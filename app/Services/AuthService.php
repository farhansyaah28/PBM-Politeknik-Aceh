<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuthService
{
    public function __construct(private PDO $db) {}

    /** @return array{user_id:string,participant_id:?string,role:string}|null */
    public function authenticate(string $identifier, string $password): ?array
    {
        $statement = $this->db->prepare('SELECT u.id AS user_id, u.password_hash, u.role, u.status AS user_status, p.id AS participant_id FROM users u LEFT JOIN participants p ON p.user_id = u.id WHERE (u.username = :username OR u.email = :email OR p.registration_number = :number) LIMIT 1');
        $statement->execute(['username' => $identifier, 'email' => $identifier, 'number' => $identifier]);
        $user = $statement->fetch();
        if (!$user || $user['user_status'] === 'DISABLED' || !password_verify($password, $user['password_hash'])) {
            $this->audit('login.failed', null, null, hash('sha256', strtolower($identifier)));
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE users SET password_hash = :password_hash, updated_at = UTC_TIMESTAMP() WHERE id = :id')->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['user_id']]);
        }
        $this->audit('login.success', $user['user_id'], $user['participant_id'], $user['user_id'], $user['role']);
        return ['user_id' => $user['user_id'], 'participant_id' => $user['participant_id'], 'role' => $user['role']];
    }

    /** @param array{user_id:string,participant_id:?string,role:string} $user */
    public function recordLogout(array $user): void
    {
        $this->audit('logout', $user['user_id'], $user['participant_id'], $user['user_id'], $user['role']);
    }

    private function audit(string $action, ?string $actorId, ?string $participantId, string $targetId, string $actorType = 'ANONYMOUS'): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, :actor_type, :actor_id, :participant_id, :action, "auth", :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'actor_type' => $actorId === null ? 'ANONYMOUS' : $actorType, 'actor_id' => $actorId, 'participant_id' => $participantId, 'action' => $action, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12))]);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
