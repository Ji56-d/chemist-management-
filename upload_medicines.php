<?php
/**
 * upload_medicines.php
 * Professional bulk medicine importer - FIXED DUPLICATE HANDLING
 * Properly imports CSV files with correct column mapping
 * Now handles duplicates by skipping them and reporting
 */

declare(strict_types=1);

require_once 'config/db.php';
if (!isAdmin()) {
    redirect('dashboard.php');
}

set_time_limit(0);

// -----------------------------------------------------------------------------
// 1. Configuration & constants
// -----------------------------------------------------------------------------
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024 * 1024);
define('DEFAULT_CATEGORY', 'General');
define('DEFAULT_UNIT', 'Tablet');
define('DEFAULT_STOCK', 100);
define('DEFAULT_BATCH', 'CSV-UPLOAD');
define('DEFAULT_SUPPLIER', 'CSV Import');
define('MAX_STOCK', 999999999);

// Field mapping for column detection
const FIELD_MAPPING = [
    'medicine_name' => ['medicine name', 'medicine', 'drug', 'product', 'item', 'name', 'medication', 'generic name', 'brand name', 'medname', 'product name', 'drug name'],
    'category' => ['category', 'type', 'class', 'group', 'classification'],
    'unit_type' => ['unit', 'unit type', 'form', 'dosage form', 'pack', 'strength', 'uom', 'unit of measure', 'unit type'],
    'stock_quantity' => ['stock', 'quantity', 'qty', 'stock quantity', 'available', 'on hand', 'qty in stock', 'in stock', 'stock quantity'],
    'cost_price' => ['cost', 'cost price', 'purchase price', 'buying price', 'wholesale', 'unit cost', 'buy price', 'cost price'],
    'selling_price' => ['selling price', 'sale price', 'price', 'retail price', 'mrp', 'sell price', 'sales price', 'unit price', 'selling price'],
    'batch_no' => ['batch', 'batch no', 'lot', 'lot number', 'batch number', 'batch code', 'lot no', 'batch no'],
    'supplier' => ['supplier', 'vendor', 'manufacturer', 'distributor', 'supplier name'],
    'expiry_date' => ['expiry', 'expiry date', 'expiration', 'exp date', 'valid till', 'expiration date', 'expires'],
    'barcode' => ['barcode', 'bar code', 'ean', 'upc', 'sku', 'barcode number', 'code']
];

// -----------------------------------------------------------------------------
// 2. Helper functions
// -----------------------------------------------------------------------------

function sanitizeNumeric($value): float
{
    if ($value === null || trim((string)$value) === '') return 0.0;
    $value = trim((string)$value);
    $negative = preg_match('/^\(.*\)$/', $value) === 1;
    $value = trim($value, " \t\n\r\0\x0B()");
    $value = preg_replace('/[A-Za-z$€£¥₦]+/u', '', $value);
    $value = str_replace(',', '', $value);
    $value = preg_replace('/[^0-9.\-]/', '', $value);
    if ($value === '' || $value === '-' || $value === '.') return 0.0;
    $number = (float)$value;
    return $negative ? -abs($number) : $number;
}

function sanitizeInteger($value): int
{
    if ($value === null || trim((string)$value) === '') return 0;
    $value = trim((string)$value);
    $negative = strpos($value, '-') === 0;
    $clean = preg_replace('/[^0-9]/', '', str_replace(',', '', $value));
    if ($clean === '') return 0;
    $intValue = (int)$clean;
    if ($negative) $intValue = -$intValue;
    return $intValue > MAX_STOCK ? MAX_STOCK : $intValue;
}

function cleanCellValue(?string $value): string
{
    if ($value === null) return '';
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = trim($value);
    return $value;
}

