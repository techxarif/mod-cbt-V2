<?php
// students/import.php — Bulk Import Students from CSV / Pasted Data
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$error         = '';
$success       = '';
$importedCount = 0;
$skippedCount  = 0;
$rowErrors     = [];

/**
 * Normalizes multiple Date of Birth formats into MySQL 'YYYY-MM-DD'.
 */
function parse_student_dob(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    // Pattern YYYY-MM-DD or YYYY/MM/DD or YYYY.MM.DD
    if (preg_match('/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})$/', $raw, $m)) {
        if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
    }

    // Pattern DD-MM-YYYY or DD/MM/YYYY or DD.MM.YYYY
    if (preg_match('/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})$/', $raw, $m)) {
        // Standard DD/MM/YYYY first
        if (checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        // Fallback to MM/DD/YYYY
        if (checkdate((int)$m[1], (int)$m[2], (int)$m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
        }
    }

    // 8 continuous digits (e.g. 20050815 or 15082005)
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if (strlen($digits) === 8) {
        $first4 = (int)substr($digits, 0, 4);
        if ($first4 >= 1900 && $first4 <= 2099) {
            $y = $first4; $m = (int)substr($digits, 4, 2); $d = (int)substr($digits, 6, 2);
            if (checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }
        $last4 = (int)substr($digits, 4, 4);
        if ($last4 >= 1900 && $last4 <= 2099) {
            $y = $last4; $d = (int)substr($digits, 0, 2); $m = (int)substr($digits, 2, 2);
            if (checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }
    }

    // Human-readable textual dates (e.g. 15 Aug 2005, August 15 2005)
    $ts = strtotime($raw);
    if ($ts !== false && $ts > 0) {
        return date('Y-m-d', $ts);
    }

    return null;
}

/**
 * Clean & normalize column names for fuzzy detection.
 */
function normalize_col_key(string $col): string
{
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $col));
}

/**
 * Finds index of matching column given a list of candidate aliases.
 */
function find_col_index(array $headers, array $candidates): ?int
{
    $normHeaders = array_map('normalize_col_key', $headers);
    foreach ($candidates as $cand) {
        $normCand = normalize_col_key($cand);
        $idx = array_search($normCand, $normHeaders, true);
        if ($idx !== false) {
            return $idx;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $importSource = str_param('import_source', 'file', 'post');
    $csvString    = '';

    if ($importSource === 'paste') {
        $csvString = str_param('csv_text', '', 'post');
        if (trim($csvString) === '') {
            $error = 'Please paste your CSV data into the text box.';
        }
    } else {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a valid CSV file to upload.';
        } else {
            $csvString = file_get_contents($_FILES['csv_file']['tmp_name']);
            if ($csvString === false || trim($csvString) === '') {
                $error = 'Uploaded file is empty or could not be read.';
            }
        }
    }

    if (!$error && $csvString !== '') {
        // Strip UTF-8 BOM
        $csvString = preg_replace('/^\xEF\xBB\xBF/', '', $csvString);

        // Normalize newlines and parse rows
        $lines = preg_split('/\r\n|\r|\n/', trim($csvString));
        if (empty($lines)) {
            $error = 'No data found in CSV input.';
        } else {
            // Determine delimiter: comma vs tab vs semicolon
            $firstLine = $lines[0];
            $delimiter = ',';
            if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $delimiter = "\t";
            } elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            }

            $rows = [];
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $row = str_getcsv($line, $delimiter);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }

            if (empty($rows)) {
                $error = 'No valid rows found in CSV.';
            } else {
                $headers = array_shift($rows);
                $headers = array_map(fn($h) => trim((string)$h), $headers);

                // User verified overrides (from popup verification form)
                $colNameOverride   = str_param('col_name', '', 'post');
                $colMobileOverride = str_param('col_mobile', '', 'post');
                $colDobOverride    = str_param('col_dob', '', 'post');
                $colUidOverride    = str_param('col_uid', '', 'post');

                // Determine column indices
                $nameAliases = ['name', 'full_name', 'fullname', 'student_name', 'studentname', 'candidate_name', 'candidatename', 'student', 'candidate', 'first_name', 'candidate_full_name'];
                $mobAliases  = ['mobile_number', 'mobile', 'mob', 'phone', 'phone_number', 'phonenumber', 'contact', 'contact_number', 'contact_no', 'contactno', 'tel', 'cell', 'whatsapp', 'mobile_no'];
                $dobAliases  = ['date_of_birth', 'dob', 'd.o.b', 'd.o.b.', 'birth_date', 'birthdate', 'dateofbirth', 'birth_day', 'birthday', 'bday', 'birth'];
                $uidAliases  = ['uid', 'roll_no', 'roll', 'rollno', 'reg_no', 'regno', 'registration_no', 'registration_number', 'id', 'student_id', 'admission_no', 'roll_number'];

                $nameIdx   = ($colNameOverride !== '' && is_numeric($colNameOverride)) ? (int)$colNameOverride : find_col_index($headers, $nameAliases);
                $mobileIdx = ($colMobileOverride !== '' && is_numeric($colMobileOverride)) ? (int)$colMobileOverride : find_col_index($headers, $mobAliases);
                $dobIdx    = ($colDobOverride !== '' && is_numeric($colDobOverride)) ? (int)$colDobOverride : find_col_index($headers, $dobAliases);
                $uidIdx    = ($colUidOverride !== '' && is_numeric($colUidOverride)) ? (int)$colUidOverride : find_col_index($headers, $uidAliases);

                if ($nameIdx === null) {
                    $error = 'Could not find a student Name column. Please ensure your CSV has a column like "name" or "full_name".';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO students (name, uid, mobile, date_of_birth, password_hash, status)
                            VALUES (?, ?, ?, ?, ?, 'active')
                        ");

                        $checkUidStmt = $pdo->prepare("SELECT id FROM students WHERE uid = ? LIMIT 1");

                        $rowNum = 1;
                        foreach ($rows as $row) {
                            $rowNum++;
                            $name   = trim($row[$nameIdx] ?? '');
                            $mobile = ($mobileIdx !== null) ? trim($row[$mobileIdx] ?? '') : '';
                            $rawDob = ($dobIdx !== null) ? trim($row[$dobIdx] ?? '') : '';
                            $rawUid = ($uidIdx !== null) ? trim($row[$uidIdx] ?? '') : '';

                            if ($name === '') {
                                $skippedCount++;
                                $rowErrors[] = "Row $rowNum: Name was empty, skipped.";
                                continue;
                            }

                            // Robust Date of Birth parsing
                            $dob = parse_student_dob($rawDob);
                            if ($dob === null) {
                                // Default fallback date if missing or unparseable so MySQL NOT NULL is satisfied
                                $dob = '2000-01-01';
                                if ($rawDob !== '') {
                                    $rowErrors[] = "Row $rowNum ($name): Could not parse DOB '$rawDob' — default set to 2000-01-01.";
                                }
                            }

                            // Generate or validate UID
                            if ($rawUid !== '') {
                                $cleanUid = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $rawUid));
                                $checkUidStmt->execute([$cleanUid]);
                                if ($checkUidStmt->fetch()) {
                                    // Duplicate UID, create distinct suffix
                                    $uid = $cleanUid . '_' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                                } else {
                                    $uid = $cleanUid;
                                }
                            } else {
                                // STU + 6 random alphanumeric chars
                                $uid = 'STU' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                            }

                            // Generate default password based on DOB digits (e.g. 15082005) or fallback
                            $dobParts = explode('-', $dob); // YYYY-MM-DD
                            if (count($dobParts) === 3) {
                                $dobDigits = $dobParts[2] . $dobParts[1] . $dobParts[0]; // DDMMYYYY
                            } else {
                                $dobDigits = preg_replace('/[^0-9]/', '', $rawDob);
                            }
                            $rawPassword = (strlen($dobDigits) >= 6) ? $dobDigits : 'Student@123';
                            $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);

                            // Clean mobile number
                            $cleanMobile = preg_replace('/[^0-9+]/', '', $mobile);

                            $insertStmt->execute([
                                $name,
                                $uid,
                                $cleanMobile,
                                $dob,
                                $passwordHash
                            ]);
                            $importedCount++;
                        }

                        $pdo->commit();
                        $success = "Successfully imported $importedCount candidate(s)!" . ($skippedCount > 0 ? " ($skippedCount skipped)" : '');
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $ref = app_log('error', 'CSV Import failure: ' . $e->getMessage());
                        $error = 'Import failed. Ref: ' . $ref;
                    }
                }
            }
        }
    }
}

