const fs = require('fs');

// --- 1. UPDATE public/api.php ---
let apiContent = fs.readFileSync('public/api.php', 'utf8');

// A. Insert isSystemBounceOrDeliveryWarning function
const funcTarget = `function deletePhysicalMaildirFiles($messageId, $subject = '', $fromEmail = '') {`;
const funcCode = `function isSystemBounceOrDeliveryWarning($fromEmail = '', $fromName = '', $subject = '', $bodyText = '') {
    $f = strtolower((string)$fromEmail);
    $n = strtolower((string)$fromName);
    $s = strtolower((string)$subject);
    $b = strtolower((string)$bodyText);

    if (
        str_contains($f, 'mailer-daemon') ||
        str_contains($f, 'postmaster') ||
        str_contains($n, 'mail delivery') ||
        str_contains($n, 'mail delivery software') ||
        str_contains($n, 'mail delivery system') ||
        str_contains($n, 'mail delivery subsystem') ||
        str_contains($s, 'mail delivery failed') ||
        str_contains($s, 'delivery status notification') ||
        str_contains($s, 'warning: message') ||
        str_contains($s, 'delayed 72 hours') ||
        str_contains($s, 'undelivered mail') ||
        str_contains($b, 'created automatically by mail delivery software') ||
        str_contains($b, 'host lookup did not complete') ||
        str_contains($b, 'has not yet been delivered to one or more of its recipients') ||
        str_contains($b, 'delivery attempts will continue') ||
        str_contains($b, 'myboostmobile.com')
    ) {
        return true;
    }
    return false;
}

function deletePhysicalMaildirFiles($messageId, $subject = '', $fromEmail = '') {`;

if (!apiContent.includes('function isSystemBounceOrDeliveryWarning(')) {
  apiContent = apiContent.replace(funcTarget, funcCode);
  console.log('Added isSystemBounceOrDeliveryWarning to api.php');
}

// B. Purge bounce messages in syncInboundEmails
const syncTarget = `function syncInboundEmails($db, $force = false) {
    // Ultra-Fast Zero-Lag Inbound Sync`;
const syncCode = `function syncInboundEmails($db, $force = false) {
    // Ultra-Fast Zero-Lag Inbound Sync
    @$db->exec("DELETE FROM messages WHERE 
        LOWER(sender_email) LIKE '%mailer-daemon%' OR
        LOWER(sender_email) LIKE '%postmaster%' OR
        LOWER(sender_name) LIKE '%mail delivery%' OR
        LOWER(subject) LIKE '%delayed%' OR
        LOWER(subject) LIKE '%mail delivery failed%' OR
        LOWER(subject) LIKE '%warning: message%' OR
        LOWER(body) LIKE '%created automatically by mail delivery software%' OR
        LOWER(body) LIKE '%host lookup did not complete%' OR
        LOWER(body) LIKE '%myboostmobile.com%'
    ");`;

if (apiContent.includes(syncTarget)) {
  apiContent = apiContent.replace(syncTarget, syncCode);
  console.log('Added bounce purge in syncInboundEmails');
}

// C. In IMAP loop: filter out system bounce
const imapTarget = `$bodyText = $extracted['text'] ?: '(no text body)';
                    $bodyHtml = $extracted['html'] ?: '';
                    $attachments = $extracted['attachments'] ?? [];`;

const imapReplacement = `$bodyText = $extracted['text'] ?: '(no text body)';
                    $bodyHtml = $extracted['html'] ?: '';
                    $attachments = $extracted['attachments'] ?? [];

                    // Silently drop mail delivery delay & failure warnings
                    if (isSystemBounceOrDeliveryWarning($fromEmail, $fromName, $subject, $bodyText)) {
                        @imap_delete($mbox, $i);
                        if (!empty($messageIdHeader)) {
                            @$db->prepare('INSERT OR IGNORE INTO deleted_messages (message_id, user_id, subject, sender_email) VALUES (?, "system", ?, ?)')->execute([$messageIdHeader, $subject, $fromEmail]);
                        }
                        continue;
                    }`;

if (apiContent.includes(imapTarget)) {
  apiContent = apiContent.replace(imapTarget, imapReplacement);
  console.log('Added IMAP bounce filter in api.php');
}

// D. In Maildir loop: filter out system bounce
const maildirTarget = `if (empty($bodyText)) {
            $bodyText = strip_tags($bodyHtml ?: $rawBody);
        }`;

const maildirReplacement = `if (empty($bodyText)) {
            $bodyText = strip_tags($bodyHtml ?: $rawBody);
        }

        // Silently drop mail delivery delay & failure warnings
        if (isSystemBounceOrDeliveryWarning($fromEmail, $fromName, $subject, $bodyText)) {
            @unlink($srcPath);
            if (!empty($messageId)) {
                @$db->prepare('INSERT OR IGNORE INTO deleted_messages (message_id, user_id, subject, sender_email) VALUES (?, "system", ?, ?)')->execute([$messageId, $subject, $fromEmail]);
            }
            continue;
        }`;