function parseDate(?string $value): ?string
{
    if (empty($value)) return null;
    $value = cleanCellValue($value);
    
    $formats = [
        'Y-m-d',
        'm/d/Y',
        'd/m/Y',
        'Y-m-d H:i:s',
        'm/d/Y H:i:s',
        'd/m/Y H:i:s'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }
    
    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

/**
 * Detect column mapping from headers
 */
function normalizeHeader(string $header): string
{
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
    $header = strtolower(trim($header));
    $header = preg_replace('/[\s_\-]+/', ' ', $header);
    return trim($header);
}

function detectColumnMapping(array $headers): array
{
    $map = [];
    $usedFields = [];
    $normalized = [];

    foreach ($headers as $idx => $header) {
        $normalized[$idx] = normalizeHeader((string)$header);
    }

    foreach ($normalized as $idx => $header) {
        if ($header === '') continue;
        foreach (FIELD_MAPPING as $field => $synonyms) {
            $synonyms = array_map('normalizeHeader', $synonyms);
            if (in_array($header, $synonyms, true) && !isset($usedFields[$field])) {
                $map[$idx] = $field;
                $usedFields[$field] = true;
                break;
            }
        }
    }

    foreach ($normalized as $idx => $header) {
        if ($header === '' || isset($map[$idx])) continue;
        $bestField = null;
        $bestLength = -1;
        foreach (FIELD_MAPPING as $field => $synonyms) {
            if (isset($usedFields[$field])) continue;
            foreach ($synonyms as $synonym) {
                $synonym = normalizeHeader($synonym);
                if ($synonym !== '' &&
                    (strpos($header, $synonym) !== false || strpos($synonym, $header) !== false) &&
                    strlen($synonym) > $bestLength) {
                    $bestField = $field;
                    $bestLength = strlen($synonym);
                }
            }
        }
        if ($bestField !== null) {
            $map[$idx] = $bestField;
            $usedFields[$bestField] = true;
        }
    }

    $standardOrder = [
        'medicine_name', 'category', 'unit_type', 'stock_quantity',
        'cost_price', 'selling_price', 'batch_no', 'supplier',
        'expiry_date', 'barcode'
    ];
    $remaining = array_values(array_diff($standardOrder, array_values($map)));

    foreach ($normalized as $idx => $header) {
        if ($header !== '' && !isset($map[$idx]) && !empty($remaining)) {
            $map[$idx] = array_shift($remaining);
        }
    }

    ksort($map);
    return $map;
}

// -----------------------------------------------------------------------------
// 3. Template download handler
// -----------------------------------------------------------------------------
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="medicine_import_template.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    $headers = ['Medicine Name', 'Category', 'Unit Type', 'Stock Quantity', 'Cost Price', 'Selling Price', 'Batch No', 'Supplier', 'Expiry Date', 'Barcode'];
    fputcsv($output, $headers);
    for ($i = 0; $i < 5; $i++) {
        fputcsv($output, array_fill(0, count($headers), ''));
    }
    fclose($output);
    exit;
}

// -----------------------------------------------------------------------------
// 4. CSV Parser
// -----------------------------------------------------------------------------

