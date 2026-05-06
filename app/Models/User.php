<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEmail(string $email): array|false {
        $stmt = $this->db->prepare("SELECT * FROM signup WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function updateLoginAttempts(int $id, int $attempts, int $locked = 0): bool {
        $stmt = $this->db->prepare("UPDATE signup SET login_attempts = ?, is_locked = ? WHERE id = ?");
        return $stmt->execute([$attempts, $locked, $id]);
    }

    public function logHistory(int $userId, string $ip): string|false {
        $stmt = $this->db->prepare("INSERT INTO login_history (user_id, login_time, ip_address) VALUES (?, NOW(), ?)");
        $stmt->execute([$userId, $ip]);
        return $this->db->lastInsertId();
    }
}