if (apiContent.includes(maildirTarget)) {
  apiContent = apiContent.replace(maildirTarget, maildirReplacement);
  console.log('Added Maildir bounce filter in api.php');
}

// E. In /api/messages query: exclude bounces from result
const whereTarget = `$where = ['user_id = ?'];
        $params = [$currentUserId];`;
const whereReplacement = `$where = ['user_id = ?'];
        $params = [$currentUserId];
        // Exclude automated mail delivery software delay warnings and bounces
        $where[] = 'NOT (
            LOWER(sender_email) LIKE "%mailer-daemon%" OR
            LOWER(sender_email) LIKE "%postmaster%" OR
            LOWER(sender_name) LIKE "%mail delivery%" OR
            LOWER(subject) LIKE "%delayed%" OR
            LOWER(subject) LIKE "%mail delivery failed%" OR
            LOWER(subject) LIKE "%warning: message%" OR
            LOWER(body) LIKE "%created automatically by mail delivery software%" OR
            LOWER(body) LIKE "%host lookup did not complete%" OR
            LOWER(body) LIKE "%myboostmobile.com%"
        )';`;

if (apiContent.includes(whereTarget)) {
  apiContent = apiContent.replace(whereTarget, whereReplacement);
  console.log('Added WHERE filter to /api/messages in api.php');
}

fs.writeFileSync('public/api.php', apiContent, 'utf8');

// --- 2. UPDATE public/inbox.php ---
let inboxContent = fs.readFileSync('public/inbox.php', 'utf8');

// A. In top query for initial inbox messages
const inboxSqlOld = `WHERE user_id = ? AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0 AND (folder_id IS NULL OR folder_id = '' OR folder_id = 'inbox' OR folder_id = 'updates') ORDER BY created_at DESC LIMIT 50`;
const inboxSqlNew = `WHERE user_id = ? AND is_draft = 0 AND (is_spam = 0 OR LOWER(sender_email) IN (SELECT LOWER(email) FROM users)) AND is_trashed = 0 AND is_archived = 0 AND (folder_id IS NULL OR folder_id = '' OR folder_id = 'inbox' OR folder_id = 'updates') AND NOT (LOWER(sender_email) LIKE '%mailer-daemon%' OR LOWER(sender_name) LIKE '%mail delivery%' OR LOWER(subject) LIKE '%delayed%' OR LOWER(body) LIKE '%created automatically by mail delivery software%' OR LOWER(body) LIKE '%myboostmobile.com%') ORDER BY created_at DESC LIMIT 50`;

if (inboxContent.includes(inboxSqlOld)) {
  inboxContent = inboxContent.replace(inboxSqlOld, inboxSqlNew);
  console.log('Updated initial inbox messages query in inbox.php');
}

// B. Also run purge query in top of inbox.php
const topPurgeOld = `try {
    $db = getDbConnection();`;
const topPurgeNew = `try {
    $db = getDbConnection();
    // Auto-purge any automated mail delivery warnings or bounces
    @$db->exec("DELETE FROM messages WHERE 
        LOWER(sender_email) LIKE '%mailer-daemon%' OR
        LOWER(sender_email) LIKE '%postmaster%' OR
        LOWER(sender_name) LIKE '%mail delivery%' OR
        LOWER(subject) LIKE '%delayed%' OR
        LOWER(subject) LIKE '%mail delivery failed%' OR
        LOWER(subject) LIKE '%warning: message%' OR
        LOWER(body) LIKE '%created automatically by mail delivery software%' OR
        LOWER(body) LIKE '%host lookup did not complete%' OR
        LOWER(body) LIKE '%myboostmobile.com%'
    ");`;

if (inboxContent.includes(topPurgeOld)) {
  inboxContent = inboxContent.replace(topPurgeOld, topPurgeNew);
  console.log('Added top DB purge in inbox.php');
}

// C. In frontend JavaScript getCurrentFilteredList
const filterOld = `      if (currentFilter === 'unread') list = list.filter(m => !m.isRead);`;
const filterNew = `      // Filter out automated mail delivery software delay warnings and bounces
      list = list.filter(m => {
        const text = ((m.sender || m.sender_email || '') + ' ' + (m.senderName || m.sender_name || '') + ' ' + (m.subject || '') + ' ' + (m.body || '') + ' ' + (m.preview || '')).toLowerCase();
        return !text.includes('mailer-daemon') &&
               !text.includes('mail delivery system') &&
               !text.includes('mail delivery software') &&
               !text.includes('created automatically by mail delivery software') &&
               !text.includes('host lookup did not complete') &&
               !text.includes('delayed 72 hours') &&
               !text.includes('warning: message') &&
               !text.includes('myboostmobile.com');
      });

      if (currentFilter === 'unread') list = list.filter(m => !m.isRead);`;

if (inboxContent.includes(filterOld)) {
  inboxContent = inboxContent.replace(filterOld, filterNew);
  console.log('Added frontend JS bounce filter in inbox.php');
}

fs.writeFileSync('public/inbox.php', inboxContent, 'utf8');
console.log('Saved changes to api.php and inbox.php!');
