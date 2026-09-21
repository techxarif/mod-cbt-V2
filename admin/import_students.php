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

<div class="page-header">

    <div>

        <h1>Import Students</h1>

        <p>
            Add students using a CSV file.
        </p>

    </div>

</div>


<?php if ($message): ?>

<div class="alert success">

    <?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert error">

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<div class="card">

    <h2>Student CSV Import</h2>

    <p>
        CSV format:
    </p>

<pre>name,mobile_number,date_of_birth
Rahul Mondal,9876543210,24/09/2008
Aman Mondal,9876543211,15/11/2007</pre>

    <p>
        Student password is automatically generated from DOB.
    </p>

    <p>
        Example:
        <strong>24/09/2008 → 24092008</strong>
    </p>


    <form
        method="POST"
        enctype="multipart/form-data"
    >

        <div class="form-group">

            <label>
                Select CSV File
            </label>

            <input
                type="file"
                name="csv_file"
                accept=".csv"
                required
            >

        </div>


        <button
            type="submit"
            class="btn btn-primary"
        >
            Import Students
        </button>

    </form>

</div>


<?php if (!empty($errors)): ?>

<div class="card">

    <h2>Import Errors</h2>

    <div class="alert error">

        <?php foreach ($errors as $importError): ?>

            <div style="margin-bottom: 6px;">
                <?= htmlspecialchars($importError) ?>
            </div>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; ?>


<?php if (!empty($importedStudents)): ?>

<div class="card">

    <h2>Successfully Imported Students</h2>

    <div style="overflow-x:auto;">

        <table>

            <thead>

                <tr>
                    <th>Name</th>
                    <th>UID</th>
                    <th>Mobile</th>
                    <th>DOB</th>
                    <th>Password</th>
                </tr>

            </thead>

            <tbody>

            <?php foreach ($importedStudents as $student): ?>

                <tr>

                    <td>
                        <strong>
                            <?= htmlspecialchars($student['name']) ?>
                        </strong>
                    </td>

                    <td>
                        <?= htmlspecialchars($student['uid']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($student['mobile']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($student['dob']) ?>
                    </td>

                    <td>
                        <strong>
                            <?= htmlspecialchars($student['password']) ?>
                        </strong>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>


<?php require_once '../includes/footer.php'; ?>