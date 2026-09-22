<?php

require_once '../includes/db.php';
require_once '../includes/auth.php';

requireTeacher();

$message = '';
$error = '';
$importedStudents = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {

        $error = "Please select a valid CSV file.";

    } else {

        $file = $_FILES['csv_file']['tmp_name'];

        $handle = fopen($file, 'r');

        if ($handle === false) {

            $error = "Could not open CSV file.";

        } else {

            /*
             * Read CSV header
             */

            $headers = fgetcsv($handle);

            if (!$headers) {

                $error = "CSV file is empty.";

            } else {

                /*
                 * Remove UTF-8 BOM
                 */

                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

                $headers = array_map(function ($header) {
                    return strtolower(trim($header));
                }, $headers);

                /*
                 * Required columns
                 */

                $requiredColumns = [
                    'name',
                    'mobile_number',
                    'date_of_birth'
                ];

                foreach ($requiredColumns as $column) {

                    if (!in_array($column, $headers)) {

                        $error = "Missing CSV column: " . $column;
                        break;
                    }
                }

                if (!$error) {

                    $nameIndex = array_search('name', $headers);
                    $mobileIndex = array_search('mobile_number', $headers);
                    $dobIndex = array_search('date_of_birth', $headers);

                    $successCount = 0;
                    $rowNumber = 1;

                    /*
                     * Read rows
                     */

                    while (($row = fgetcsv($handle)) !== false) {

                        $rowNumber++;

                        /*
                         * Skip empty rows
                         */

                        if (
                            count($row) === 1 &&
                            trim($row[0]) === ''
                        ) {
                            continue;
                        }

                        $name = trim($row[$nameIndex] ?? '');
                        $mobile = trim($row[$mobileIndex] ?? '');
                        $dobInput = trim($row[$dobIndex] ?? '');

                        /*
                         * NAME VALIDATION
                         */

                        if ($name === '') {

                            $errors[] =
                                "Row $rowNumber: Student name is empty.";

                            continue;
                        }

                        /*
                         * MOBILE VALIDATION
                         */

                        $mobile = preg_replace('/[^0-9]/', '', $mobile);

                        if (strlen($mobile) !== 10) {

                            $errors[] =
                                "Row $rowNumber ($name): Invalid mobile number: $mobile";

                            continue;
                        }

                        /*
                         * DOB
                         *
                         * Accept:
                         *
                         * 24/09/2008
                         * 24-09-2008
                         * 2008-09-24
                         */

                        $dob = false;

                        $formats = [
                            'd/m/Y',
                            'd-m-Y',
                            'Y-m-d'
                        ];

                        foreach ($formats as $format) {

                            $temp = DateTime::createFromFormat(
                                $format,
                                $dobInput
                            );

                            if (
                                $temp &&
                                $temp->format($format) === $dobInput
                            ) {

                                $dob = $temp;
                                break;
                            }
                        }

                        if (!$dob) {

                            $errors[] =
                                "Row $rowNumber ($name): Invalid DOB '$dobInput'. Use DD/MM/YYYY.";

                            continue;
                        }

                        /*
                         * Database DOB
                         */

                        $databaseDob = $dob->format('Y-m-d');

                        /*
                         * PASSWORD
                         *
                         * 24/09/2008
                         *
                         * becomes:
                         *
                         * 24092008
                         */

                        $plainPassword = $dob->format('dmY');

                        /*
                         * CHECK DUPLICATE MOBILE
                         */

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM students
                            WHERE mobile = ?
                            LIMIT 1
                        ");

                        $stmt->execute([$mobile]);

                        if ($stmt->fetch()) {

                            $errors[] =
                                "Row $rowNumber ($name): Mobile number $mobile already exists.";

                            continue;
                        }

                        /*
                         * GENERATE UNIQUE UID
                         */

                        do {

                            $uid = 'MOD' .
                                   date('ym') .
                                   str_pad(
                                       random_int(1, 9999),
                                       4,
                                       '0',
                                       STR_PAD_LEFT
                                   );

                            $stmt = $pdo->prepare("
                                SELECT id
                                FROM students
                                WHERE uid = ?
                                LIMIT 1
                            ");

                            $stmt->execute([$uid]);

                        } while ($stmt->fetch());

                        /*
                         * HASH PASSWORD
                         */

                        $passwordHash = password_hash(
                            $plainPassword,
                            PASSWORD_DEFAULT
                        );

                        /*
                         * INSERT STUDENT
                         */

                        try {

                            $stmt = $pdo->prepare("
                                INSERT INTO students
                                (
                                    name,
                                    uid,
                                    mobile,
                                    date_of_birth,
                                    password_hash,
                                    status
                                )
                                VALUES
                                (?, ?, ?, ?, ?, 'active')
                            ");

                            $stmt->execute([
                                $name,
                                $uid,
                                $mobile,
                                $databaseDob,
                                $passwordHash
                            ]);

                            $successCount++;

                            $importedStudents[] = [
                                'name' => $name,
                                'uid' => $uid,
                                'mobile' => $mobile,
                                'dob' => $dob->format('d/m/Y'),
                                'password' => $plainPassword
                            ];

                        } catch (PDOException $e) {

                            $errors[] =
                                "Row $rowNumber ($name): Database error - " .
                                $e->getMessage();
                        }
                    }

                    /*
                     * Success message
                     */

                    $message =
                        $successCount .
                        " student(s) imported successfully.";

                    if (!empty($errors)) {

                        $message .=
                            " " .
                            count($errors) .
                            " row(s) failed.";
                    }
                }
            }

            fclose($handle);
        }
    }
}

$page_title = "Import Students";

require_once '../includes/header.php';

?>

<style>

/* =========================================================
   MODUS IMPORT STUDENTS
========================================================= */

.import-page {
    position: relative;
    min-height: calc(100vh - 80px);
    padding-bottom: 50px;
}

.import-page::before,
.import-page::after {
    content: "";
    position: fixed;
    width: 500px;
    height: 500px;
    border-radius: 50%;
    filter: blur(110px);
    pointer-events: none;
    z-index: -1;
    opacity: .15;
    animation: modusFloat 12s ease-in-out infinite alternate;
}

.import-page::before {
    background: #2563eb;
    top: -180px;
    left: -200px;
}
.import-card {
    position: relative;

    padding: 28px;

    /* MUCH MORE OPAQUE */
    background:
        linear-gradient(
            145deg,
            rgba(15, 23, 42, 0.97),
            rgba(7, 12, 24, 0.98)
        );

    border: 1px solid rgba(148, 163, 184, 0.14);

    border-radius: 22px;

    box-shadow:
        0 25px 70px rgba(0, 0, 0, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.035);

    overflow: hidden;

    /* Keep slight glass effect */
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}
.import-page::after {
    background: black;
    right: -220px;
    bottom: -200px;
    animation-delay: -5s;
}

@keyframes modusFloat {
    from {
        transform: translate(0, 0) scale(1);
    }

    to {
        transform: translate(35px, 25px) scale(1.08);
    }
}


/* =========================================================
   HEADER
========================================================= */

.import-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 25px;

    margin-bottom: 30px;
}

