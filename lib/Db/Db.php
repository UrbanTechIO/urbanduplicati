<?php
namespace OCA\UrbanDuplicati\Db;

use OCP\IDBConnection;

class Db {
    private IDBConnection $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $rows = $this->fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    public function execute(string $sql, array $params = []): void {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function lastInsertId(string $table = ''): int {
        return (int)$this->db->lastInsertId($table);
    }
}
