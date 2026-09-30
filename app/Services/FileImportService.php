<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;
use XMLReader;

final class FileImportService
{
    private const MAX_BYTES = 5_242_880;
    private const MAX_ROWS = 2_000;
    private const MAX_ROW_XML_BYTES = 1_000_000;
    private const MAX_SHARED_STRINGS = 50_000;

    /** @param array<string,mixed> $file @param list<string> $headers @return list<array<string,string>> */
    public function parseCsvUpload(array $file, array $headers): array
    {
        $this->validateUpload($file, ['csv']);
        $handle = fopen((string) $file['tmp_name'], 'rb');
        if ($handle === false) throw new ValidationException(['import' => 'Berkas CSV tidak dapat dibaca.']);
        try {
            $first = fgetcsv($handle);
            if ($first === false) throw new ValidationException(['import' => 'Berkas CSV tidak berisi header.']);
            $normalizedHeaders = $this->headers($first);
            $this->assertHeaders($normalizedHeaders, $headers);
            $rows = [];
            $line = 1;
            while (($cells = fgetcsv($handle)) !== false) {
                $line++;
                if ($cells === [null] || $cells === []) continue;
                if (count($cells) !== count($headers)) throw new ValidationException(['import' => "Baris {$line} memiliki jumlah kolom yang tidak sesuai."]);
                $row = array_combine($headers, array_map([$this, 'clean'], $cells));
                if (!is_array($row)) throw new ValidationException(['import' => "Baris {$line} tidak dapat diproses."]);
                $row['_line'] = (string) $line;
                $rows[] = $row;
                if (count($rows) > self::MAX_ROWS) throw new ValidationException(['import' => 'Maksimal 2.000 baris setiap impor.']);
            }
            if ($rows === []) throw new ValidationException(['import' => 'Berkas tidak memiliki data untuk diimpor.']);
            return $rows;
        } finally { fclose($handle); }
    }