.import-eyebrow {
    display: flex;
    align-items: center;
    gap: 9px;

    margin-bottom: 12px;

    color: #60a5fa;

    font-size: 10px;
    font-weight: 800;
    letter-spacing: .18em;
    text-transform: uppercase;
}

.import-eyebrow-dot {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #3b82f6;

    box-shadow:
        0 0 0 5px rgba(59,130,246,.08),
        0 0 15px rgba(59,130,246,.75);

    animation: importPulse 2s infinite;
}

@keyframes importPulse {

    0%,100% {
        opacity: 1;
        transform: scale(1);
    }

    50% {
        opacity: .55;
        transform: scale(.78);
    }

}

.import-header h1 {
    margin: 0;

    color: #f8fafc;

    font-size: clamp(34px, 5vw, 54px);
    line-height: .98;

    font-weight: 850;

    letter-spacing: -.055em;
}

.import-header p {
    margin: 12px 0 0;

    color: #7f8da5;

    font-size: 14px;
}


/* =========================================================
   STATUS
========================================================= */

.import-status {
    flex-shrink: 0;

    display: flex;
    align-items: center;
    gap: 9px;

    padding: 11px 15px;

    border-radius: 999px;

    background: rgba(59,130,246,.07);
    border: 1px solid rgba(96,165,250,.15);

    color: #93c5fd;

    font-size: 10px;
    font-weight: 800;

    letter-spacing: .1em;
    text-transform: uppercase;
}

.import-status span {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #60a5fa;

    box-shadow: 0 0 12px rgba(96,165,250,.7);
}


/* =========================================================
   GRID
========================================================= */