$page_title = 'Import Students — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'students');
?>

<div class="page-content" style="max-width: 860px; margin: 0 auto;">

    <div class="page-header">
        <div>
            <h1 class="page-title">Import Students</h1>
            <p class="page-subtitle">Batch enroll candidates using CSV file upload or direct text paste</p>
        </div>
        <a href="index.php" class="btn btn-secondary">← Back to Students</a>
    </div>

    <?php if ($error): ?>
        <?= alert_box('error', $error) ?>
    <?php endif; ?>

    <?php if ($success): ?>
        <?= alert_box('success', $success) ?>
    <?php endif; ?>

    <!-- =========================================================
         DEMO CSV TEMPLATE BOX (Preview before uploading)
         ========================================================= -->
    <div class="card" style="border: 1px solid rgba(32, 229, 150, 0.25); background: linear-gradient(145deg, rgba(14,21,31,0.95), rgba(9,14,21,0.92));">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: var(--green); box-shadow: 0 0 10px var(--green);"></span>
                <h3 style="font-size: 15px; font-weight: 800; color: #fff; margin: 0;">Sample / Demo CSV Format</h3>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="copyDemoCsv()" id="copyDemoBtn" style="font-size: 11px; padding: 6px 12px;">
                    📋 Copy Demo CSV
                </button>
                <button type="button" class="btn btn-primary" onclick="useDemoData()" style="font-size: 11px; padding: 6px 12px;">
                    ⚡ Use Demo Data & Verify
                </button>
                <button type="button" class="btn btn-secondary" onclick="downloadSampleCsv()" style="font-size: 11px; padding: 6px 12px;">
                    ⬇ Download .CSV
                </button>
            </div>
        </div>

        <p style="font-size: 12.5px; color: var(--muted); margin-bottom: 12px;">
            Multiple column names are recognized automatically (e.g. <code>name</code>, <code>full_name</code>, <code>dob</code>, <code>date_of_birth</code>, <code>mobile</code>, <code>phone</code>, <code>roll_no</code>).
            Date of birth can be in <strong>YYYY-MM-DD</strong>, <strong>DD/MM/YYYY</strong>, or <strong>DD-MM-YYYY</strong>.
        </p>

        <!-- Demo Table Preview -->
        <div class="data-table-wrapper" style="margin-bottom: 12px; border-radius: 8px;">
            <table class="data-table" style="min-width: 100%; font-size: 12px;">
                <thead>
                    <tr>
                        <th>name</th>
                        <th>mobile_number</th>
                        <th>date_of_birth</th>
                        <th>roll_no <small style="color:var(--muted);">(optional)</small></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Aarav Sharma</td>
                        <td>9876543210</td>
                        <td>15/08/2005</td>
                        <td>ROLL101</td>
                    </tr>
                    <tr>
                        <td>Priya Patel</td>
                        <td>9812345678</td>
                        <td>2006-03-22</td>
                        <td>ROLL102</td>
                    </tr>
                    <tr>
                        <td>Rohan Kumar</td>
                        <td>9898989898</td>
                        <td>12-10-2005</td>
                        <td>ROLL103</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <details style="font-size: 12px; color: var(--muted); cursor: pointer;">
            <summary style="font-weight: 700; color: var(--green);">View Raw CSV Plain Text</summary>
            <pre id="demoCsvRaw" style="margin-top: 8px; padding: 12px; background: rgba(0,0,0,0.4); border-radius: 6px; font-family: monospace; color: #a5f3fc; overflow-x: auto; font-size: 12px;">name,mobile_number,date_of_birth,roll_no
