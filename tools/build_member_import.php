<?php

function normalizeText(?string $value): string
{
    $value = mb_strtoupper(trim((string) $value), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return preg_replace('/[^A-Z0-9]+/', '', $ascii);
}

function sqlValue(?string $value): string
{
    return $value === null || $value === '' ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
}

function workbookRows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Classeur illisible');
    $strings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        foreach (simplexml_load_string($sharedXml)->si as $item) {
            $text = isset($item->t) ? (string) $item->t : '';
            foreach ($item->r as $run) $text .= (string) $run->t;
            $strings[] = trim($text);
        }
    }
    $result = [];
    $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
    foreach ($sheet->sheetData->row as $row) {
        if ((int) $row['r'] === 1) continue;
        $values = [];
        foreach ($row->c as $cell) {
            preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
            $value = (string) $cell->v;
            if ((string) $cell['t'] === 's') $value = $strings[(int) $value] ?? '';
            elseif ((string) $cell['t'] === 'inlineStr') $value = (string) $cell->is->t;
            $values[$match[0] ?? ''] = preg_replace('/\s+/u', ' ', trim($value));
        }
        if (($values['B'] ?? '') !== '') $result[] = $values;
    }
    $zip->close();
    return $result;
}

[$xlsxPath, $directoryPath, $sqlPath, $reportPath] = array_slice($argv, 1);
$directorySource = file_get_contents($directoryPath);
preg_match('/=\s*(\[.*\]);\s*$/s', $directorySource, $directoryMatch);
$directory = json_decode($directoryMatch[1] ?? '[]', true, flags: JSON_THROW_ON_ERROR);
$byMedia = [];
foreach ($directory as $item) $byMedia[normalizeText($item['name'])][] = $item;

$rows = workbookRows($xlsxPath);
$memberCounts = [];
$pressCounts = [];
foreach ($rows as $row) {
    $pressRaw = mb_strtoupper(preg_replace('/\s+/u', '', $row['D'] ?? ''), 'UTF-8');
    $normalizedPress = preg_match('/^(\d{1,4})JP$/', $pressRaw, $match)
        ? str_pad($match[1], 4, '0', STR_PAD_LEFT) . 'JP'
        : $pressRaw;
    $normalizedMember = mb_strtoupper(preg_replace('/\s+/u', '', $row['E'] ?? ''), 'UTF-8');
    if ($normalizedPress !== '') $pressCounts[$normalizedPress] = ($pressCounts[$normalizedPress] ?? 0) + 1;
    if ($normalizedMember !== '') $memberCounts[$normalizedMember] = ($memberCounts[$normalizedMember] ?? 0) + 1;
}
$report = [];
$valuesSql = [];
$stats = ['matched' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'invalid_cijp' => 0, 'invalid_member' => 0, 'duplicate_rows' => 0, 'ready_to_import' => 0, 'excluded' => 0];

foreach ($rows as $index => $row) {
    $fullName = trim($row['B'] ?? '');
    $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lastName = array_shift($parts) ?: null;
    $firstNames = $parts ? implode(' ', $parts) : null;
    $media = trim($row['C'] ?? '');
    $pressRaw = mb_strtoupper(preg_replace('/\s+/u', '', $row['D'] ?? ''), 'UTF-8');
    $press = preg_match('/^(\d{1,4})JP$/', $pressRaw, $match)
        ? str_pad($match[1], 4, '0', STR_PAD_LEFT) . 'JP'
        : $pressRaw;
    $member = mb_strtoupper(preg_replace('/\s+/u', '', $row['E'] ?? ''), 'UTF-8');

    $candidates = $byMedia[normalizeText($media)] ?? [];
    if (!$candidates && mb_strlen(normalizeText($media)) >= 6) {
        foreach ($byMedia as $key => $items) {
            if (str_contains($key, normalizeText($media)) || str_contains(normalizeText($media), $key)) {
                $candidates = array_merge($candidates, $items);
            }
        }
    }
    $companies = array_values(array_unique(array_column($candidates, 'company')));
    $types = array_values(array_unique(array_column($candidates, 'type')));
    $company = count($companies) === 1 ? $companies[0] : null;
    $type = count($types) === 1 ? $types[0] : null;
    $status = !$candidates ? 'unmatched' : (($company && $type) ? 'matched' : 'ambiguous');
    $stats[$status]++;

    $issues = [];
    if (!preg_match('/^\d{4}JP$/', $press)) { $issues[] = 'CIJP invalide'; $stats['invalid_cijp']++; }
    if (!preg_match('/^UJ\d{2}-\d{5}$/', $member)) { $issues[] = 'Numéro UNJCI invalide'; $stats['invalid_member']++; }
    $isDuplicate = false;
    if (($memberCounts[$member] ?? 0) > 1) { $issues[] = 'Numéro UNJCI présent sur plusieurs lignes'; $isDuplicate = true; }
    if (($pressCounts[$press] ?? 0) > 1) { $issues[] = 'CIJP présent sur plusieurs lignes'; $isDuplicate = true; }
    if ($isDuplicate) $stats['duplicate_rows']++;

    $ready = !$issues;
    $stats[$ready ? 'ready_to_import' : 'excluded']++;

    $report[] = [$index + 2, $fullName, $media, $press, $member, $company, $type, $status, $ready ? 'PRÊT' : 'EXCLU', implode(' | ', $issues)];
    if ($ready) {
        $valuesSql[] = '(' . implode(', ', [
            sqlValue($fullName), sqlValue($lastName), sqlValue($firstNames), sqlValue($media),
            sqlValue($company), sqlValue($type), sqlValue($press), sqlValue($member), sqlValue($status),
            'NOW()', 'NOW()',
        ]) . ')';
    }
}

$sql = "-- Import généré depuis liste_journalistes_a_jour.xlsx\n";
$sql .= "-- Exécuter member-import-manual.sql avant ce fichier.\n";
$sql .= "-- Lignes prêtes : {$stats['ready_to_import']} ; lignes exclues et documentées dans le rapport CSV : {$stats['excluded']}.\n";
$sql .= "START TRANSACTION;\n\n";
$sql .= "INSERT INTO `preloaded_members` (`full_name`, `suggested_last_name`, `suggested_first_names`, `media_name`, `company_name`, `media_type`, `press_card_number`, `member_number`, `mapping_status`, `created_at`, `updated_at`) VALUES\n";
$sql .= implode(",\n", $valuesSql);
$sql .= "\nON DUPLICATE KEY UPDATE\n  `full_name` = VALUES(`full_name`),\n  `suggested_last_name` = VALUES(`suggested_last_name`),\n  `suggested_first_names` = VALUES(`suggested_first_names`),\n  `media_name` = VALUES(`media_name`),\n  `company_name` = VALUES(`company_name`),\n  `media_type` = VALUES(`media_type`),\n  `mapping_status` = VALUES(`mapping_status`),\n  `updated_at` = NOW();\n\nCOMMIT;\n";
file_put_contents($sqlPath, $sql);

$handle = fopen($reportPath, 'wb');
fwrite($handle, "\xEF\xBB\xBF");
fputcsv($handle, ['Ligne Excel', 'Nom complet', 'Média', 'CIJP normalisé', 'Numéro UNJCI', 'Entreprise proposée', 'Type proposé', 'Rapprochement', 'Import', 'Anomalies'], ';');
foreach ($report as $line) fputcsv($handle, $line, ';');
fclose($handle);

echo json_encode(['rows' => count($rows), 'stats' => $stats], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