function parseCsvFile(string $filePath, ?array &$debug = null): array
{
    $rows = [];
    $debug = $debug ?? [];

    if (!is_readable($filePath)) {
        $debug['error'] = 'File not readable.';
        return $rows;
    }

    if (filesize($filePath) == 0) {
        $debug['error'] = 'File is empty.';
        return $rows;
    }

    // Check file signature to detect if it's a ZIP (XLSX)
    $handle = fopen($filePath, 'rb');
    $signature = fread($handle, 4);
    fclose($handle);
    
    if ($signature === "PK\x03\x04") {
        $debug['error'] = 'File is an XLSX file. Please save as CSV first.';
        return $rows;
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        $debug['error'] = 'Could not open file.';
        return $rows;
    }

    $firstLine = fgets($handle);
    fclose($handle);
    if ($firstLine === false) {
        $debug['error'] = 'Could not read first line.';
        return $rows;
    }

    // Check if it's HTML
    if (preg_match('/<html/i', $firstLine) || preg_match('/<table/i', $firstLine)) {
        $debug['error'] = 'File appears to be HTML, not CSV.';
        return $rows;
    }

    // Detect delimiter
    $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
    $delimiters = [',', ';', "\t", '|'];
    $delimiter = ',';
    $bestCount = 0;
    foreach ($delimiters as $candidate) {
        $count = count(str_getcsv($firstLine, $candidate));
        if ($count > $bestCount) {
            $bestCount = $count;
            $delimiter = $candidate;
        }
    }
    $debug['delimiter'] = $delimiter;

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        $debug['error'] = 'Could not reopen file.';
        return $rows;
    }

    // Read header
    $headerLine = fgetcsv($handle, 0, $delimiter);
    if ($headerLine === false) {
        $debug['error'] = 'Could not read header row.';
        fclose($handle);
        return $rows;
    }
    
    // Clean header
    $headerLine[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine[0]);
    $debug['headers'] = $headerLine;
    
    // Detect column mapping
    $colMap = detectColumnMapping($headerLine);
    $debug['column_map'] = $colMap;

    // Read data rows
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $hasData = false;
        $cleanedData = [];
        foreach ($data as $cell) {
            if ($cell === null) {
                $cell = '';
            }
            $cleanedCell = trim((string)$cell);
            $cleanedData[] = $cleanedCell;
            if ($cleanedCell !== '') {
                $hasData = true;
            }
        }
        if (!$hasData) continue;

        $row = [];
        $hasMedicineName = false;
        foreach ($colMap as $idx => $field) {
            $value = isset($cleanedData[$idx]) ? $cleanedData[$idx] : '';
            $value = cleanCellValue($value);
            $row[$field] = $value;
            if ($field === 'medicine_name' && !empty($value)) {
                $hasMedicineName = true;
            }
        }
        
        if ($hasMedicineName) {
            $rows[] = $row;
        }
    }
    fclose($handle);

    $debug['row_count'] = count($rows);
    if (!empty($rows)) {
        $debug['first_row'] = $rows[0];
        $debug['sample_rows'] = array_slice($rows, 0, 3);
    }
    return $rows;
}

// -----------------------------------------------------------------------------
// 5. Core import logic - FIXED DUPLICATE HANDLING
// -----------------------------------------------------------------------------

function validateRow(array $row, int $rowNumber): array
{
    $errors = [];
    $data = [];

    $medicineName = trim($row['medicine_name'] ?? '');
    $category = trim($row['category'] ?? '');
    $unitType = trim($row['unit_type'] ?? '');
    $stockQty = isset($row['stock_quantity']) ? sanitizeInteger($row['stock_quantity']) : DEFAULT_STOCK;
    $costPrice = isset($row['cost_price']) ? sanitizeNumeric($row['cost_price']) : 0;
    $sellingPrice = isset($row['selling_price']) ? sanitizeNumeric($row['selling_price']) : 0;
    $batchNo = trim($row['batch_no'] ?? '');
    $supplier = trim($row['supplier'] ?? '');
    $expiryDate = parseDate($row['expiry_date'] ?? '');
    $barcode = trim($row['barcode'] ?? '');

    if ($medicineName === '') {
        $errors[] = 'Medicine Name is required.';
    }
    if ($sellingPrice < 0) {
        $errors[] = 'Selling Price cannot be negative.';
    }
    if ($stockQty < 0) {
        $errors[] = 'Stock Quantity cannot be negative.';
    }
    if ($stockQty > MAX_STOCK) {
        $errors[] = 'Stock Quantity exceeds maximum allowed value (' . MAX_STOCK . ').';
    }
    if ($costPrice < 0) {
        $errors[] = 'Cost Price cannot be negative.';
    }

    // Apply defaults
    if ($category === '') $category = DEFAULT_CATEGORY;
    if ($unitType === '') $unitType = DEFAULT_UNIT;
    if ($stockQty <= 0) $stockQty = DEFAULT_STOCK;
    if ($stockQty > MAX_STOCK) $stockQty = MAX_STOCK;
    if ($batchNo === '') $batchNo = DEFAULT_BATCH;
    if ($supplier === '') $supplier = DEFAULT_SUPPLIER;
    if ($expiryDate === null) {
        $expiryDate = date('Y-m-d', strtotime('+2 years'));
    }

    $data = [
        'medicine_name' => $medicineName,
        'category' => $category,
        'unit_type' => $unitType,
        'stock_quantity' => $stockQty,
        'cost_price' => $costPrice,
        'selling_price' => $sellingPrice,
        'batch_no' => $batchNo,
        'supplier' => $supplier,
        'expiry_date' => $expiryDate,
        'barcode' => $barcode,
    ];

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $data,
        'row_num' => $rowNumber,
    ];
}