Aarav Sharma,9876543210,15/08/2005,ROLL101
Priya Patel,9812345678,2006-03-22,ROLL102
Rohan Kumar,9898989898,12-10-2005,ROLL103
Ananya Sen,9765432100,05/01/2006,ROLL104</pre>
        </details>
    </div>

    <!-- =========================================================
         IMPORT TABS (Upload File vs. Paste CSV)
         ========================================================= -->
    <div class="card">
        <!-- Tabs Header -->
        <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--line); padding-bottom: 12px; margin-bottom: 20px;">
            <button type="button" class="btn" id="tabBtnFile" onclick="switchTab('file')" style="background: rgba(32, 229, 150, 0.15); border-color: var(--green); color: var(--green);">
                📁 Upload CSV File
            </button>
            <button type="button" class="btn" id="tabBtnPaste" onclick="switchTab('paste')" style="background: transparent; border-color: var(--line); color: var(--muted);">
                📋 Paste CSV Data
            </button>
        </div>

        <!-- Main Submission Form -->
        <form id="importForm" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="import_source" id="importSourceInput" value="file">
            <!-- Overrides from popup -->
            <input type="hidden" name="col_name" id="postColName" value="">
            <input type="hidden" name="col_mobile" id="postColMobile" value="">
            <input type="hidden" name="col_dob" id="postColDob" value="">
            <input type="hidden" name="col_uid" id="postColUid" value="">

            <!-- Tab 1: File Upload Pane -->
            <div id="paneFile">
                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label" style="font-weight: 700; margin-bottom: 8px; display: block;">Select CSV File (.csv / .txt)</label>
                    <input type="file" id="csvFileInput" name="csv_file" class="form-input" accept=".csv,.txt" onchange="onFileSelected(this)">
                </div>
                <div style="font-size: 12px; color: var(--muted); margin-bottom: 20px;">
                    Supports spreadsheets saved as CSV from Excel, Google Sheets, or LibreOffice.
                </div>
            </div>

            <!-- Tab 2: Paste CSV Pane -->
            <div id="panePaste" style="display: none;">
                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label" style="font-weight: 700; margin-bottom: 8px; display: block;">
                        Paste CSV / Excel Data
                        <small style="font-weight: normal; color: var(--muted);">(Comma, Tab, or Semicolon separated)</small>
                    </label>
                    <textarea id="csvTextInput" name="csv_text" rows="8" class="form-input" placeholder="name,mobile,date_of_birth,roll_no&#10;Aarav Sharma,9876543210,15/08/2005,ROLL101&#10;Priya Patel,9812345678,2006-03-22,ROLL102" style="font-family: monospace; font-size: 13px; line-height: 1.5; resize: vertical;"></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--line); flex-wrap: wrap;">
                <span id="quickStatus" style="font-size: 12.5px; color: var(--muted);">Ready for import</span>
                <div style="display: flex; gap: 10px;">
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                    <button type="button" class="btn btn-primary" onclick="prepareAndVerify()" style="font-size: 13px; font-weight: 800;">
                        🔍 Review & Verify Columns →
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (!empty($rowErrors)): ?>
        <div class="card" style="border: 1px solid rgba(255, 180, 84, 0.35);">
            <h3 style="font-size: 14px; margin-bottom: 10px; color: var(--orange); display: flex; align-items: center; gap: 6px;">
                <span>⚠</span> Import Warnings / Skipped Rows:
            </h3>
            <ul style="font-size: 12.5px; color: #cbd5e1; padding-left: 20px; line-height: 1.6;">
                <?php foreach (array_slice($rowErrors, 0, 15) as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
                <?php if (count($rowErrors) > 15): ?>
                    <li><em>...and <?= count($rowErrors) - 15 ?> more notices.</em></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

</div>

<!-- =========================================================
     VERIFICATION POPUP MODAL
     Detects column mappings, allows user override, shows live preview
     ========================================================= -->
<div id="verifyModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.82); z-index: 10000; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); padding: 20px; overflow-y: auto;">
    <div style="max-width: 720px; margin: 30px auto; background: #0d141d; border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); overflow: hidden; animation: cardIn 0.3s ease both;">

        <!-- Modal Header -->
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="font-size: 17px; font-weight: 850; color: #fff; margin: 0;">Verify CSV Column Mappings</h2>
                <p style="font-size: 12px; color: var(--muted); margin: 3px 0 0;">Check detected fields and preview parsed student data before final import</p>
            </div>
            <button type="button" onclick="closeVerifyModal()" style="background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; padding: 4px 8px; line-height: 1;">✕</button>
        </div>

        <!-- Modal Body -->
        <div style="padding: 24px;">

            <!-- Status Banner -->
            <div id="modalSummaryBadge" style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; background: rgba(32, 229, 150, 0.12); border: 1px solid rgba(32, 229, 150, 0.3); border-radius: 999px; color: var(--green); font-size: 12px; font-weight: 750; margin-bottom: 20px;">
                ✓ Detected Columns Ready
            </div>

            <!-- Column Mapping Dropdowns Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; background: rgba(255,255,255,0.02); padding: 16px; border-radius: 12px; border: 1px solid var(--line);">

                <!-- Name Column -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #a5f3fc; margin-bottom: 6px;">
                        Student Name *
                    </label>
                    <select id="selectColName" class="form-input" style="font-size: 13px;" onchange="updatePreviewTable()"></select>
                </div>

                <!-- Mobile Column -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #a5f3fc; margin-bottom: 6px;">
                        Mobile Number
                    </label>
                    <select id="selectColMobile" class="form-input" style="font-size: 13px;" onchange="updatePreviewTable()"></select>
                </div>

                <!-- Date of Birth Column -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #a5f3fc; margin-bottom: 6px;">
                        Date of Birth (DOB) *
                    </label>
                    <select id="selectColDob" class="form-input" style="font-size: 13px;" onchange="updatePreviewTable()"></select>
                </div>

                <!-- Roll / UID Column (Optional) -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #a5f3fc; margin-bottom: 6px;">
                        Roll No / UID <small style="color: var(--muted);">(Optional)</small>
                    </label>
                    <select id="selectColUid" class="form-input" style="font-size: 13px;" onchange="updatePreviewTable()"></select>
                </div>

            </div>

            <!-- Live Sample Table Preview -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--muted);">
                        Live Preview (First 5 Rows)
                    </span>
                    <span id="previewCountBadge" style="font-size: 11px; color: var(--green);"></span>
                </div>

                <div class="data-table-wrapper" style="max-height: 220px; overflow-y: auto;">
                    <table class="data-table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Date of Birth (MySQL Y-m-d)</th>
                                <th>Assigned UID</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top: 14px; font-size: 11.5px; color: var(--muted); line-height: 1.5;">
                ℹ <em>Default passwords will automatically be generated from the birth date digits (e.g. <code>15082005</code>). If DOB is unparseable, <code>2000-01-01</code> is set safely.</em>
            </div>

        </div>

        <!-- Modal Footer -->
        <div style="padding: 16px 24px; background: rgba(7, 12, 18, 0.8); border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn btn-secondary" onclick="closeVerifyModal()">
                Back to Edit
            </button>
            <button type="button" class="btn btn-primary" onclick="confirmAndSubmitImport()" style="font-weight: 800;">
                ✓ Confirm & Import Students
            </button>
        </div>

    </div>
