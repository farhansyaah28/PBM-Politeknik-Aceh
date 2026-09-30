<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class XlsxTemplateBuilder
{
    private const MAX_ROWS = 2_000;

    /** @var array<string,string> */
    private array $definedNames = [];

    /**
     * @param list<list<string>> $rows
     * @param array{
     *   title?:string,
     *   summary?:string,
     *   steps?:list<string>,
     *   references?:array<string,array{title?:string,items?:list<array{code?:string,name?:string,0?:string,1?:string}>}>
     * } $options
     */
    public function build(array $rows, string $sheetName, array $options = []): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Ekstensi ZIP PHP belum aktif. Hubungi administrator server aplikasi.');
        $headers = array_map('strval', array_values($rows[0] ?? []));
        if ($headers === []) throw new RuntimeException('Template XLSX memerlukan header.');
        $example = array_map('strval', array_values($rows[1] ?? array_fill(0, count($headers), '')));
        if (count($example) < count($headers)) $example = array_pad($example, count($headers), '');

        $profile = $this->profile($headers, $sheetName, $example, $options);
        $this->definedNames = [];
        $guideSheet = $this->guideSheet($profile);
        $dataSheet = $this->dataSheet($headers, $profile);
        $path = tempnam(sys_get_temp_dir(), 'pmb-template-');
        if ($path === false) throw new RuntimeException('Template XLSX tidak dapat dibuat.');

        $zip = new ZipArchive();
        $isOpen = false;
        try {
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Template XLSX tidak dapat dibuat.');
            $isOpen = true;
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', $this->workbook($sheetName));
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', $dataSheet);
            $zip->addFromString('xl/worksheets/sheet2.xml', $guideSheet);
            $zip->close();
            $isOpen = false;
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) throw new RuntimeException('Template XLSX tidak dapat dibaca.');
            return $bytes;
        } finally {
            if ($isOpen) $zip->close();
            if (is_file($path)) @unlink($path);
        }
    }

    /** @param list<string> $headers @param list<string> $example @param array<string,mixed> $options @return array<string,mixed> */
    private function profile(array $headers, string $sheetName, array $example, array $options): array
    {
        $fieldHelp = [
            'full_name' => ['description'=>'Nama lengkap peserta sesuai identitas.','width'=>28,'style'=>1],
            'email' => ['description'=>'Email unik dan aktif. Satu email hanya untuk satu peserta.','width'=>34,'style'=>1],
            'phone_number' => ['description'=>'Gunakan angka dengan nol di depan, misalnya 081234567890.','width'=>20,'style'=>3],
            'school_name' => ['description'=>'Nama sekolah asal peserta.','width'=>30,'style'=>1],
            'graduation_year' => ['description'=>'Empat digit tahun antara 1950 dan 2100.','width'=>18,'style'=>2],
            'admission_path' => ['description'=>'Pilih jalur masuk peserta dari dropdown.','width'=>22,'style'=>2],
            'program_code' => ['description'=>'Pilih kode program studi aktif dari dropdown.','width'=>20,'style'=>2],
            'wave_code' => ['description'=>'Pilih kode gelombang aktif dari dropdown.','width'=>20,'style'=>2],
            'category_code' => ['description'=>'Pilih kode kategori soal aktif dari dropdown.','width'=>20,'style'=>2],
            'question_text' => ['description'=>'Tulis pertanyaan lengkap. Hindari soal yang sama dalam kategori yang sama.','width'=>52,'style'=>1],
            'option_a' => ['description'=>'Isi pilihan jawaban A.','width'=>24,'style'=>1],
            'option_b' => ['description'=>'Isi pilihan jawaban B.','width'=>24,'style'=>1],
            'option_c' => ['description'=>'Isi pilihan jawaban C.','width'=>24,'style'=>1],
            'option_d' => ['description'=>'Isi pilihan jawaban D.','width'=>24,'style'=>1],
            'correct_option' => ['description'=>'Pilih A, B, C, atau D dari dropdown.','width'=>18,'style'=>2],
        ];
        $fields = [];
        foreach ($headers as $index => $header) {
            $help = $fieldHelp[$header] ?? ['description'=>'Isi sesuai kebutuhan impor.','width'=>22,'style'=>1];
            $exampleValue = $example[$index] ?? '';
            if ($header === 'phone_number' && $exampleValue !== '') $exampleValue .= ' (teks)';
            $fields[] = ['name'=>$header,'label'=>ImportHeaderDictionary::label($header),'description'=>$help['description'],'width'=>$help['width'],'style'=>$help['style'],'example'=>$exampleValue];
        }

        $steps = array_values(array_filter(array_map('strval', $options['steps'] ?? []), static fn (string $value): bool => trim($value) !== ''));
        if ($steps === []) $steps = [
            'Buka sheet ' . $sheetName . ' dan mulai isi data pada baris 2.',
            'Isi satu data per baris. Jangan mengubah nama, urutan, atau jumlah kolom.',
            'Gunakan dropdown pada kolom berkode agar nilai sesuai konfigurasi aktif.',
            'Biarkan baris yang tidak digunakan tetap kosong.',
            'Simpan sebagai XLSX. Maksimal 2.000 baris dan ukuran file 5 MB.',
        ];

        $references = [];
        foreach (($options['references'] ?? []) as $header => $reference) {
            if (!is_string($header) || !in_array($header, $headers, true) || !is_array($reference)) continue;
            $items = [];
            foreach (($reference['items'] ?? []) as $item) {
                if (!is_array($item)) continue;
                $code = trim((string) ($item['code'] ?? $item[0] ?? ''));
                $name = trim((string) ($item['name'] ?? $item[1] ?? ''));
                if ($code !== '') $items[] = ['code'=>$code,'name'=>$name];
            }
            $references[$header] = ['title'=>(string) ($reference['title'] ?? $header),'items'=>$items];
        }
        if (in_array('correct_option', $headers, true) && !isset($references['correct_option'])) {
            $references['correct_option'] = ['title'=>'Pilihan jawaban benar','items'=>[['code'=>'A','name'=>'Opsi A'],['code'=>'B','name'=>'Opsi B'],['code'=>'C','name'=>'Opsi C'],['code'=>'D','name'=>'Opsi D']]];
        }

        return [
            'sheet_name'=>$sheetName,
            'title'=>(string) ($options['title'] ?? 'Template Impor ' . $sheetName),
            'summary'=>(string) ($options['summary'] ?? 'Isi data pada sheet ' . $sheetName . '. Baca petunjuk sebelum mengunggah.'),
            'steps'=>$steps,
            'fields'=>$fields,
            'references'=>$references,
        ];
    }

    /** @param list<string> $headers @param array<string,mixed> $profile */
    private function dataSheet(array $headers, array $profile): string
    {
        $lastColumn = $this->columnLetters(count($headers) - 1);
        $columns = '';
        foreach ($profile['fields'] as $index => $field) {
            $column = $index + 1;
            $columns .= '<col min="' . $column . '" max="' . $column . '" width="' . (int) $field['width'] . '" customWidth="1"/>';
        }
        $headerCells = '';
        foreach ($profile['fields'] as $index => $field) $headerCells .= $this->inlineCell($this->columnLetters($index) . '1', (string) $field['label'], 4);
        $rows = '<row r="1" ht="34" customHeight="1">' . $headerCells . '</row>';
        for ($row = 2; $row <= self::MAX_ROWS + 1; $row++) {
            $cells = '';
            foreach ($profile['fields'] as $index => $field) $cells .= '<c r="' . $this->columnLetters($index) . $row . '" s="' . (int) $field['style'] . '"/>';
            $rows .= '<row r="' . $row . '" ht="22" customHeight="1">' . $cells . '</row>';
        }

        $validations = [];
        foreach ($profile['references'] as $header => $reference) {
            $column = array_search($header, $headers, true);
            $name = $this->definedName((string) $header);
            if ($column === false || ($reference['items'] ?? []) === [] || !isset($this->definedNames[$name])) continue;
            $letter = $this->columnLetters((int) $column);
            $range = $letter . '2:' . $letter . (self::MAX_ROWS + 1);
            $validations[] = '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" promptTitle="Pilih kode" prompt="Gunakan nilai dari daftar yang tersedia." errorTitle="Nilai tidak valid" error="Pilih nilai dari dropdown." sqref="' . $range . '"><formula1>' . $this->xml($name) . '</formula1></dataValidation>';
        }
        $yearColumn = array_search('graduation_year', $headers, true);
        if ($yearColumn !== false) {
            $letter = $this->columnLetters((int) $yearColumn);
            $range = $letter . '2:' . $letter . (self::MAX_ROWS + 1);
            $validations[] = '<dataValidation type="whole" operator="between" allowBlank="1" showInputMessage="1" showErrorMessage="1" promptTitle="Tahun lulus" prompt="Isi empat digit tahun, misalnya 2026." errorTitle="Tahun tidak valid" error="Gunakan tahun antara 1950 dan 2100." sqref="' . $range . '"><formula1>1950</formula1><formula2>2100</formula2></dataValidation>';
        }
        $validationXml = $validations === [] ? '' : '<dataValidations count="' . count($validations) . '">' . implode('', $validations) . '</dataValidations>';

        $emailColumn = array_search('email', $headers, true);
        $duplicateRule = '';
        if ($emailColumn !== false) {
            $letter = $this->columnLetters((int) $emailColumn);
            $duplicateRule = '<conditionalFormatting sqref="' . $letter . '2:' . $letter . (self::MAX_ROWS + 1) . '"><cfRule type="expression" dxfId="0" priority="2"><formula>AND(' . $letter . '2&lt;&gt;&quot;&quot;,COUNTIF($' . $letter . '$2:$' . $letter . '$' . (self::MAX_ROWS + 1) . ',' . $letter . '2)&gt;1)</formula></cfRule></conditionalFormatting>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><tabColor rgb="FF0B5961"/><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:' . $lastColumn . (self::MAX_ROWS + 1) . '"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="22"/><cols>' . $columns . '</cols><sheetData>' . $rows . '</sheetData><autoFilter ref="A1:' . $lastColumn . (self::MAX_ROWS + 1) . '"/><conditionalFormatting sqref="A2:' . $lastColumn . (self::MAX_ROWS + 1) . '"><cfRule type="expression" dxfId="0" priority="1"><formula>AND(COUNTA($A2:$' . $lastColumn . '2)&gt;0,A2=&quot;&quot;)</formula></cfRule></conditionalFormatting>' . $duplicateRule . $validationXml . '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    /** @param array<string,mixed> $profile */
    private function guideSheet(array $profile): string
    {
        $cells = [];
        $heights = [1=>10,2=>28,3=>34,5=>24,12=>24,13=>30];
        $merges = ['A2:G2','A3:G3','A5:D5','A12:D12'];
        $add = static function (int $row, string $cell) use (&$cells): void { $cells[$row][] = $cell; };
        $height = static function (int $row, int $value) use (&$heights): void { $heights[$row] = max($heights[$row] ?? 0, $value); };

        $add(2, $this->inlineCell('A2', (string) $profile['title'], 5));
        $add(3, $this->inlineCell('A3', (string) $profile['summary'], 6));
        $add(5, $this->inlineCell('A5', 'Cara menggunakan', 7));
        foreach (array_slice($profile['steps'], 0, 5) as $index => $step) {
            $row = 6 + $index;
            $merges[] = 'A' . $row . ':D' . $row;
            $height($row, 23);
            $add($row, $this->inlineCell('A' . $row, ($index + 1) . '. ' . $step, 12));
        }
        $add(12, $this->inlineCell('A12', 'Panduan kolom', 7));
        $add(13, $this->inlineCell('A13', 'Kolom', 8));
        $add(13, $this->inlineCell('B13', 'Wajib', 8));
        $add(13, $this->inlineCell('C13', 'Cara mengisi', 8));
        $add(13, $this->inlineCell('D13', 'Contoh', 8));
        foreach ($profile['fields'] as $index => $field) {
            $row = 14 + $index;
            $height($row, 38);
            $add($row, $this->inlineCell('A' . $row, (string) $field['label'], 9));
            $add($row, $this->inlineCell('B' . $row, 'Ya', 10));
            $add($row, $this->inlineCell('C' . $row, (string) $field['description'], 9));
            $add($row, $this->inlineCell('D' . $row, (string) $field['example'], 9));
        }

        $referenceRow = 5;
        foreach ($profile['references'] as $header => $reference) {
            $items = $reference['items'] ?? [];
            $merges[] = 'F' . $referenceRow . ':G' . $referenceRow;
            $height($referenceRow, 24);
            $add($referenceRow, $this->inlineCell('F' . $referenceRow, (string) ($reference['title'] ?? $header), 7));
            $headerRow = $referenceRow + 1;
            $height($headerRow, 26);
            $add($headerRow, $this->inlineCell('F' . $headerRow, 'Kode', 8));
            $add($headerRow, $this->inlineCell('G' . $headerRow, 'Nama', 8));
            if ($items === []) {
                $emptyRow = $referenceRow + 2;
                $height($emptyRow, 30);
                $add($emptyRow, $this->inlineCell('F' . $emptyRow, 'Belum ada', 11));
                $add($emptyRow, $this->inlineCell('G' . $emptyRow, 'Aktifkan konfigurasi terlebih dahulu.', 11));
                $referenceRow += 5;
                continue;
            }
            $firstItemRow = $referenceRow + 2;
            foreach ($items as $index => $item) {
                $row = $firstItemRow + $index;
                $height($row, 24);
                $add($row, $this->inlineCell('F' . $row, (string) $item['code'], 10));
                $add($row, $this->inlineCell('G' . $row, (string) $item['name'], 9));
            }
            $lastItemRow = $firstItemRow + count($items) - 1;
            $this->definedNames[$this->definedName((string) $header)] = "'Petunjuk'!\$F\$" . $firstItemRow . ':$F$' . $lastItemRow;
            $referenceRow = $lastItemRow + 3;
        }

        $allRows = array_unique(array_merge(array_keys($cells), array_keys($heights)));
        sort($allRows);
        $rowXml = '';
        foreach ($allRows as $row) $rowXml .= '<row r="' . $row . '" ht="' . ($heights[$row] ?? 22) . '" customHeight="1">' . implode('', $cells[$row] ?? []) . '</row>';
        $lastRow = max($allRows);
        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><tabColor rgb="FF78AEB3"/></sheetPr><dimension ref="A1:G' . $lastRow . '"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews><sheetFormatPr defaultRowHeight="22"/><cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="11" customWidth="1"/><col min="3" max="3" width="54" customWidth="1"/><col min="4" max="4" width="30" customWidth="1"/><col min="5" max="5" width="4" customWidth="1"/><col min="6" max="6" width="18" customWidth="1"/><col min="7" max="7" width="36" customWidth="1"/></cols><sheetData>' . $rowXml . '</sheetData><mergeCells count="' . count($merges) . '">' . implode('', array_map(static fn (string $range): string => '<mergeCell ref="' . $range . '"/>', $merges)) . '</mergeCells><pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';
    }

    private function workbook(string $sheetName): string
    {
        $names = '';
        foreach ($this->definedNames as $name => $reference) $names .= '<definedName name="' . $this->xml($name) . '">' . $this->xml($reference) . '</definedName>';
        return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="' . $this->xml($sheetName) . '" sheetId="1" r:id="rId1"/><sheet name="Petunjuk" sheetId="2" r:id="rId2"/></sheets>' . ($names === '' ? '' : '<definedNames>' . $names . '</definedNames>') . '<calcPr calcId="191029"/></workbook>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="5"><font><sz val="10"/><color rgb="FF17212B"/><name val="Arial"/><family val="2"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font><font><b/><sz val="15"/><color rgb="FF0B5961"/><name val="Arial"/><family val="2"/></font><font><i/><sz val="10"/><color rgb="FF52616B"/><name val="Arial"/><family val="2"/></font><font><b/><sz val="10"/><color rgb="FF92400E"/><name val="Arial"/><family val="2"/></font></fonts><fills count="8"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B5961"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF8DC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2C7A7B"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE6FFFA"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFD9E2E7"/></bottom><diagonal/></border><border><left style="thin"><color rgb="FFFFFFFF"/></left><right style="thin"><color rgb="FFFFFFFF"/></right><top style="thin"><color rgb="FFFFFFFF"/></top><bottom style="thin"><color rgb="FFFFFFFF"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="13"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="49" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="4" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="1"><dxf><font><b/><color rgb="FF991B1B"/></font><fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor indexed="64"/></patternFill></fill></dxf></dxfs><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/></styleSheet>';
    }

    private function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t>' . $this->xml($value) . '</t></is></c>';
    }

    private function definedName(string $header): string
    {
        return 'List_' . preg_replace('/[^A-Za-z0-9_]/', '_', $header);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function columnLetters(int $index): string
    {
        $letters = '';
        for ($value = $index + 1; $value > 0; $value = intdiv($value - 1, 26)) $letters = chr(65 + (($value - 1) % 26)) . $letters;
        return $letters;
    }
}
