<?php
declare(strict_types=1);

namespace App\Services;

final class ImportHeaderDictionary
{
    /** @var array<string,string> */
    private const LABELS = [
        'full_name' => 'Nama Lengkap',
        'email' => 'Email',
        'phone_number' => 'Nomor WhatsApp',
        'school_name' => 'Asal Sekolah',
        'graduation_year' => 'Tahun Lulus',
        'admission_path' => 'Jalur Masuk',
        'program_code' => 'Kode Program Studi',
        'wave_code' => 'Kode Gelombang',
        'category_code' => 'Kode Kategori',
        'question_text' => 'Pertanyaan',
        'option_a' => 'Opsi A',
        'option_b' => 'Opsi B',
        'option_c' => 'Opsi C',
        'option_d' => 'Opsi D',
        'correct_option' => 'Kunci Jawaban',
    ];

    public static function label(string $canonical): string
    {
        return self::LABELS[$canonical] ?? $canonical;
    }

    public static function canonical(string $header): string
    {
        $normalized = self::normalize($header);
        foreach (self::LABELS as $canonical => $label) {
            if ($normalized === self::normalize($canonical) || $normalized === self::normalize($label)) return $canonical;
        }
        return $normalized;
    }

    private static function normalize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower($value, 'UTF-8');
    }
}
