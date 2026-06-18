<?php

function powerbiXmlEscape($value) {
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function powerbiColumnLetter($index) {
    $index = intval($index);
    $letter = '';
    while ($index >= 0) {
        $letter = chr(65 + ($index % 26)) . $letter;
        $index = intdiv($index, 26) - 1;
    }
    return $letter;
}

function powerbiBuildSheetXml($rows, $columns) {
    $sheetRows = array();
    $sheetRows[] = '<row r="1">';
    foreach ($columns as $colIndex => $colName) {
        $cellRef = powerbiColumnLetter($colIndex) . '1';
        $sheetRows[] = '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . powerbiXmlEscape($colName) . '</t></is></c>';
    }
    $sheetRows[] = '</row>';

    $rowNum = 2;
    foreach ($rows as $row) {
        $sheetRows[] = '<row r="' . $rowNum . '">';
        foreach ($columns as $colIndex => $colName) {
            $cellRef = powerbiColumnLetter($colIndex) . $rowNum;
            $value = $row[$colName] ?? '';
            if (is_numeric($value) && $value !== '' && !preg_match('/^0\d/', (string) $value)) {
                $sheetRows[] = '<c r="' . $cellRef . '"><v>' . powerbiXmlEscape($value) . '</v></c>';
            } else {
                $sheetRows[] = '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . powerbiXmlEscape($value) . '</t></is></c>';
            }
        }
        $sheetRows[] = '</row>';
        $rowNum++;
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '</worksheet>';
}

function powerbiExportWorkbook($datasets) {
    if (!class_exists('ZipArchive')) {
        throw new Exception('PHP ZipArchive extension is required for Excel export.');
    }

    $tmp = tempnam(sys_get_temp_dir(), 'pbi_');
    if ($tmp === false) {
        throw new Exception('Could not create temporary export file.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Could not create Excel workbook.');
    }

    $sheetNames = array();
    $sheetIndex = 1;
    foreach ($datasets as $sheetName => $payload) {
        $safeName = preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $sheetName);
        if ($safeName === '') {
            $safeName = 'Sheet' . $sheetIndex;
        }
        $sheetNames[] = $safeName;
        $zip->addFromString('xl/worksheets/sheet' . $sheetIndex . '.xml', powerbiBuildSheetXml($payload['rows'], $payload['columns']));
        $sheetIndex++;
    }

    $workbookSheets = array();
    foreach ($sheetNames as $i => $name) {
        $id = $i + 1;
        $workbookSheets[] = '<sheet name="' . powerbiXmlEscape(substr($name, 0, 31)) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
    }

    $workbookRels = array();
    foreach ($sheetNames as $i => $name) {
        $id = $i + 1;
        $workbookRels[] = '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
    }

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . implode('', array_map(function ($i) {
            return '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }, range(1, count($sheetNames))))
        . '</Types>'
    );

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>'
    );

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . implode('', $workbookRels)
        . '</Relationships>'
    );

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . implode('', $workbookSheets) . '</sheets>'
        . '</workbook>'
    );

    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"></styleSheet>'
    );

    $zip->close();
    return $tmp;
}

function powerbiBuildAllDatasets($conn) {
    $resources = array(
        'Orders' => 'orders',
        'Order Lines' => 'order_lines',
        'Menu' => 'menu',
        'Reviews' => 'reviews',
        'Daily' => 'daily',
        'Calendar' => 'calendar',
    );

    $datasets = array();
    foreach ($resources as $label => $resource) {
        $datasets[$label] = array(
            'columns' => powerbiDatasetColumns($resource),
            'rows' => powerbiDatasetRows($conn, $resource),
        );
    }
    return $datasets;
}