function importMedicines(mysqli $conn, array $rows, ?array &$importedList = null): array
{
    $startTime = microtime(true);
    $startMemory = memory_get_usage();

    $inserted = 0;
    $skipped = 0;
    $failed = 0;
    $duplicates = 0;
    $errors = [];
    $duplicateNames = [];
    $importedList = [];
    $errorLimit = 1000;
    $commitEvery = 500;

    if (empty($rows)) {
        return [
            'inserted' => 0, 
            'skipped' => 0, 
            'failed' => 0, 
            'duplicates' => 0,
            'duplicate_names' => [],
            'errors' => [], 
            'progress' => []
        ];
    }

    // --- FIX: Pre-load existing medicine names for duplicate checking ---
    $existingNames = [];
    $nameStmt = $conn->prepare('SELECT LOWER(name) as name FROM medicines');
    $nameStmt->execute();
    $result = $nameStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $existingNames[strtolower(trim($row['name']))] = true;
    }
    $nameStmt->close();

    // Preload categories
    $categoryCache = [];
    $stmt = $conn->prepare('SELECT id, name FROM categories');
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categoryCache[strtolower(trim($row['name']))] = (int) $row['id'];
    }
    $stmt->close();

    $insertStmt = $conn->prepare(
        'INSERT INTO medicines
            (name, category_id, unit_type, stock_quantity, cost_price,
             selling_price, expiry_date, batch_no, supplier, barcode)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $conn->begin_transaction();

    $totalRows = count($rows);
    $processedCount = 0;
    $progress = [];
    $seenInBatch = []; // Track duplicates within the same import batch

    foreach ($rows as $index => $row) {
        $rowNumber = $index + 2;
        $processedCount++;

        $validation = validateRow($row, $rowNumber);
        if (!$validation['valid']) {
            $skipped++;
            if (count($errors) < $errorLimit) {
                $errors[] = "Row {$rowNumber}: " . implode('; ', $validation['errors']);
            }
            continue;
        }

        $data = $validation['data'];
        $nameKey = strtolower(trim($data['medicine_name']));

        // --- FIX: Check for duplicates ---
        // Check against existing database records
        if (isset($existingNames[$nameKey])) {
            $duplicates++;
            $duplicateNames[] = $data['medicine_name'] . " (row {$rowNumber})";
            if (count($errors) < $errorLimit) {
                $errors[] = "Row {$rowNumber}: Duplicate medicine '{$data['medicine_name']}' - skipped (already exists in database)";
            }
            continue;
        }

        // Check against duplicates within this import batch
        if (isset($seenInBatch[$nameKey])) {
            $duplicates++;
            $duplicateNames[] = $data['medicine_name'] . " (row {$rowNumber})";
            if (count($errors) < $errorLimit) {
                $errors[] = "Row {$rowNumber}: Duplicate medicine '{$data['medicine_name']}' - skipped (duplicate in CSV file)";
            }
            continue;
        }

        // Mark as seen in this batch
        $seenInBatch[$nameKey] = true;

        // Get or create category
        $catKey = strtolower($data['category']);
        if (!isset($categoryCache[$catKey])) {
            $catStmt = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
            $catStmt->bind_param('s', $data['category']);
            $catStmt->execute();
            $newId = (int) $conn->insert_id;
            $categoryCache[$catKey] = $newId;
            $catStmt->close();
        }
        $categoryId = $categoryCache[$catKey];

        try {
            $medicineName = $data['medicine_name'];
            $unitType = $data['unit_type'];
            $stockQuantity = (int)$data['stock_quantity'];
            $costPrice = (float)$data['cost_price'];
            $sellingPrice = (float)$data['selling_price'];
            $expiryDate = $data['expiry_date'];
            $batchNo = $data['batch_no'];
            $supplier = $data['supplier'];
            $barcode = $data['barcode'];

            $insertStmt->bind_param(
                'sisiddssss',
                $medicineName,
                $categoryId,
                $unitType,
                $stockQuantity,
                $costPrice,
                $sellingPrice,
                $expiryDate,
                $batchNo,
                $supplier,
                $barcode
            );
            if ($insertStmt->execute()) {
                $inserted++;
                // Add to existing names cache so subsequent rows check against it
                $existingNames[$nameKey] = true;
                $importedList[] = [
                    'name' => $data['medicine_name'],
                    'category' => $data['category'],
                    'selling_price' => $data['selling_price'],
                    'stock' => $data['stock_quantity']
                ];
            } else {
                $failed++;
                if (count($errors) < $errorLimit) {
                    $errors[] = "Row {$rowNumber}: Database insert error: " . $insertStmt->error;
                }
            }
        } catch (Throwable $e) {
            $failed++;
            if (count($errors) < $errorLimit) {
                $errors[] = "Row {$rowNumber}: Exception – " . $e->getMessage();
            }
        }

        if ($processedCount % $commitEvery === 0) {
            $conn->commit();
            $conn->begin_transaction();
            $progress[] = "Processed " . $processedCount . " of {$totalRows} rows. Inserted: {$inserted}, Duplicates: {$duplicates}, Skipped: {$skipped}, Failed: {$failed}.";
        }
    }

    $conn->commit();
    $insertStmt->close();

    $endTime = microtime(true);
    $endMemory = memory_get_usage();
    $peakMemory = memory_get_peak_usage();

    return [
        'inserted' => $inserted,
        'skipped' => $skipped,
        'failed' => $failed,
        'duplicates' => $duplicates,
        'duplicate_names' => $duplicateNames,
        'errors' => $errors,
        'progress' => $progress,
        'timing' => [
            'total_seconds' => round($endTime - $startTime, 2),
            'memory_used_mb' => round(($endMemory - $startMemory) / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
        ]
    ];
}

// -----------------------------------------------------------------------------
// 6. Handle file uploads
// -----------------------------------------------------------------------------

$success = null;
$error = null;
$importedMedicines = [];
$totalRows = 0;
$debug = [];
$duplicateList = [];

if (isset($_POST['upload_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['csv'];

    if (!in_array($ext, $allowed)) {
        $error = 'Only CSV files are allowed. Please save your Excel file as CSV first.';
    } elseif ($file['size'] > MAX_FILE_SIZE) {
        $error = 'File size exceeds the ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB limit.';
    } else {
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0777, true);
        }
        $target = UPLOAD_DIR . time() . '_' . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target)) {
            try {
                $rows = parseCsvFile($target, $debug);

                if (empty($rows)) {
                    $msg = 'No valid data rows found. ';
                    if (isset($debug['error'])) {
                        $msg .= 'Parser error: ' . $debug['error'] . '. ';
                    }
                    if (!empty($debug['headers'])) {
                        $msg .= 'Detected headers: ' . implode(', ', array_map('htmlspecialchars', $debug['headers'])) . '. ';
                    }
                    if (!empty($debug['column_map'])) {
                        $msg .= 'Column mapping: <pre>' . print_r($debug['column_map'], true) . '</pre>. ';
                    }
                    if (isset($debug['row_count'])) {
                        $msg .= 'Found ' . $debug['row_count'] . ' data rows. ';
                    }
                    if (isset($debug['sample_rows']) && !empty($debug['sample_rows'])) {
                        $msg .= 'Sample rows: <pre>' . print_r($debug['sample_rows'], true) . '</pre>. ';
                    }
                    $error = $msg;
                } else {
                    $totalRows = count($rows);
                    $result = importMedicines($conn, $rows, $importedMedicines);
                    
                    $success = sprintf(
                        'Import completed. Inserted: %d, Duplicates Skipped: %d, Skipped (errors): %d, Failed: %d.',
                        $result['inserted'],
                        $result['duplicates'],
                        $result['skipped'],
                        $result['failed']
                    );
                    $success .= ' Time: ' . $result['timing']['total_seconds'] . 's, Memory: ' . $result['timing']['memory_used_mb'] . 'MB.';

                    if (!empty($result['progress'])) {
                        $success .= '<br>' . implode('<br>', $result['progress']);
                    }

                    if (!empty($result['duplicate_names'])) {
                        $duplicateList = $result['duplicate_names'];
                    }

                    if (!empty($result['errors'])) {
                        $error = '<br><strong>Errors:</strong><br>' .
                            implode('<br>', array_slice($result['errors'], 0, 20));
                        if (count($result['errors']) > 20) {
                            $error .= '<br>... and ' . (count($result['errors']) - 20) . ' more errors.';
                        }
                    }
                }
            } catch (Throwable $e) {
                $error = 'Processing error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine();
            }
            unlink($target);
        } else {
            $error = 'Failed to move uploaded file.';
        }
    }
}

