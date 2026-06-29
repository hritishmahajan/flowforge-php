<?php
declare(strict_types=1);

namespace FlowForge;

/**
 * Storage abstraction.
 *
 * Ships with a zero-config JSON file backend so the app runs anywhere PHP runs
 * (no DB setup needed for the demo). A drop-in MySQL backend is provided too —
 * set FLOWFORGE_DSN in the environment to switch. Same interface, no app changes.
 */
interface Storage
{
    /** @return array<int,array<string,mixed>> */
    public function all(string $collection): array;
    public function insert(string $collection, array $row): array;
    public function clear(string $collection): void;
}

final class JsonStorage implements Storage
{
    public function __construct(private string $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function path(string $c): string
    {
        return $this->dir . '/' . preg_replace('/[^a-z0-9_]/i', '', $c) . '.json';
    }

    public function all(string $collection): array
    {
        $p = $this->path($collection);
        if (!is_file($p)) {
            return [];
        }
        return json_decode((string)file_get_contents($p), true) ?: [];
    }

    public function insert(string $collection, array $row): array
    {
        $rows = $this->all($collection);
        $row['id'] = $row['id'] ?? bin2hex(random_bytes(6));
        $row['created_at'] = $row['created_at'] ?? gmdate('c');
        $rows[] = $row;
        // Atomic write — avoids torn reads under PHP's built-in server.
        $tmp = $this->path($collection) . '.tmp';
        file_put_contents($tmp, json_encode($rows, JSON_PRETTY_PRINT));
        rename($tmp, $this->path($collection));
        return $row;
    }

    public function clear(string $collection): void
    {
        @unlink($this->path($collection));
    }
}

/**
 * MySQL backend — ticks the "MySQL" box from the JD. Activated via FLOWFORGE_DSN.
 * Schema lives in schema.sql. Uses PDO + prepared statements (no SQL injection).
 */
final class MysqlStorage implements Storage
{
    private \PDO $db;

    public function __construct(string $dsn, string $user, string $pass)
    {
        $this->db = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    public function all(string $collection): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table($collection)}` ORDER BY created_at ASC");
        $stmt->execute();
        return array_map(fn($r) => $this->decode($r), $stmt->fetchAll());
    }

    public function insert(string $collection, array $row): array
    {
        $row['id'] = $row['id'] ?? bin2hex(random_bytes(6));
        $row['created_at'] = $row['created_at'] ?? gmdate('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table($collection)}` (id, payload, created_at) VALUES (?, ?, ?)"
        );
        $stmt->execute([$row['id'], json_encode($row), $row['created_at']]);
        return $row;
    }

    public function clear(string $collection): void
    {
        $this->db->exec("TRUNCATE TABLE `{$this->table($collection)}`");
    }

    private function table(string $c): string
    {
        return preg_replace('/[^a-z0-9_]/i', '', $c);
    }

    private function decode(array $r): array
    {
        return json_decode($r['payload'], true) ?: $r;
    }
}