.import-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.45fr)
        minmax(300px, .75fr);

    gap: 20px;

    align-items: start;
}


/* =========================================================
   GLASS CARD
========================================================= */

.import-card {
    position: relative;

    padding: 28px;

    /* MUCH MORE OPAQUE */
    background:
        linear-gradient(
            145deg,
            rgba(15, 23, 42, 0.97),
            rgba(7, 12, 24, 0.98)
        );

    border: 1px solid rgba(148, 163, 184, 0.14);

    border-radius: 22px;

    box-shadow:
        0 25px 70px rgba(0, 0, 0, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.035);

    overflow: hidden;

    /* Keep slight glass effect */
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}


.import-card::before {
    content: "";

    position: absolute;
    inset: 0;

    background:
        linear-gradient(
            135deg,
            rgba(37, 99, 235, 0.035),
            transparent 40%,
            rgba(124, 58, 237, 0.025)
        );

    pointer-events: none;
}
.import-card > * {
    position: relative;
    z-index: 1;
}


/* =========================================================
   CARD HEADING
========================================================= */

.card-heading {
    margin-bottom: 24px;
}

.card-heading-row {
    display: flex;
    align-items: center;
    gap: 13px;
}

.card-icon {
    width: 40px;
    height: 40px;

    display: grid;
    place-items: center;

    border-radius: 12px;

    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.16),
            rgba(124,58,237,.13)
        );

    border: 1px solid rgba(96,165,250,.13);

    color: #60a5fa;

    font-size: 17px;
    font-weight: 900;
}

.card-heading h2 {
    margin: 0;

    color: #f1f5f9;

    font-size: 19px;
    font-weight: 800;

    letter-spacing: -.02em;
}

.card-heading p {
    margin: 6px 0 0;

    color: #64748b;

    font-size: 12px;
    line-height: 1.55;
}


/* =========================================================
   CSV FORMAT BOX
========================================================= */

.csv-box {
    padding: 18px;

    border-radius: 15px;

    background: rgba(8, 13, 25, 0.92);

    border: 1px solid rgba(148, 163, 184, 0.10);

    margin-bottom: 18px;

    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.025);
}

.csv-box-label {
    margin-bottom: 10px;

    color: #64748b;

    font-size: 9px;
    font-weight: 800;

    letter-spacing: .15em;
    text-transform: uppercase;
}

.csv-box pre {
    margin: 0;

    padding: 15px;

    overflow-x: auto;

    border-radius: 11px;

    background: #050812;

    border: 1px solid rgba(255,255,255,.05);

    color: #a5b4fc;

    font-family:
        "SFMono-Regular",
        Consolas,
        monospace;

    font-size: 11px;
    line-height: 1.8;
}


/* =========================================================
   PASSWORD INFO
========================================================= */

.password-info {
    display: flex;
    gap: 12px;

    padding: 15px 17px;

    border-radius: 14px;

    background: rgba(16,185,129,.045);
    border: 1px solid rgba(16,185,129,.11);

    margin-bottom: 25px;
}

.password-icon {
    flex-shrink: 0;

    width: 30px;
    height: 30px;

    display: grid;
    place-items: center;

    border-radius: 9px;

    background: rgba(16,185,129,.09);

    color: #6ee7b7;

    font-size: 13px;
}

.password-info strong {
    color: #a7f3d0;

    font-size: 12px;
}

.password-info div {
    color: #64748b;

    font-size: 11px;

    line-height: 1.55;
}

.password-example {
    margin-top: 4px;

    color: #94a3b8 !important;

    font-family:
        "SFMono-Regular",
        Consolas,
        monospace;
}


/* =========================================================
   FILE DROP AREA
========================================================= */

.file-upload {
    position: relative;

    margin-top: 8px;
}

.file-upload input[type="file"] {
    position: absolute;

    width: 1px;
    height: 1px;

    opacity: 0;

    pointer-events: none;
}

.file-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;

    min-height: 150px;

    padding: 25px;

    border-radius: 17px;

    border: 1px dashed rgba(96,165,250,.25);

    background:
        radial-gradient(
            circle at center,
            rgba(37,99,235,.07),
            transparent 65%
        ),
        rgba(255,255,255,.018);

    cursor: pointer;

    transition:
        border-color .2s ease,
        background .2s ease,
        transform .2s ease;
}

