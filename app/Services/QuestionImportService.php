<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class QuestionImportService
{
    private const HEADERS = ['category_code', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'];

    public function __construct(private PDO $db, private FileImportService $files) {}

    /** @param array<string,mixed> $file */
    public function import(array $file, string $actorId): int
    {
        $rows = $this->files->parseXlsxUpload($file, self::HEADERS);
        $categories = $this->db->query('SELECT code, id FROM question_categories WHERE is_active=1')->fetchAll(PDO::FETCH_KEY_PAIR);
        $validated = []; $errors = []; $seen = [];
        foreach ($rows as $row) {
            $line = (int) $row['_line'];
            $categoryCode = strtoupper(trim($row['category_code']));
            $question = trim($row['question_text']);
            $answers = array_map('trim', [$row['option_a'], $row['option_b'], $row['option_c'], $row['option_d']]);
            $key = strtoupper(trim($row['correct_option']));
            if (!isset($categories[$categoryCode])) $errors[] = "Baris {$line}: Kode Kategori tidak aktif atau tidak ditemukan.";
            if ($question === '' || in_array('', $answers, true) || !in_array($key, ['A', 'B', 'C', 'D'], true)) $errors[] = "Baris {$line}: Pertanyaan, Opsi A-D, dan Kunci Jawaban A-D wajib valid.";
            $duplicateKey = ($categories[$categoryCode] ?? '') . '|' . mb_strtolower(preg_replace('/\s+/', ' ', $question) ?? $question);
            if (isset($seen[$duplicateKey])) $errors[] = "Baris {$line}: duplikat dengan baris {$seen[$duplicateKey]}.";
            $seen[$duplicateKey] = $line;
            $validated[] = ['line' => $line, 'category_id' => (string) ($categories[$categoryCode] ?? ''), 'question_text' => $question, 'option_a' => $answers[0], 'option_b' => $answers[1], 'option_c' => $answers[2], 'option_d' => $answers[3], 'correct_option' => $key];
        }
        if ($errors !== []) throw new ValidationException(['import' => implode(' ', array_slice(array_unique($errors), 0, 12))]);
        $existing = $this->db->prepare('SELECT id FROM questions WHERE category_id=:category_id AND LOWER(TRIM(question_text))=LOWER(TRIM(:question_text)) LIMIT 1');
        foreach ($validated as $row) {
            $existing->execute(['category_id' => $row['category_id'], 'question_text' => $row['question_text']]);
            if ($existing->fetchColumn()) $errors[] = "Baris {$row['line']}: soal sudah ada pada kategori tersebut.";
        }
        if ($errors !== []) throw new ValidationException(['import' => implode(' ', $errors)]);
        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare('INSERT INTO questions (id,category_id,question_text,option_a,option_b,option_c,option_d,correct_option,is_active,created_by,created_at,updated_at) VALUES (:id,:category_id,:question_text,:option_a,:option_b,:option_c,:option_d,:correct_option,1,:actor_id,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            foreach ($validated as $row) {
                $id = $this->uuid();
                unset($row['line']);
                $insert->execute(['id' => $id, 'actor_id' => $actorId] + $row);
                $this->audit($actorId, 'exam.question.imported', 'question', $id);
            }
            $this->db->commit();
            return count($validated);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public static function templateRows(): array { return [self::HEADERS, ['TPA', 'Contoh soal', 'A', 'B', 'C', 'D', 'A']]; }
    private function audit(string $actorId, string $action, string $type, string $id): void
    {
        $this->db->prepare('INSERT INTO audit_logs (id,actor_type,actor_id,participant_id,action,target_type,target_id,request_id,created_at) VALUES (:id,"ADMIN",:actor_id,NULL,:action,:target_type,:target_id,:request_id,UTC_TIMESTAMP())')->execute(['id' => $this->uuid(), 'actor_id' => $actorId, 'action' => $action, 'target_type' => $type, 'target_id' => $id, 'request_id' => bin2hex(random_bytes(12))]);
    }
    private function uuid(): string { $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($bytes),4)); }
}