// -----------------------------------------------------------------------------
// 7. HTML output
// -----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Medicines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #2c3e50; }
        .sidebar a { color: white; display: block; padding: 12px 20px; text-decoration: none; }
        .sidebar a:hover { background: #1a252f; }
        .content { padding: 20px; }
        .btn-template { margin: 5px; }
        .upload-section {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .upload-section:hover {
            border-color: #0d6efd;
            background: #f8f9fa;
        }
        .template-preview {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .imported-list {
            max-height: 600px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        .imported-list .list-group-item {
            background: transparent;
            border-color: #e9ecef;
            padding: 8px 12px;
        }
        .imported-list .list-group-item:last-child {
            border-bottom: none;
        }
        .imported-list .list-group-item:nth-child(even) {
            background: rgba(0,0,0,0.02);
        }
        .imported-list .list-group-item:nth-child(odd) {
            background: rgba(0,0,0,0.01);
        }
        .progress-stats {
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .status-badge {
            position: relative;
            padding-left: 25px;
        }
        .status-badge::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-badge.success::before { background: #28a745; }
        .status-badge.danger::before { background: #dc3545; }
        .status-badge.warning::before { background: #ffc107; }
        .status-badge.info::before { background: #17a2b8; }
        .status-badge.primary::before { background: #007bff; }
        .status-badge.secondary::before { background: #6c757d; }
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .list-group-item .badge {
            font-size: 11px;
        }
        .duplicate-list {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'admin_sidebar.php'; ?>
        <div class="col-md-10 content">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3>📤 Upload Medicines</h3>
                    <small>Import medicines via CSV file - Duplicates are automatically skipped</small>
                </div>
                <div class="card-body">

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> <?= $success ?>
                            
                            <?php if (!empty($duplicateList)): ?>
                                <div class="mt-2">
                                    <strong><i class="fas fa-exclamation-triangle text-warning me-2"></i>Duplicate Medicines Skipped (<?= count($duplicateList) ?>):</strong>
                                    <div class="duplicate-list mt-1">
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($duplicateList as $dup): ?>
                                                <li><span class="text-warning">⚠️</span> <?= htmlspecialchars($dup) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($importedMedicines)): ?>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong><i class="fas fa-list me-2"></i>Imported Medicines (<?= count($importedMedicines) ?>):</strong>
                                        <span class="badge bg-primary">Total: <?= $totalRows ?> rows</span>
                                    </div>
                                    <div class="imported-list">
                                        <div class="list-group">
                                            <?php foreach ($importedMedicines as $index => $medicine): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span class="text-muted" style="min-width: 30px;">#<?= $index + 1 ?></span>
                                                    <span style="flex: 1; min-width: 0;">
                                                        <i class="fas fa-pills text-success me-2"></i>
                                                        <strong><?= htmlspecialchars($medicine['name']) ?></strong>
                                                    </span>
                                                    <span class="badge bg-info me-1"><?= htmlspecialchars($medicine['category']) ?></span>
                                                    <span class="badge bg-success me-1">KES <?= number_format($medicine['selling_price'], 2) ?></span>
                                                    <span class="badge bg-secondary">Stock: <?= $medicine['stock'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="progress-stats mt-2">
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <div class="status-badge success">
                                                    <strong>Inserted:</strong> <?= count($importedMedicines) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="status-badge secondary">
                                                    <strong>Duplicates:</strong> <?= $result['duplicates'] ?? 0 ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="status-badge warning">
                                                    <strong>Total Rows:</strong> <?= $totalRows ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="status-badge info">
                                                    <strong>Time:</strong> 
                                                    <?= $result['timing']['total_seconds'] ?? 0 ?>s
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <?php if (!empty($debug) && (isset($_GET['debug']) || isset($debug['error']))): ?>
                        <div class="alert alert-info">
                            <strong>Debug Information:</strong>
                            <div class="debug-info mt-2">
                                <?= htmlspecialchars(print_r($debug, true)) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <strong>📌 Download Blank Template</strong> – Fill in your data and upload.
                                <br><small><i class="fas fa-info-circle"></i> If a medicine name already exists in the database, it will be skipped automatically.</small>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="?download_template" class="btn btn-success btn-template">
                                    <i class="fas fa-file-csv"></i> CSV Template
                                </a>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="upload-section">
                        <h5><i class="fas fa-file-csv text-success"></i> Import from CSV</h5>
                        <p class="text-muted">Supports .csv files – headers will be auto-detected. Duplicate medicine names will be ignored.</p>
                        <div class="template-preview mb-3">
                            <strong>Expected Headers:</strong>
                            Medicine Name, Category, Unit Type, Stock Quantity, Cost Price, Selling Price, Batch No, Supplier, Expiry Date, Barcode
                            <br><span class="text-muted">Only <strong>Medicine Name</strong> is required.</span>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Select CSV File</label>
                                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="upload_csv" class="btn btn-primary w-100">
                                        <i class="fas fa-upload"></i> Import
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="mt-3">
                        <a href="medicines.php" class="btn btn-secondary"><i class="fas fa-list"></i> View All Medicines</a>
                        <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5>📝 Field Descriptions</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr><th>Field</th><th>Required</th><th>Default</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>Medicine Name</strong></td><td><span class="badge bg-danger">Required</span></td><td>–</td><td>Name of the medicine (duplicates are skipped)</td></tr>
                            <tr><td><strong>Category</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>General</td><td>e.g. Pain Relief, Antibiotics</td></tr>
                            <tr><td><strong>Unit Type</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>Tablet</td><td>Tablet, Capsule, Sachet, etc.</td></tr>
                            <tr><td><strong>Stock Quantity</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>100</td><td>Initial stock (max <?= MAX_STOCK ?>)</td></tr>
                            <tr><td><strong>Cost Price</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>0</td><td>Purchase cost</td></tr>
                            <tr><td><strong>Selling Price</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>0</td><td>Retail price</td></tr>
                            <tr><td><strong>Batch No</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>CSV-UPLOAD</td><td>Batch / lot number</td></tr>
                            <tr><td><strong>Supplier</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>CSV Import</td><td>Supplier name</td></tr>
                            <tr><td><strong>Expiry Date</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>+2 years</td><td>Expiration date</td></tr>
                            <tr><td><strong>Barcode</strong></td><td><span class="badge bg-secondary">Optional</span></td><td>–</td><td>Barcode number</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>