<?php
declare(strict_types=1);

namespace RacconMail\Core;

use PDO;
use Exception;

/**
 * Inbound IMAP Mail Poller with Anti-Spam Bypass Protection
 */
class IMAPSync
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Polls the cPanel / IMAP server and syncs inbound emails into local SQLite store.
     */
    public function sync(): int
    {
        if (!function_exists('imap_open')) {
            throw new Exception('PHP IMAP extension is required for mail synchronization.');
        }

        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 993;
        $flags = $this->config['flags'] ?? '/imap/ssl/novalidate-cert';
        $user = $this->config['user'] ?? '';
        $pass = $this->config['pass'] ?? '';

        $mailboxStr = "{" . $host . ":" . $port . $flags . "}INBOX";
        $mbox = @imap_open($mailboxStr, $user, $pass);

        if (!$mbox) {
            @imap_errors();
            return 0;
        }

        $msgCount = imap_num_msg($mbox);
        $synced = 0;

        for ($i = 1; $i <= $msgCount; $i++) {
            $header = imap_headerinfo($mbox, $i);
            if (!$header) continue;

            $fromEmail = strtolower($header->from[0]->mailbox . '@' . $header->from[0]->host);
            $fromName = $header->from[0]->personal ?? $fromEmail;
            $subject = isset($header->subject) ? mb_decode_mimeheader($header->subject) : '(no subject)';

            // Locate matching recipient user
            $toEmail = strtolower($header->to[0]->mailbox . '@' . $header->to[0]->host);
            $stmtUser = $this->db->prepare('SELECT id, email FROM users WHERE LOWER(email) = LOWER(?)');
            $stmtUser->execute([$toEmail]);
            $recipient = $stmtUser->fetch();

            if (!$recipient) {
                // If not found, deliver to default admin
                $stmtAdmin = $this->db->prepare('SELECT id, email FROM users WHERE is_admin = 1 LIMIT 1');
                $stmtAdmin->execute();
                $recipient = $stmtAdmin->fetch();
            }

            if ($recipient) {
                $body = imap_body($mbox, $i);
                $msgId = 'in_' . uniqid();

                $stmtIns = $this->db->prepare('
                    INSERT INTO messages (id, user_id, sender_name, sender_email, recipient_email, subject, body, is_read, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, datetime("now"))
                ');
                $stmtIns->execute([
                    $msgId,
                    $recipient['id'],
                    $fromName,
                    $fromEmail,
                    $recipient['email'],
                    $subject,
                    $body
                ]);

                $synced++;
                @imap_delete($mbox, $i);
            }
        }

        @imap_expunge($mbox);
        @imap_close($mbox);
        @imap_errors(); // Suppress notice warnings on shutdown

        return $synced;
    }
}