.file-label:hover {
    transform: translateY(-2px);

    border-color: rgba(96,165,250,.5);

    background:
        radial-gradient(
            circle at center,
            rgba(37,99,235,.11),
            transparent 65%
        ),
        rgba(255,255,255,.025);
}

.upload-icon {
    width: 48px;
    height: 48px;

    display: grid;
    place-items: center;

    flex-shrink: 0;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.17),
            rgba(124,58,237,.13)
        );

    border: 1px solid rgba(96,165,250,.15);

    color: #60a5fa;

    font-size: 21px;
}

.upload-text strong {
    display: block;

    color: #e2e8f0;

    font-size: 13px;
}

.upload-text span {
    display: block;

    margin-top: 4px;

    color: #64748b;

    font-size: 11px;
}

.selected-file {
    margin-top: 10px;

    color: #60a5fa;

    font-size: 11px;

    text-align: center;
}


/* =========================================================
   IMPORT BUTTON
========================================================= */

.import-submit {
    position: relative;

    width: 100%;

    min-height: 50px;

    margin-top: 17px;

    border: 0;
    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    font-size: 13px;
    font-weight: 800;

    letter-spacing: .02em;

    cursor: pointer;

    overflow: hidden;

    box-shadow:
        0 14px 35px rgba(37,99,235,.2);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}

.import-submit::after {
    content: "";

    position: absolute;

    top: 0;
    left: -120%;

    width: 65%;
    height: 100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.18),
            transparent
        );

    transform: skewX(-20deg);

    transition: left .65s ease;
}

.import-submit:hover {
    transform: translateY(-2px);

    box-shadow:
        0 18px 40px rgba(37,99,235,.28);
}

.import-submit:hover::after {
    left: 150%;
}


/* =========================================================
   SIDE INFORMATION
========================================================= */

.info-list {
    display: grid;

    gap: 10px;
}
{
background:
        radial-gradient(
            circle at 10% 10%,
            rgba(37, 99, 235, 0.10),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 80%,
            rgba(124, 58, 237, 0.08),
            transparent 30%
        ),
        #050812 !important;

    color: #e2e8f0;
}
.info-row {
    display: flex;
    align-items: center;
    gap: 12px;

    padding: 13px;

    border-radius: 13px;

    background: rgba(15, 23, 42, 0.82);

    border: 1px solid rgba(148, 163, 184, 0.09);

    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.02);

    transition:
        background .2s ease,
        border-color .2s ease,
        transform .2s ease;
}

.info-row:hover {
    background: rgba(20, 30, 50, 0.95);
    border-color: rgba(96, 165, 250, 0.16);
    transform: translateX(3px);
}

.info-number {
    width: 28px;
    height: 28px;

    display: grid;
    place-items: center;

    flex-shrink: 0;

    border-radius: 8px;

    background: rgba(59,130,246,.08);

    color: #60a5fa;

    font-size: 10px;
    font-weight: 800;
}

.info-row strong {
    display: block;

    color: #cbd5e1;

    font-size: 11px;
}

.info-row span {
    display: block;

    margin-top: 2px;

    color: #64748b;

    font-size: 10px;
}


/* =========================================================
   ALERTS
========================================================= */

.modus-alert {
    margin-bottom: 20px;

    padding: 15px 17px;

    border-radius: 14px;

    font-size: 12px;
    line-height: 1.6;
}

.modus-success {
    background: rgba(16,185,129,.07);

    border: 1px solid rgba(16,185,129,.18);

    color: #86efac;
}

.modus-error {
    background: rgba(239,68,68,.07);

    border: 1px solid rgba(239,68,68,.17);

    color: #fca5a5;
}


/* =========================================================
   ERROR CARD
========================================================= */

.error-card {
    margin-top: 20px;
}

.error-item {
    padding: 11px 13px;

    margin-bottom: 7px;

    border-radius: 10px;

    background: rgba(239,68,68,.045);

    border: 1px solid rgba(239,68,68,.08);

    color: #fca5a5;

    font-size: 11px;
}

.error-item:last-child {
    margin-bottom: 0;
}


/* =========================================================
   IMPORTED STUDENTS
========================================================= */

.students-card {
    margin-top: 20px;
}

.table-wrap {
    overflow-x: auto;

    border-radius: 15px;

    border: 1px solid rgba(255,255,255,.06);
}

.students-table {
    width: 100%;

    border-collapse: collapse;

    min-width: 700px;
}

