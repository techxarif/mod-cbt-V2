<?php

require_once '../includes/db.php';
require_once '../includes/auth.php';

requireTeacher();

$page_title = "Student Management";

$stmt = $pdo->query("
    SELECT
        id,
        name,
        uid,
        mobile,
        date_of_birth,
        status,
        created_at
    FROM students
    ORDER BY id DESC
");

$students = $stmt->fetchAll();

require_once '../includes/header.php';

?>

<div class="page-header">

    <div>
        <h1>Student Management</h1>
        <p>View and manage all registered students.</p>
    </div>

    <div>
        <a href="import_students.php" class="btn btn-primary">
            Import Students
        </a>
    </div>

</div>


<div class="card">

    <div style="overflow-x: auto;">

        <table>

            <thead>
                <tr>
                    <th>#</th>
                    <th>Student Name</th>
                    <th>UID</th>
                    <th>Mobile Number</th>
                    <th>Date of Birth</th>
                    <th>Status</th>
                    <th>Added On</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($students)): ?>

                <tr>
                    <td colspan="7" style="text-align: center;">
                        No students found.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($students as $index => $student): ?>

                    <tr>

                        <td>
                            <?= $index + 1 ?>
                        </td>

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
                            <?= date(
                                'd/m/Y',
                                strtotime($student['date_of_birth'])
                            ) ?>
                        </td>

                        <td>

                            <?php if ($student['status'] === 'active'): ?>

                                <span class="status-badge status-active">
                                    Active
                                </span>

                            <?php else: ?>

                                <span class="status-badge status-inactive">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= date(
                                'd/m/Y',
                                strtotime($student['created_at'])
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php require_once '../includes/footer.php'; ?>