<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Paginator;
use PDO;

final class ExamSecurityService
{
    public function __construct(private PDO $db, private array $config = []) {}

    public function acceptConsent(string $participantId, string $sessionId): void
    {
        $assignment = $this->db->prepare('SELECT id FROM exam_assignments WHERE participant_id=:participant_id AND session_id=:session_id AND assignment_status IN ("ASSIGNED", "STARTED") LIMIT 1');
        $assignment->execute(['participant_id' => $participantId, 'session_id' => $sessionId]);
        if (!$assignment->fetch()) throw new ValidationException(['consent' => 'Sesi tidak ditugaskan kepada peserta ini.']);
        $this->db->prepare('INSERT IGNORE INTO exam_session_consents (id, participant_id, session_id, consent_version, accepted_at, created_at) VALUES (:id, :participant_id, :session_id, "EXAM-SECURITY-v1", UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'participant_id' => $participantId, 'session_id' => $sessionId]);
        $this->audit($participantId, 'exam.consent.accepted', 'exam_session', $sessionId);
    }

    /** @return array{violations:int,paused:bool} */
    public function recordEvent(string $participantId, string $attemptId, string $eventType, array $details = []): array
    {
        $allowed = ['FULLSCREEN_ENTERED', 'FULLSCREEN_EXIT', 'TAB_HIDDEN', 'CAMERA_GRANTED', 'CAMERA_DENIED', 'PHOTO_CAPTURED'];
        if (!in_array($eventType, $allowed, true)) throw new ValidationException(['security' => 'Jenis security event tidak valid.']);
        $this->db->beginTransaction();
        try {
            $attempt = $this->db->prepare('SELECT id, status FROM exam_attempts WHERE id=:attempt_id AND participant_id=:participant_id FOR UPDATE');
            $attempt->execute(['attempt_id' => $attemptId, 'participant_id' => $participantId]); $attemptRow = $attempt->fetch();
            if (!$attemptRow || !in_array($attemptRow['status'], ['READY','IN_PROGRESS'], true)) throw new ValidationException(['security' => 'Attempt tidak aktif untuk pencatatan security event.']);
            $this->db->prepare('INSERT INTO exam_security_events (id, attempt_id, participant_id, event_type, details_json, created_at) VALUES (:id, :attempt_id, :participant_id, :event_type, :details_json, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'attempt_id' => $attemptId, 'participant_id' => $participantId, 'event_type' => $eventType, 'details_json' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR)]);
            $violations = $this->db->prepare('SELECT COUNT(*) FROM exam_security_events WHERE attempt_id=:attempt_id AND event_type IN ("FULLSCREEN_EXIT", "TAB_HIDDEN", "CAMERA_DENIED")');
            $violations->execute(['attempt_id' => $attemptId]); $count = (int) $violations->fetchColumn();
            $paused = $attemptRow['status'] === 'IN_PROGRESS' && $count >= 3;
            if ($paused) $this->db->prepare('UPDATE exam_attempts SET status="PAUSED_REVIEW", updated_at=UTC_TIMESTAMP() WHERE id=:attempt_id')->execute(['attempt_id' => $attemptId]);
            $this->audit($participantId, 'exam.security.' . strtolower($eventType), 'exam_attempt', $attemptId);
            $this->db->commit();
            return ['violations' => $count, 'paused' => $paused];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function storeInitialPhoto(string $participantId, string $attemptId, string $dataUrl): void
    {
        if (!preg_match('#^data:(image/png|image/jpeg);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $matches)) throw new ValidationException(['photo' => 'Format foto awal tidak valid.']);
        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || strlen($bytes) < 8 || strlen($bytes) > 2097152) throw new ValidationException(['photo' => 'Ukuran foto awal tidak valid.']);
        $signature = $matches[1] === 'image/png' ? "\x89PNG\r\n\x1A\n" : "\xFF\xD8\xFF";
        if (!hash_equals($signature, substr($bytes, 0, strlen($signature)))) throw new ValidationException(['photo' => 'Isi foto awal tidak sesuai dengan format yang dipilih.']);
        $storagePath = null;
        $this->db->beginTransaction();
        try {
            $attempt = $this->db->prepare('SELECT id FROM exam_attempts WHERE id=:attempt_id AND participant_id=:participant_id AND status IN ("READY", "IN_PROGRESS") FOR UPDATE');
            $attempt->execute(['attempt_id' => $attemptId, 'participant_id' => $participantId]);
            if (!$attempt->fetch()) throw new ValidationException(['photo' => 'Attempt tidak aktif untuk foto awal.']);
            $exists = $this->db->prepare('SELECT id FROM proctoring_photos WHERE attempt_id=:attempt_id LIMIT 1');
            $exists->execute(['attempt_id' => $attemptId]);
            if ($exists->fetch()) { $this->db->commit(); return; }
            $id = $this->uuid(); $extension = $matches[1] === 'image/png' ? 'png' : 'jpg';
            $storageKey = 'proctoring/' . $attemptId . '/' . $id . '.' . $extension;
            $base = rtrim($this->config['private_storage_path'] ?? dirname(__DIR__, 2) . '/storage/private', DIRECTORY_SEPARATOR);
            $storagePath = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
            if (!is_dir(dirname($storagePath)) && !mkdir(dirname($storagePath), 0750, true) && !is_dir(dirname($storagePath))) throw new \RuntimeException('Penyimpanan foto privat tidak dapat disiapkan.');
            if (file_put_contents($storagePath, $bytes, LOCK_EX) === false) throw new \RuntimeException('Foto awal belum dapat disimpan.');
            @chmod($storagePath, 0640);
            $this->db->prepare('INSERT INTO proctoring_photos (id, attempt_id, participant_id, storage_key, mime_type, size_bytes, content_hash, captured_at, created_at) VALUES (:id, :attempt_id, :participant_id, :storage_key, :mime_type, :size_bytes, :content_hash, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $id, 'attempt_id' => $attemptId, 'participant_id' => $participantId, 'storage_key' => $storageKey, 'mime_type' => $matches[1], 'size_bytes' => strlen($bytes), 'content_hash' => hash('sha256', $bytes)]);
            $this->db->prepare('INSERT INTO exam_security_events (id, attempt_id, participant_id, event_type, details_json, created_at) VALUES (:id, :attempt_id, :participant_id, "PHOTO_CAPTURED", NULL, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'attempt_id' => $attemptId, 'participant_id' => $participantId]);
            $this->audit($participantId, 'exam.proctoring.photo_captured', 'proctoring_photo', $id);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($storagePath && is_file($storagePath)) @unlink($storagePath);
            throw $exception;
        }
    }

    public function hasInitialPhoto(string $participantId, string $attemptId): bool
    {
        $statement = $this->db->prepare('SELECT id FROM proctoring_photos WHERE attempt_id=:attempt_id AND participant_id=:participant_id LIMIT 1');
        $statement->execute(['attempt_id' => $attemptId, 'participant_id' => $participantId]);
        return (bool) $statement->fetch();
    }

    public function resumeAttempt(string $attemptId, string $adminId): void
    {
        $this->db->beginTransaction();
        try {
            $admin = $this->db->prepare('SELECT id FROM users WHERE id=:admin_id AND role="ADMIN" AND status="ACTIVE" LIMIT 1');
            $admin->execute(['admin_id' => $adminId]);
            if (!$admin->fetch()) throw new ValidationException(['security' => 'Hanya Admin aktif yang dapat melanjutkan attempt.']);
            $attempt = $this->db->prepare('SELECT ea.id FROM exam_attempts ea JOIN exam_sessions es ON es.id=ea.session_id WHERE ea.id=:attempt_id AND ea.status="PAUSED_REVIEW" AND ea.expires_at > UTC_TIMESTAMP() AND es.ends_at > UTC_TIMESTAMP() FOR UPDATE');
            $attempt->execute(['attempt_id' => $attemptId]);
            if (!$attempt->fetch()) throw new ValidationException(['security' => 'Attempt tidak dapat dilanjutkan karena bukan dalam status review aktif atau waktu ujian telah berakhir.']);
            $this->db->prepare('UPDATE exam_attempts SET status="IN_PROGRESS", updated_at=UTC_TIMESTAMP() WHERE id=:attempt_id')->execute(['attempt_id' => $attemptId]);
            $this->auditAdmin($adminId, 'exam.security.resumed', 'exam_attempt', $attemptId);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return array{rows:list<array<string,mixed>>,paginator:Paginator} */
    public function adminMonitoring(string $adminId, mixed $page = 1): array
    {
        $admin = $this->db->prepare('SELECT id FROM users WHERE id=:admin_id AND role="ADMIN" AND status="ACTIVE" LIMIT 1');
        $admin->execute(['admin_id' => $adminId]);
        if (!$admin->fetch()) throw new ValidationException(['security' => 'Akses monitoring hanya tersedia untuk Admin aktif.']);
        $total=(int)$this->db->query('SELECT COUNT(*) FROM exam_attempts WHERE status IN ("IN_PROGRESS","PAUSED_REVIEW","SUBMITTED")')->fetchColumn();$paginator=Paginator::fromRequest($total,$page);
        $statement = $this->db->query('SELECT ea.id AS attempt_id, ea.status AS attempt_status, ea.started_at, ea.expires_at, ea.submitted_at, p.registration_number, p.full_name, es.name AS session_name, MIN(pp.id) AS photo_id, COUNT(DISTINCT ese.id) AS event_count, COUNT(DISTINCT CASE WHEN ese.event_type IN ("FULLSCREEN_EXIT", "TAB_HIDDEN", "CAMERA_DENIED") THEN ese.id END) AS violation_count FROM exam_attempts ea JOIN participants p ON p.id=ea.participant_id JOIN exam_sessions es ON es.id=ea.session_id LEFT JOIN exam_security_events ese ON ese.attempt_id=ea.id LEFT JOIN proctoring_photos pp ON pp.attempt_id=ea.id WHERE ea.status IN ("IN_PROGRESS", "PAUSED_REVIEW", "SUBMITTED") GROUP BY ea.id, ea.status, ea.started_at, ea.expires_at, ea.submitted_at, p.registration_number, p.full_name, es.name ORDER BY ea.status="PAUSED_REVIEW" DESC, ea.status="IN_PROGRESS" DESC, ea.updated_at DESC LIMIT '.$paginator->perPage.' OFFSET '.$paginator->offset());
        return ['rows'=>$statement->fetchAll(),'paginator'=>$paginator];
    }

    /** @return array<string, mixed>|null */
    public function adminPhoto(string $photoId, string $adminId): ?array
    {
        $admin = $this->db->prepare('SELECT id FROM users WHERE id=:admin_id AND role="ADMIN" AND status="ACTIVE" LIMIT 1');
        $admin->execute(['admin_id' => $adminId]);
        if (!$admin->fetch()) throw new ValidationException(['security' => 'Akses foto proctoring hanya tersedia untuk Admin aktif.']);
        $photo = $this->db->prepare('SELECT id, storage_key, mime_type, size_bytes, content_hash FROM proctoring_photos WHERE id=:photo_id LIMIT 1');
        $photo->execute(['photo_id' => $photoId]);
        $result = $photo->fetch() ?: null;
        if ($result !== null) $this->auditAdmin($adminId, 'exam.proctoring.photo_viewed', 'proctoring_photo', $photoId);
        return $result;
    }

    private function audit(string $participantId, string $action, string $targetType, string $targetId): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, "PARTICIPANT", NULL, :participant_id, :action, :target_type, :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'participant_id' => $participantId, 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12))]);
    }

    private function auditAdmin(string $adminId, string $action, string $targetType, string $targetId): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, "ADMIN", :actor_id, NULL, :action, :target_type, :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'actor_id' => $adminId, 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12))]);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
