<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const SESSION_LIFETIME = 7200; // 2 hours of inactivity

/** Stores PHP sessions in the database (Vercel has no persistent disk). */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private function cutoff(): string
    {
        return gmdate('c', time() - SESSION_LIFETIME);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function validateId(string $id): bool
    {
        $stmt = db()->prepare(
            'SELECT 1 FROM sessions
             WHERE id = :id AND last_activity > CAST(:cutoff AS TIMESTAMPTZ)'
        );
        $stmt->execute(['id' => $id, 'cutoff' => $this->cutoff()]);
        return (bool)$stmt->fetchColumn();
    }

    public function read(string $id): string|false
    {
        $stmt = db()->prepare(
            'SELECT data FROM sessions
             WHERE id = :id AND last_activity > CAST(:cutoff AS TIMESTAMPTZ)'
        );
        $stmt->execute(['id' => $id, 'cutoff' => $this->cutoff()]);
        $row = $stmt->fetch();

        return $row ? (string)base64_decode($row['data'], true) : '';
    }

    public function write(string $id, string $data): bool
    {
        $stmt = db()->prepare(
            'INSERT INTO sessions (id, data, last_activity)
             VALUES (:id, :data, NOW())
             ON CONFLICT (id) DO UPDATE
             SET data = EXCLUDED.data, last_activity = NOW()'
        );
        return $stmt->execute(['id' => $id, 'data' => base64_encode($data)]);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $stmt = db()->prepare('UPDATE sessions SET last_activity = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function destroy(string $id): bool
    {
        $stmt = db()->prepare('DELETE FROM sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = db()->prepare(
            'DELETE FROM sessions WHERE last_activity < CAST(:cutoff AS TIMESTAMPTZ)'
        );
        $stmt->execute(['cutoff' => $this->cutoff()]);
        return $stmt->rowCount();
    }
}

/** Start a secure, database-backed session. */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);

    session_name('smallpay_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_set_save_handler(new DbSessionHandler(), true);
    session_start();
}