.students-table th {
    padding: 13px 15px;

    text-align: left;

    color: #64748b;

    background: rgba(255,255,255,.025);

    font-size: 9px;
    font-weight: 800;

    letter-spacing: .12em;
    text-transform: uppercase;

    border-bottom: 1px solid rgba(255,255,255,.06);
}

.students-table td {
    padding: 14px 15px;

    color: #aab7ca;

    font-size: 11px;

    border-bottom: 1px solid rgba(255,255,255,.045);
}

.students-table tr:last-child td {
    border-bottom: none;
}

.student-name {
    color: #e2e8f0;

    font-weight: 750;
}

.student-uid {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 7px;

    background: rgba(59,130,246,.07);

    color: #93c5fd;

    font-family:
        "SFMono-Regular",
        Consolas,
        monospace;

    font-size: 10px;
}

.student-password {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 7px;

    background: rgba(16,185,129,.07);

    color: #86efac;

    font-family:
        "SFMono-Regular",
        Consolas,
        monospace;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .import-grid {
        grid-template-columns: 1fr;
    }

    .import-header {
        align-items: flex-start;
        flex-direction: column;
    }

}

@media (max-width: 600px) {

    .import-card {
        padding: 20px;
    }

    .import-header h1 {
        font-size: 39px;
    }

    .import-status {
        display: none;
    }

    .file-label {
        flex-direction: column;
        text-align: center;
    }

}

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
    }

}

</style>


<div class="import-page">

    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="import-header">

        <div>

            <div class="import-eyebrow">

                <span class="import-eyebrow-dot"></span>

                Student Management

            </div>

            <h1>
                Import Students
            </h1>

            <p>
                Bulk-create student accounts from a structured CSV file.
            </p>

        </div>


        <div class="import-status">

            <span></span>

            CSV Import Ready

        </div>

    </div>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if ($message): ?>

        <div class="modus-alert modus-success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="modus-alert modus-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         MAIN GRID
    ====================================================== -->

    <div class="import-grid">


        <!-- =================================================
             IMPORT FORM
        ================================================== -->

        <section class="import-card">

            <div class="card-heading">

                <div class="card-heading-row">

                    <div class="card-icon">
                        ↑
                    </div>

                    <div>

                        <h2>
                            Student CSV Import
                        </h2>

                        <p>
                            Upload a CSV containing student identity
                            and date-of-birth information.
                        </p>

                    </div>

                </div>

            </div>


            <!-- CSV FORMAT -->

            <div class="csv-box">

                <div class="csv-box-label">
                    Required CSV Format
                </div>

