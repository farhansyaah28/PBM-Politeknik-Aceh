<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class AdminConfigurationService
{
    public function __construct(private PDO $db) {}

    /** @return array{programs:array<int,array<string,mixed>>,admission_paths:array<int,array<string,mixed>>,waves:array<int,array<string,mixed>>} */
    public function adminCatalog(): array
    {
        return [
            'programs' => $this->db->query('SELECT id, code, name, is_active FROM study_programs ORDER BY is_active DESC, name')->fetchAll(),
            'admission_paths' => $this->db->query('SELECT id, name, is_active FROM admission_paths ORDER BY is_active DESC, name')->fetchAll(),
            'waves' => $this->db->query('SELECT id, code, name, is_active, exam_question_count, exam_duration_minutes, passing_grade, security_mode FROM admission_waves ORDER BY is_active DESC, name')->fetchAll(),
        ];
    }

    /** @param array<string,string> $input */
    public function createProgram(array $input, string $actorId): void
    {
        $code = strtoupper(trim($input['code'] ?? '')); $name = trim($input['name'] ?? '');
        if ($code === '' || $name === '') throw new ValidationException(['program' => 'Kode dan nama program studi wajib diisi.']);
        if (!preg_match('/^[A-Z0-9-]{2,24}$/', $code)) throw new ValidationException(['program' => 'Kode program hanya boleh berisi huruf kapital, angka, atau tanda hubung.']);
        try {
            $id = $this->uuid();
            $this->db->prepare('INSERT INTO study_programs (id, code, name, is_active, created_at, updated_at) VALUES (:id, :code, :name, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $id, 'code' => $code, 'name' => $name]);
            $this->audit($actorId, 'config.program.created', 'study_program', $id);
        } catch (\PDOException $exception) { throw new ValidationException(['program' => 'Kode atau nama program studi sudah digunakan.']); }
    }

    /** @param array<string,string> $input */
    public function updateProgram(string $programId, array $input, string $actorId): void
    {
        $code = strtoupper(trim($input['code'] ?? '')); $name = trim($input['name'] ?? '');
        if ($code === '' || $name === '') throw new ValidationException(['program' => 'Kode dan nama program studi wajib diisi.']);
        if (!preg_match('/^[A-Z0-9-]{2,24}$/', $code)) throw new ValidationException(['program' => 'Kode program hanya boleh berisi huruf kapital, angka, atau tanda hubung.']);
        $exists = $this->db->prepare('SELECT id FROM study_programs WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $programId]);
        if (!$exists->fetch()) throw new ValidationException(['program' => 'Program studi tidak ditemukan.']);
        try {
            $this->db->prepare('UPDATE study_programs SET code = :code, name = :name, updated_at = UTC_TIMESTAMP() WHERE id = :id')->execute(['code' => $code, 'name' => $name, 'id' => $programId]);
            $this->audit($actorId, 'config.program.updated', 'study_program', $programId);
        } catch (\PDOException $exception) { throw new ValidationException(['program' => 'Kode atau nama program studi sudah digunakan.']); }
    }

    public function deleteProgram(string $programId, string $actorId): void
    {
        $exists = $this->db->prepare('SELECT id FROM study_programs WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $programId]);
        if (!$exists->fetch()) throw new ValidationException(['program' => 'Program studi tidak ditemukan.']);
        $usedByParticipant = $this->db->prepare('SELECT COUNT(*) FROM participants WHERE program_id = :id');
        $usedByParticipant->execute(['id' => $programId]);
        if ((int) $usedByParticipant->fetchColumn() > 0) throw new ValidationException(['program' => 'Program studi sudah dipakai data peserta dan tidak dapat dihapus. Gunakan Nonaktifkan untuk menjaga riwayat.']);
        $this->db->prepare('DELETE FROM study_programs WHERE id = :id')->execute(['id' => $programId]);
        $this->audit($actorId, 'config.program.deleted', 'study_program', $programId);
    }

    /** @param array<string,string> $input */
    public function createAdmissionPath(array $input, string $actorId): void
    {
        $name = trim($input['name'] ?? '');
        $this->assertAdmissionPathName($name);
        try {
            $id = $this->uuid();
            $this->db->prepare('INSERT INTO admission_paths (id, name, is_active, created_at, updated_at) VALUES (:id, :name, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute(['id' => $id, 'name' => $name]);
            $this->audit($actorId, 'config.admission_path.created', 'admission_path', $id);
        } catch (\PDOException $exception) {
            throw new ValidationException(['admission_path' => 'Nama jalur masuk sudah digunakan.']);
        }
    }

    /** @param array<string,string> $input */
    public function updateAdmissionPath(string $pathId, array $input, string $actorId): void
    {
        $name = trim($input['name'] ?? '');
        $this->assertAdmissionPathName($name);
        $exists = $this->db->prepare('SELECT id, name FROM admission_paths WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $pathId]);
        $current = $exists->fetch();
        if (!$current) throw new ValidationException(['admission_path' => 'Jalur masuk tidak ditemukan.']);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE admission_paths SET name = :name, updated_at = UTC_TIMESTAMP() WHERE id = :id')->execute(['name' => $name, 'id' => $pathId]);
            $this->db->prepare('UPDATE participants SET admission_path = :name, updated_at = UTC_TIMESTAMP() WHERE admission_path = :current_name')->execute(['name' => $name, 'current_name' => $current['name']]);
            $this->audit($actorId, 'config.admission_path.updated', 'admission_path', $pathId);
            $this->db->commit();
        } catch (\PDOException $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw new ValidationException(['admission_path' => 'Nama jalur masuk sudah digunakan.']);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function deleteAdmissionPath(string $pathId, string $actorId): void
    {
        $path = $this->db->prepare('SELECT id, name FROM admission_paths WHERE id = :id LIMIT 1');
        $path->execute(['id' => $pathId]);
        $record = $path->fetch();
        if (!$record) throw new ValidationException(['admission_path' => 'Jalur masuk tidak ditemukan.']);
        $used = $this->db->prepare('SELECT COUNT(*) FROM participants WHERE admission_path = :name');
        $used->execute(['name' => $record['name']]);
        if ((int) $used->fetchColumn() > 0) throw new ValidationException(['admission_path' => 'Jalur masuk sudah dipakai data peserta dan tidak dapat dihapus. Gunakan Nonaktifkan untuk menjaga riwayat.']);
        $this->db->prepare('DELETE FROM admission_paths WHERE id = :id')->execute(['id' => $pathId]);
        $this->audit($actorId, 'config.admission_path.deleted', 'admission_path', $pathId);
    }

    /** @param array<string,string> $input */
    public function createWave(array $input, string $actorId): void
    {
        $errors = $this->validateWave($input);
        if ($errors !== []) throw new ValidationException($errors);
        try {
            $id = $this->uuid();
            $this->db->prepare('INSERT INTO admission_waves (id, code, name, is_active, exam_question_count, exam_duration_minutes, passing_grade, security_mode, created_at, updated_at) VALUES (:id, :code, :name, 1, :exam_question_count, :exam_duration_minutes, :passing_grade, :security_mode, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute($this->waveParameters($input, $id));
            $this->audit($actorId, 'config.wave.created', 'admission_wave', $id);
        } catch (\PDOException $exception) { throw new ValidationException(['wave' => 'Kode atau nama gelombang sudah digunakan.']); }
    }

    /** @param array<string,string> $input */
    public function updateWave(string $waveId, array $input, string $actorId): void
    {
        $errors = $this->validateWave($input);
        if ($errors !== []) throw new ValidationException($errors);
        $parameters = $this->waveParameters($input, $waveId);
        $this->db->prepare('UPDATE admission_waves SET code=:code, name=:name, exam_question_count=:exam_question_count, exam_duration_minutes=:exam_duration_minutes, passing_grade=:passing_grade, security_mode=:security_mode, updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute($parameters);
        $this->audit($actorId, 'config.wave.updated', 'admission_wave', $waveId);
    }

    public function toggle(string $entity, string $id, string $actorId): void
    {
        $table = match ($entity) {
            'program' => 'study_programs',
            'admission_path' => 'admission_paths',
            'wave' => 'admission_waves',
            default => null,
        };
        if ($table === null) throw new RuntimeException('Jenis konfigurasi tidak valid.');
        $this->db->prepare("UPDATE {$table} SET is_active = NOT is_active, updated_at = UTC_TIMESTAMP() WHERE id = :id")->execute(['id' => $id]);
        $targetType = match ($entity) {'program' => 'study_program', 'admission_path' => 'admission_path', default => 'admission_wave'};
        $this->audit($actorId, "config.{$entity}.toggled", $targetType, $id);
    }

    private function assertAdmissionPathName(string $name): void
    {
        if ($name === '') throw new ValidationException(['admission_path' => 'Nama jalur masuk wajib diisi.']);
        if (mb_strlen($name) > 64) throw new ValidationException(['admission_path' => 'Nama jalur masuk maksimal 64 karakter.']);
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .&()\/-]*$/u', $name)) throw new ValidationException(['admission_path' => 'Nama jalur masuk mengandung karakter yang tidak didukung.']);
    }

    /** @param array<string,string> $input @return array<string,string> */
    private function validateWave(array $input): array
    {
        $errors = []; $code = strtoupper(trim($input['code'] ?? '')); $name = trim($input['name'] ?? '');
        if ($code === '' || !preg_match('/^[A-Z0-9-]{2,24}$/', $code)) $errors['code'] = 'Kode gelombang wajib diisi (huruf kapital, angka, atau tanda hubung).';
        if ($name === '') $errors['name'] = 'Nama gelombang wajib diisi.';
        foreach (['exam_question_count', 'exam_duration_minutes'] as $field) if (!isset($input[$field]) || filter_var($input[$field], FILTER_VALIDATE_INT) === false || (int) $input[$field] < 1) $errors[$field] = 'Nilai harus berupa bilangan positif.';
        if (!isset($input['passing_grade']) || !is_numeric($input['passing_grade']) || (float) $input['passing_grade'] < 0 || (float) $input['passing_grade'] > 100) $errors['passing_grade'] = 'Passing grade harus antara 0 sampai 100.';
        if (!in_array($input['security_mode'] ?? '', ['WEB_STRICT', 'PROCTORING_LITE'], true)) $errors['security_mode'] = 'Mode keamanan tidak valid.';
        return $errors;
    }

    /** @param array<string,string> $input @return array<string,mixed> */
    private function waveParameters(array $input, string $id): array
    {
        return ['id' => $id, 'code' => strtoupper(trim($input['code'])), 'name' => trim($input['name']), 'exam_question_count' => (int) $input['exam_question_count'], 'exam_duration_minutes' => (int) $input['exam_duration_minutes'], 'passing_grade' => $input['passing_grade'], 'security_mode' => $input['security_mode']];
    }

    private function audit(string $actorId, string $action, string $targetType, string $targetId): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id, actor_type, actor_id, participant_id, action, target_type, target_id, request_id, created_at) VALUES (:id, "ADMIN", :actor_id, NULL, :action, :target_type, :target_id, :request_id, UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'actor_id' => $actorId, 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId, 'request_id' => bin2hex(random_bytes(12))]);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