    /** @param array<string,mixed> $file @param list<string> $headers @return list<array<string,string>> */
    public function parseXlsxUpload(array $file, array $headers): array
    {
        $this->validateUpload($file, ['xlsx']);
        if (!class_exists(ZipArchive::class)) throw new ValidationException(['import' => 'Ekstensi ZIP PHP belum aktif. Hubungi administrator server aplikasi.']);
        if (!class_exists(XMLReader::class)) throw new ValidationException(['import' => 'Ekstensi XMLReader PHP belum aktif. Hubungi administrator server aplikasi.']);
        $zip = new ZipArchive();
        if ($zip->open((string) $file['tmp_name']) !== true) throw new ValidationException(['import' => 'Berkas XLSX tidak valid atau rusak.']);
        try {
            if ($zip->numFiles > 10_000) throw new ValidationException(['import' => 'XLSX memiliki terlalu banyak bagian untuk diproses.']);
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $total += (int) ($stat['size'] ?? 0);
                if ($total > 25_000_000) throw new ValidationException(['import' => 'Isi XLSX terlalu besar untuk diproses.']);
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (!is_string($sheet)) throw new ValidationException(['import' => 'XLSX harus memiliki worksheet pertama.']);
            $strings = $this->sharedStrings($zip->getFromName('xl/sharedStrings.xml'));
            return $this->xlsxRows($sheet, $strings, $headers);
        } finally { $zip->close(); }
    }
    /**
     * @param list<list<string>> $rows
     * @param array<string,mixed> $options
     */
    public function xlsxTemplate(array $rows, string $sheetName, array $options = []): string
    {
        return (new XlsxTemplateBuilder())->build($rows, $sheetName, $options);
    }
    /** @param array<string,mixed> $file @param list<string> $extensions */
    private function validateUpload(array $file, array $extensions): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new ValidationException(['import' => 'Pilih berkas impor yang valid.']);
        if (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) throw new ValidationException(['import' => 'Berkas impor tidak diterima melalui formulir yang sah.']);
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) throw new ValidationException(['import' => 'Ukuran berkas harus antara 1 byte dan 5 MB.']);
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $extensions, true)) throw new ValidationException(['import' => 'Tipe berkas tidak didukung.']);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if ($extension === 'xlsx' && !in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'], true)) throw new ValidationException(['import' => 'Berkas XLSX memiliki tipe konten yang tidak sesuai.']);
        if ($extension === 'csv' && !in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'], true)) throw new ValidationException(['import' => 'Berkas CSV memiliki tipe konten yang tidak sesuai.']);
    }

    /** @param list<mixed> $row @return list<string> */
    private function headers(array $row): array { return array_map(fn ($value): string => ImportHeaderDictionary::canonical($this->clean((string) $value)), $row); }
    /** @param list<string> $actual @param list<string> $expected */
    private function assertHeaders(array $actual, array $expected): void
    {
        $expected = array_map(static fn (string $header): string => ImportHeaderDictionary::canonical($header), $expected);
        if ($actual !== $expected) {
            $labels = array_map(static fn (string $header): string => ImportHeaderDictionary::label($header), $expected);
            throw new ValidationException(['import' => 'Header berkas harus: ' . implode(', ', $labels) . '.']);
        }
    }
    private function clean(string $value): string { return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value); }

    /** @return list<string> */
    private function sharedStrings(string|false $xml): array
    {
        if (!is_string($xml) || $xml === '') return [];
        $reader = new XMLReader();
        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $strings = [];
        try {
            if (!$reader->XML($xml, null, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT)) throw new ValidationException(['import' => 'Daftar teks XLSX tidak valid.']);
            try {
                while ($reader->read()) {
                    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') continue;
                    if (count($strings) >= self::MAX_SHARED_STRINGS) throw new ValidationException(['import' => 'XLSX memiliki terlalu banyak teks bersama untuk diproses.']);
                    $itemXml = $reader->readOuterXml();
                    if ($itemXml === '' || strlen($itemXml) > self::MAX_ROW_XML_BYTES) throw new ValidationException(['import' => 'Daftar teks XLSX terlalu besar atau tidak valid.']);
                    $item = simplexml_load_string($itemXml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
                    if ($item === false) throw new ValidationException(['import' => 'Daftar teks XLSX tidak valid.']);
                    $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $nodes = $item->xpath('.//m:t') ?: [];
                    $strings[] = trim(implode('', array_map(static fn (\SimpleXMLElement $node): string => (string) $node, $nodes)));
                }
            } finally {
                $reader->close();
            }
            if (libxml_get_errors() !== []) throw new ValidationException(['import' => 'Daftar teks XLSX tidak valid.']);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
        return $strings;
    }

    /** @param list<string> $strings @param list<string> $headers @return list<array<string,string>> */
    private function xlsxRows(string $xml, array $strings, array $headers): array
    {
        $reader = new XMLReader();
        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $rows = [];
        $physicalRows = 0;
        try {
            if (!$reader->XML($xml, null, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT)) throw new ValidationException(['import' => 'Worksheet XLSX tidak valid.']);
            try {
                while ($reader->read()) {
                    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') continue;
                    $physicalRows++;
                    if ($physicalRows > self::MAX_ROWS + 1) throw new ValidationException(['import' => 'Maksimal 2.000 baris setiap impor.']);
                    $rowNumber = $reader->getAttribute('r');
                    $line = is_string($rowNumber) && ctype_digit($rowNumber) ? (int) $rowNumber : $physicalRows;
                    $rowXml = $reader->readOuterXml();
                    if ($rowXml === '' || strlen($rowXml) > self::MAX_ROW_XML_BYTES) throw new ValidationException(['import' => "Baris {$line} terlalu besar atau tidak valid."]);
                    $cells = $this->xlsxRowCells($rowXml, $strings, count($headers), $line);
                    if ($physicalRows === 1) {
                        $this->assertHeaders($this->headers(array_values($cells)), $headers);
                        continue;
                    }
                    $values = [];
                    for ($column = 0; $column < count($headers); $column++) $values[] = $cells[$column] ?? '';
                    if (count(array_filter($values, static fn (string $value): bool => $value !== '')) === 0) continue;
                    $row = array_combine($headers, $values);
                    if (!is_array($row)) throw new RuntimeException('Berkas XLSX tidak dapat dipetakan.');
                    $row['_line'] = (string) $line;
                    $rows[] = $row;
                }
            } finally {
                $reader->close();
            }
            if (libxml_get_errors() !== []) throw new ValidationException(['import' => 'Worksheet XLSX tidak valid.']);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
        if ($physicalRows === 0) throw new ValidationException(['import' => 'XLSX tidak berisi header.']);
        if ($rows === []) throw new ValidationException(['import' => 'Berkas tidak memiliki data untuk diimpor.']);
        return $rows;
    }

    /** @param list<string> $strings @return array<int,string> */
    private function xlsxRowCells(string $xml, array $strings, int $maxColumns, int $line): array
    {
        $xmlRow = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xmlRow === false) throw new ValidationException(['import' => "Baris {$line} tidak valid."]);
        $xmlRow->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = [];
        foreach (($xmlRow->xpath('./m:c') ?: []) as $cell) {
            $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $reference = strtoupper((string) ($cell['r'] ?? 'A1'));
            if (!preg_match('/^([A-Z]{1,3})[1-9][0-9]*$/', $reference, $match)) throw new ValidationException(['import' => "Baris {$line} memiliki referensi sel yang tidak valid."]);
            $column = $this->columnIndex($match[1]);
            if ($column >= $maxColumns) throw new ValidationException(['import' => "Baris {$line} memiliki kolom berlebih."]);
            $type = (string) ($cell['t'] ?? '');
            $valueNodes = $cell->xpath('./m:v') ?: [];
            $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';
            if ($type === 's') $value = $strings[(int) $value] ?? '';
            if ($type === 'inlineStr') {
                $inlineNodes = $cell->xpath('.//m:is//m:t') ?: [];
                $value = implode('', array_map(static fn (\SimpleXMLElement $node): string => (string) $node, $inlineNodes));
            }
            $cells[$column] = $this->clean($value);
        }
        ksort($cells);
        return $cells;
    }

    private function columnIndex(string $letters): int
    {
        $value = 0;
        foreach (str_split($letters) as $letter) $value = ($value * 26) + (ord($letter) - 64);
        return $value - 1;
    }
}
