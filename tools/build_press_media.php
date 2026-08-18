<?php

declare(strict_types=1);

function normalizedLabel(string $value): string
{
    $value = mb_strtoupper(trim($value), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

    return preg_replace('/[^A-Z0-9]+/', ' ', $ascii) ?: '';
}

function cleanedCell(string $value): string
{
    return preg_replace('/\s+/u', ' ', trim($value)) ?: '';
}

/**
 * @return list<string>
 */
function sharedStrings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }

    $document = simplexml_load_string($xml);
    if ($document === false) {
        throw new RuntimeException('Les textes partagés du classeur sont illisibles.');
    }

    $strings = [];
    foreach ($document->si as $item) {
        $value = isset($item->t) ? (string) $item->t : '';
        foreach ($item->r as $run) {
            $value .= (string) $run->t;
        }
        $strings[] = cleanedCell($value);
    }

    return $strings;
}

function sheetPath(ZipArchive $zip, string $expectedLabel): string
{
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false || $relationshipsXml === false) {
        throw new RuntimeException('La structure du classeur est incomplète.');
    }

    $workbook = simplexml_load_string($workbookXml);
    $relationships = simplexml_load_string($relationshipsXml);
    if ($workbook === false || $relationships === false) {
        throw new RuntimeException('La structure du classeur est illisible.');
    }

    $targets = [];
    foreach ($relationships->Relationship as $relationship) {
        $targets[(string) $relationship['Id']] = (string) $relationship['Target'];
    }

    $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $sheetTargets = [];
    $expected = normalizedLabel($expectedLabel);
    foreach ($workbook->sheets->sheet as $sheet) {
        $name = normalizedLabel((string) $sheet['name']);
        $relationshipId = (string) $sheet->attributes($relationshipNamespace)['id'];
        $target = $targets[$relationshipId] ?? null;
        if ($target === null) {
            continue;
        }

        $target = ltrim(str_replace('\\', '/', $target), '/');
        $sheetTargets[] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        if (str_contains($name, $expected)) {
            return end($sheetTargets);
        }
    }

    if (count($sheetTargets) === 1) {
        return $sheetTargets[0];
    }

    throw new RuntimeException(sprintf('La feuille « %s » est introuvable.', $expectedLabel));
}

/**
 * @return list<array<string, string>>
 */
function worksheetRows(ZipArchive $zip, string $path, array $strings): array
{
    $xml = $zip->getFromName($path);
    if ($xml === false) {
        throw new RuntimeException(sprintf('La feuille %s est illisible.', $path));
    }

    $sheet = simplexml_load_string($xml);
    if ($sheet === false) {
        throw new RuntimeException(sprintf('La feuille %s contient un XML invalide.', $path));
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $values = [];
        foreach ($row->c as $cell) {
            preg_match('/^[A-Z]+/', (string) $cell['r'], $columnMatch);
            $column = $columnMatch[0] ?? '';
            if ($column === '') {
                continue;
            }

            $type = (string) $cell['t'];
            $value = (string) $cell->v;
            if ($type === 's') {
                $value = $strings[(int) $value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($cell->is->t) ? (string) $cell->is->t : '';
                foreach ($cell->is->r as $run) {
                    $value .= (string) $run->t;
                }
            }

            $values[$column] = cleanedCell($value);
        }
        $rows[] = $values;
    }

    return $rows;
}

/**
 * @return list<array{company: string, name: string, type: string}>
 */
function pressMediaFromWorkbook(string $path, string $sheetLabel, string $type): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException(sprintf('Impossible d’ouvrir le classeur %s.', $path));
    }

    try {
        $rows = worksheetRows($zip, sheetPath($zip, $sheetLabel), sharedStrings($zip));
    } finally {
        $zip->close();
    }

    $headerIndex = null;
    foreach ($rows as $index => $row) {
        $companyHeader = normalizedLabel($row['B'] ?? '');
        $mediaHeader = normalizedLabel($row['C'] ?? '');
        if (str_contains($companyHeader, 'ENTREPRISE') && str_contains($mediaHeader, 'TITRE')) {
            $headerIndex = $index;
            break;
        }
    }

    if ($headerIndex === null) {
        throw new RuntimeException(sprintf('Les colonnes Entreprise et Titres sont introuvables dans %s.', $path));
    }

    $records = [];
    $lastCompany = '';
    foreach (array_slice($rows, $headerIndex + 1) as $row) {
        $company = cleanedCell($row['B'] ?? '');
        $name = cleanedCell($row['C'] ?? '');

        if ($company !== '') {
            $lastCompany = $company;
        }
        if ($name === '' || $lastCompany === '') {
            continue;
        }

        $records[] = [
            'company' => $lastCompany,
            'name' => $name,
            'type' => $type,
        ];
    }

    return $records;
}

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php tools/build_press_media.php <presse-ecrite.xlsx> <presse-numerique.xlsx> <sortie.ts>\n");
    exit(1);
}

[$script, $writtenWorkbook, $digitalWorkbook, $outputPath] = $argv;

$written = pressMediaFromWorkbook($writtenWorkbook, 'PRESSE ECRITE', 'Écrit');
$digital = pressMediaFromWorkbook($digitalWorkbook, 'PRESSE NUMERIQUE', 'Numérique');
$recordsByKey = [];
foreach (array_merge($written, $digital) as $record) {
    $key = normalizedLabel($record['company']) . '|' . normalizedLabel($record['name']) . '|' . $record['type'];
    $recordsByKey[$key] = $record;
}
$records = array_values($recordsByKey);

usort($records, static function (array $left, array $right): int {
    return [$left['company'], $left['name'], $left['type']] <=> [$right['company'], $right['name'], $right['type']];
});

$json = json_encode(
    $records,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
);
$typescript = <<<TS
export type PressMediaType = 'Écrit' | 'Numérique';

export interface PressMedia {
  company: string;
  name: string;
  type: PressMediaType;
}

export const PRESS_MEDIA: readonly PressMedia[] = {$json};

TS;

if (file_put_contents($outputPath, $typescript) === false) {
    throw new RuntimeException(sprintf('Impossible d’écrire le fichier %s.', $outputPath));
}

$uniqueCompanies = array_unique(array_column($records, 'company'));
printf(
    "%d titres écrits, %d titres numériques, %d entrées et %d entreprises générés.\n",
    count($written),
    count($digital),
    count($records),
    count($uniqueCompanies),
);
