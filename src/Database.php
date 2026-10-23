<?php
declare(strict_types=1);

namespace RacconMail\Core;

use PDO;
use Exception;

/**
 * High-performance SQLite database connection pool with WAL mode & busy timeout.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(?string $dbPath = null): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $path = $dbPath ?? (__DIR__ . '/../raccoon_mail.db');
        $dsn = "sqlite:" . $path;

        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Enforce high concurrency and reliability
        $pdo->exec('PRAGMA busy_timeout = 15000;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');

        self::$instance = $pdo;
        return self::$instance;
    }

    public static function migrate(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id TEXT PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                name TEXT NOT NULL,
                phone TEXT,
                phone_verified INTEGER DEFAULT 0,
                avatar TEXT DEFAULT '',
                cover_photo TEXT DEFAULT '',
                theme TEXT DEFAULT 'harmony',
                is_admin INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS messages (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                sender_name TEXT NOT NULL,
                sender_email TEXT NOT NULL,
                recipient_email TEXT NOT NULL,
                cc TEXT DEFAULT '',
                bcc TEXT DEFAULT '',
                reply_to TEXT DEFAULT '',
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                body_html TEXT DEFAULT '',
                is_read INTEGER DEFAULT 0,
                is_starred INTEGER DEFAULT 0,
                is_archived INTEGER DEFAULT 0,
                is_spam INTEGER DEFAULT 0,
                is_trashed INTEGER DEFAULT 0,
                is_draft INTEGER DEFAULT 0,
                folder_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS attachments (
                id TEXT PRIMARY KEY,
                message_id TEXT NOT NULL,
                filename TEXT NOT NULL,
                original_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                file_path TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS contacts (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                notes TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS folders (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                name TEXT NOT NULL,
                color TEXT DEFAULT '#208ED3',
                icon TEXT DEFAULT 'folder',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");
    }
}