</div>

<script>
// ============================================================
// MODUS CBT — Client-Side CSV Parser & Verification Modal
// ============================================================

var currentParsedHeaders = [];
var currentParsedRows    = [];
var currentSourceMode    = 'file';

// Fuzzy header aliases
var ALIASES = {
    name:   ['name', 'fullname', 'full_name', 'studentname', 'student_name', 'candidatename', 'candidate_name', 'student', 'candidate', 'first_name'],
    mobile: ['mobile_number', 'mobile', 'mob', 'phone', 'phone_number', 'phonenumber', 'contact', 'contact_number', 'contact_no', 'contactno', 'tel', 'cell', 'whatsapp', 'mobile_no'],
    dob:    ['date_of_birth', 'dob', 'd.o.b', 'd.o.b.', 'birth_date', 'birthdate', 'dateofbirth', 'birth_day', 'birthday', 'bday', 'birth'],
    uid:    ['uid', 'roll_no', 'roll', 'rollno', 'reg_no', 'regno', 'registration_no', 'registration_number', 'id', 'student_id', 'admission_no', 'roll_number']
};

function switchTab(mode) {
    currentSourceMode = mode;
    document.getElementById('importSourceInput').value = mode;

    var btnFile  = document.getElementById('tabBtnFile');
    var btnPaste = document.getElementById('tabBtnPaste');
    var paneFile = document.getElementById('paneFile');
    var panePaste = document.getElementById('panePaste');

    if (mode === 'file') {
        btnFile.style.background = 'rgba(32, 229, 150, 0.15)';
        btnFile.style.borderColor = 'var(--green)';
        btnFile.style.color = 'var(--green)';

        btnPaste.style.background = 'transparent';
        btnPaste.style.borderColor = 'var(--line)';
        btnPaste.style.color = 'var(--muted)';

        paneFile.style.display = 'block';
        panePaste.style.display = 'none';
    } else {
        btnPaste.style.background = 'rgba(32, 229, 150, 0.15)';
        btnPaste.style.borderColor = 'var(--green)';
        btnPaste.style.color = 'var(--green)';

        btnFile.style.background = 'transparent';
        btnFile.style.borderColor = 'var(--line)';
        btnFile.style.color = 'var(--muted)';

        paneFile.style.display = 'none';
        panePaste.style.display = 'block';
    }
}