<pre>name,mobile_number,date_of_birth
Rahul Mondal,9876543210,24/09/2008
Aman Mondal,9876543211,15/11/2007</pre>

            </div>


            <!-- PASSWORD INFO -->

            <div class="password-info">

                <div class="password-icon">
                    🔐
                </div>

                <div>

                    <strong>
                        Automatic student password
                    </strong>

                    <div>
                        Passwords are generated automatically
                        from the student's date of birth.
                    </div>

                    <div class="password-example">
                        Example: 24/09/2008 → 24092008
                    </div>

                </div>

            </div>


            <!-- FORM -->

            <form
                method="POST"
                enctype="multipart/form-data"
                id="studentImportForm"
            >

                <div class="file-upload">

                    <input
                        type="file"
                        name="csv_file"
                        id="csvFile"
                        accept=".csv"
                        required
                    >

                    <label
                        for="csvFile"
                        class="file-label"
                    >

                        <div class="upload-icon">
                            ↑
                        </div>

                        <div class="upload-text">

                            <strong>
                                Select CSV file
                            </strong>

                            <span>
                                Click here to browse your computer
                            </span>

                        </div>

                    </label>

                    <div
                        class="selected-file"
                        id="selectedFile"
                    >
                        No file selected
                    </div>

                </div>


                <button
                    type="submit"
                    class="import-submit"
                >
                    Import Students
                </button>

            </form>

        </section>


        <!-- =================================================
             IMPORT GUIDE
        ================================================== -->

        <aside class="import-card">

            <div class="card-heading">

                <div class="card-heading-row">

                    <div class="card-icon">
                        i
                    </div>

                    <div>

                        <h2>
                            Import Workflow
                        </h2>

                        <p>
                            What MODUS does with each row.
                        </p>

                    </div>

                </div>

            </div>


            <div class="info-list">

                <div class="info-row">

                    <div class="info-number">
                        01
                    </div>

                    <div>

                        <strong>
                            Read CSV
                        </strong>

                        <span>
                            MODUS reads the required columns.
                        </span>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-number">
                        02
                    </div>

                    <div>

                        <strong>
                            Validate
                        </strong>

                        <span>
                            Name, mobile and DOB are checked.
                        </span>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-number">
                        03
                    </div>

                    <div>

                        <strong>
                            Check duplicates
                        </strong>

                        <span>
                            Existing mobile numbers are rejected.
                        </span>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-number">
                        04
                    </div>

                    <div>

                        <strong>
                            Generate UID
                        </strong>

                        <span>
                            Every student receives a unique MOD UID.
                        </span>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-number">
                        05
                    </div>

                    <div>

                        <strong>
                            Create account
                        </strong>

                        <span>
                            Password is securely hashed in MySQL.
                        </span>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-number">
                        06
                    </div>

                    <div>

                        <strong>
                            Show credentials
                        </strong>

                        <span>
                            Newly created accounts appear below.
                        </span>

                    </div>

                </div>

            </div>

        </aside>

    </div>


    <!-- =====================================================
         IMPORT ERRORS
    ====================================================== -->

    <?php if (!empty($errors)): ?>

        <section class="import-card error-card">

            <div class="card-heading">

                <div class="card-heading-row">

                    <div
                        class="card-icon"
                        style="
                            background:rgba(239,68,68,.08);
                            color:#f87171;
                            border-color:rgba(239,68,68,.13);
                        "
                    >
                        !
                    </div>

                    <div>

                        <h2>
                            Import Errors
                        </h2>

                        <p>
                            These rows could not be imported.
                        </p>

                    </div>

                </div>

            </div>


            <?php foreach ($errors as $importError): ?>

                <div class="error-item">

                    <?= htmlspecialchars($importError) ?>

                </div>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         SUCCESSFULLY IMPORTED STUDENTS
    ====================================================== -->

    <?php if (!empty($importedStudents)): ?>

        <section class="import-card students-card">

            <div class="card-heading">

                <div class="card-heading-row">

                    <div
                        class="card-icon"
                        style="
                            background:rgba(16,185,129,.07);
                            color:#6ee7b7;
                            border-color:rgba(16,185,129,.13);
                        "
                    >
                        ✓
                    </div>

                    <div>

                        <h2>
                            Successfully Imported
                        </h2>

                        <p>
                            Student credentials generated by MODUS.
                        </p>

                    </div>

                </div>

            </div>


            <div class="table-wrap">

                <table class="students-table">

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                MOD UID
                            </th>

                            <th>
                                Mobile
                            </th>

                            <th>
                                DOB
                            </th>

                            <th>
                                Password
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($importedStudents as $student): ?>

                        <tr>

                            <td>

                                <span class="student-name">
                                    <?= htmlspecialchars($student['name']) ?>
                                </span>

                            </td>


                            <td>

                                <span class="student-uid">
                                    <?= htmlspecialchars($student['uid']) ?>
                                </span>

                            </td>


                            <td>
                                <?= htmlspecialchars($student['mobile']) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($student['dob']) ?>
                            </td>


                            <td>

                                <span class="student-password">
                                    <?= htmlspecialchars($student['password']) ?>
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>

</div>


<script>

/*
|--------------------------------------------------------------------------
| File Selection UI
|--------------------------------------------------------------------------
*/

const csvFile = document.getElementById('csvFile');
const selectedFile = document.getElementById('selectedFile');

if (csvFile) {

    csvFile.addEventListener('change', function () {

        if (this.files && this.files.length > 0) {

            const file = this.files[0];

            selectedFile.textContent =
                file.name +
                ' • ' +
                Math.round(file.size / 1024) +
                ' KB';

        } else {

            selectedFile.textContent =
                'No file selected';

        }

    });

}


/*
|--------------------------------------------------------------------------
| Submit State
|--------------------------------------------------------------------------
*/

const importForm =
    document.getElementById('studentImportForm');

if (importForm) {

    importForm.addEventListener('submit', function () {

        const button =
            this.querySelector('.import-submit');

        if (button) {

            button.disabled = true;

            button.textContent =
                'Importing Students...';

            button.style.opacity = '.75';

            button.style.cursor = 'wait';

        }

    });

}

</script>


<?php require_once '../includes/footer.php'; ?>