function copyDemoCsv() {
    var text = document.getElementById('demoCsvRaw').innerText;
    navigator.clipboard.writeText(text).then(function() {
        var btn = document.getElementById('copyDemoBtn');
        var orig = btn.innerText;
        btn.innerText = '✓ Copied!';
        btn.style.color = 'var(--green)';
        setTimeout(function() {
            btn.innerText = orig;
            btn.style.color = '';
        }, 2000);
    });
}

function useDemoData() {
    switchTab('paste');
    var demoText = document.getElementById('demoCsvRaw').innerText;
    var textarea = document.getElementById('csvTextInput');
    textarea.value = demoText;
    prepareAndVerify();
}

function downloadSampleCsv() {
    var text = document.getElementById('demoCsvRaw').innerText;
    var blob = new Blob([text], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'students_sample_template.csv';
    link.click();
}

function onFileSelected(input) {
    if (input.files && input.files[0]) {
        document.getElementById('quickStatus').innerText = 'Selected: ' + input.files[0].name;
    }
}

// Clean key for matching
function normalizeKey(str) {
    return (str || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

// Detect delimiter
function detectDelimiter(text) {
    var commaCount = (text.match(/,/g) || []).length;
    var tabCount   = (text.match(/\t/g) || []).length;
    var semiCount  = (text.match(/;/g) || []).length;
    if (tabCount > commaCount && tabCount > semiCount) return '\t';
    if (semiCount > commaCount && semiCount > tabCount) return ';';
    return ',';
}

// Robust CSV Line Parser
function parseCsvLine(line, delimiter) {
    var values = [];
    var current = '';
    var inQuotes = false;
    for (var i = 0; i < line.length; i++) {
        var char = line[i];
        if (char === '"' || char === "'") {
            inQuotes = !inQuotes;
        } else if (char === delimiter && !inQuotes) {
            values.push(current.trim().replace(/^["']|["']$/g, ''));
            current = '';
        } else {
            current += char;
        }
    }
    values.push(current.trim().replace(/^["']|["']$/g, ''));
    return values;
}

// Parse text into headers and rows
function parseCsvContent(content) {
    var lines = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    lines = lines.filter(function(l) { return l.trim().length > 0; });
    if (lines.length < 2) return null;

    var delimiter = detectDelimiter(lines[0]);
    var headers = parseCsvLine(lines[0], delimiter);
    var rows = [];

    for (var i = 1; i < lines.length; i++) {
        var row = parseCsvLine(lines[i], delimiter);
        if (row.length > 0 && row.some(function(cell) { return cell.trim() !== ''; })) {
            rows.push(row);
        }
    }
    return { headers: headers, rows: rows };
}

// Date normalization helper in JavaScript for preview
function normalizeDatePreview(raw) {
    if (!raw) return '2000-01-01 (default)';
    raw = raw.trim();

    // YYYY-MM-DD
    var m = raw.match(/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})$/);
    if (m) {
        return m[1] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[3]).padStart(2, '0');
    }

    // DD-MM-YYYY
    m = raw.match(/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})$/);
    if (m) {
        return m[3] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
    }

    // 8 continuous digits
    var digits = raw.replace(/[^0-9]/g, '');
    if (digits.length === 8) {
        var f4 = parseInt(digits.substr(0, 4));
        if (f4 >= 1900 && f4 <= 2099) {
            return digits.substr(0, 4) + '-' + digits.substr(4, 2) + '-' + digits.substr(6, 2);
        } else {
            return digits.substr(4, 4) + '-' + digits.substr(2, 2) + '-' + digits.substr(0, 2);
        }
    }

    var d = new Date(raw);
    if (!isNaN(d.getTime())) {
        return d.toISOString().split('T')[0];
    }
    return raw + ' ➔ 2000-01-01';
}

// Prepare data & open verification modal
function prepareAndVerify() {
    if (currentSourceMode === 'paste') {
        var text = document.getElementById('csvTextInput').value;
        if (!text || text.trim() === '') {
            alert('Please paste some CSV data first.');
            return;
        }
        var parsed = parseCsvContent(text);
        if (!parsed || parsed.rows.length === 0) {
            alert('Could not parse any valid student rows from pasted text. Please check format.');
            return;
        }
        openVerifyModal(parsed.headers, parsed.rows);
    } else {
        var fileInput = document.getElementById('csvFileInput');
        if (!fileInput.files || !fileInput.files[0]) {
            alert('Please choose a CSV file first.');
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            var content = e.target.result;
            var parsed = parseCsvContent(content);
            if (!parsed || parsed.rows.length === 0) {
                alert('Could not parse valid student rows from file.');
                return;
            }
            openVerifyModal(parsed.headers, parsed.rows);
        };
        reader.readAsText(fileInput.files[0]);
    }
}

function openVerifyModal(headers, rows) {
    currentParsedHeaders = headers;
    currentParsedRows    = rows;

    document.getElementById('modalSummaryBadge').innerHTML = '✓ ' + rows.length + ' Candidate Rows Found';
    document.getElementById('previewCountBadge').innerText = 'Showing top ' + Math.min(5, rows.length) + ' of ' + rows.length + ' rows';

    populateDropdown('selectColName', headers, ALIASES.name);
    populateDropdown('selectColMobile', headers, ALIASES.mobile, true);
    populateDropdown('selectColDob', headers, ALIASES.dob);
    populateDropdown('selectColUid', headers, ALIASES.uid, true, '[Auto-Generate STU######]');

    updatePreviewTable();
    document.getElementById('verifyModal').style.display = 'block';
}

function populateDropdown(selectId, headers, aliasList, isOptional, optLabel) {
    var select = document.getElementById(selectId);
    select.innerHTML = '';

    if (isOptional) {
        var opt = document.createElement('option');
        opt.value = '';
        opt.innerText = optLabel || '-- None / Optional --';
        select.appendChild(opt);
    }

    var bestIndex = -1;
    headers.forEach(function(h, idx) {
        var opt = document.createElement('option');
        opt.value = idx;
        opt.innerText = (idx + 1) + '. ' + h;

        var norm = normalizeKey(h);
        if (bestIndex === -1 && aliasList && aliasList.some(function(a) { return normalizeKey(a) === norm; })) {
            bestIndex = idx;
        }
        select.appendChild(opt);
    });

    if (bestIndex !== -1) {
        select.value = bestIndex;
    } else if (!isOptional && headers.length > 0) {
        select.value = 0;
    }
}

function updatePreviewTable() {
    var nameIdx = document.getElementById('selectColName').value;
    var mobIdx  = document.getElementById('selectColMobile').value;
    var dobIdx  = document.getElementById('selectColDob').value;
    var uidIdx  = document.getElementById('selectColUid').value;

    var tbody = document.getElementById('previewTableBody');
    tbody.innerHTML = '';

    var topRows = currentParsedRows.slice(0, 5);
    topRows.forEach(function(row, i) {
        var name = (nameIdx !== '' && row[nameIdx]) ? row[nameIdx] : '—';
        var mob  = (mobIdx !== '' && row[mobIdx]) ? row[mobIdx] : '—';
        var dob  = (dobIdx !== '' && row[dobIdx]) ? row[dobIdx] : '';
        var uid  = (uidIdx !== '' && row[uidIdx]) ? row[uidIdx] : 'STU' + Math.floor(100000 + Math.random() * 900000);

        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + (i + 1) + '</td>' +
                       '<td style="font-weight:700; color:#fff;">' + escapeHtml(name) + '</td>' +
                       '<td>' + escapeHtml(mob) + '</td>' +
                       '<td style="color:var(--green); font-family:monospace;">' + escapeHtml(normalizeDatePreview(dob)) + '</td>' +
                       '<td style="color:var(--muted); font-family:monospace;">' + escapeHtml(uid) + '</td>';
        tbody.appendChild(tr);
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function closeVerifyModal() {
    document.getElementById('verifyModal').style.display = 'none';
}

function confirmAndSubmitImport() {
    var nameIdx = document.getElementById('selectColName').value;
    if (nameIdx === '') {
        alert('Please map the Student Name column.');
        return;
    }

    document.getElementById('postColName').value   = document.getElementById('selectColName').value;
    document.getElementById('postColMobile').value = document.getElementById('selectColMobile').value;
    document.getElementById('postColDob').value    = document.getElementById('selectColDob').value;
    document.getElementById('postColUid').value    = document.getElementById('selectColUid').value;

    // Submit form
    document.getElementById('importForm').submit();
}

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeVerifyModal();
});
</script>

<?php layout_footer(); ?>
