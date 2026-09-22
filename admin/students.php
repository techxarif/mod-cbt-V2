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

<style>

/* =========================================================
   MODUS STUDENT MANAGEMENT
========================================================= */

.students-page {

    position: relative;

    width: 100%;

    color: #f1f5f9;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.students-page .page-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}

.students-page .page-header h1 {

    margin: 0;

    color: #f8fafc;

    font-size: 31px;

    font-weight: 800;

    letter-spacing: -.7px;
}

.students-page .page-header p {

    margin: 8px 0 0;

    color: #94a3b8;

    font-size: 14px;
}


/* =========================================================
   PRIMARY BUTTON
========================================================= */

.students-page .btn-primary {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 43px;

    padding: 0 17px;

    border-radius: 11px;

    border: 1px solid rgba(96,165,250,.30);

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    text-decoration: none;

    font-size: 13px;

    font-weight: 700;

    box-shadow:
        0 10px 30px rgba(37,99,235,.20);

    transition:
        transform .18s ease,
        box-shadow .18s ease;
}

.students-page .btn-primary:hover {

    transform: translateY(-1px);

    box-shadow:
        0 14px 35px rgba(37,99,235,.30);
}


/* =========================================================
   MAIN CARD
========================================================= */

.students-card {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            145deg,
            rgba(15,23,42,.97),
            rgba(7,12,24,.98)
        );

    border:
        1px solid rgba(148,163,184,.13);

    border-radius: 20px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.35),
        inset 0 1px 0 rgba(255,255,255,.025);

    backdrop-filter: blur(16px);

    -webkit-backdrop-filter: blur(16px);
}


/* Ambient glow */

.students-card::before {

    content: "";

    position: absolute;

    width: 320px;

    height: 200px;

    right: -140px;

    top: -120px;

    background:
        rgba(37,99,235,.09);

    filter: blur(65px);

    pointer-events: none;
}


/* =========================================================
   CARD HEADER
========================================================= */

.students-card-header {

    position: relative;

    z-index: 1;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 21px 23px;

    border-bottom:
        1px solid rgba(148,163,184,.09);
}


.students-card-title {

    display: flex;

    align-items: center;

    gap: 11px;
}


.students-icon {

    width: 36px;

    height: 36px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.18),
            rgba(124,58,237,.18)
        );

    border:
        1px solid rgba(96,165,250,.16);

    color: #93c5fd;

    font-size: 15px;

    font-weight: 800;
}


.students-card-title h2 {

    margin: 0;

    color: #e2e8f0;

    font-size: 16px;

    font-weight: 750;
}


.students-card-title span {

    display: block;

    margin-top: 3px;

    color: #64748b;

    font-size: 11px;
}


.student-count {

    padding: 7px 10px;

    border-radius: 8px;

    background:
        rgba(148,163,184,.06);

    border:
        1px solid rgba(148,163,184,.10);

    color: #94a3b8;

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   TABLE
========================================================= */

.students-table-wrap {

    position: relative;

    z-index: 1;

    overflow-x: auto;
}


.students-table {

    width: 100%;

    min-width: 900px;

    border-collapse: collapse;
}


/* =========================================================
   TABLE HEADER
========================================================= */

.students-table thead th {

    padding: 13px 18px;

    background:
        #0d1524;

    border-bottom:
        1px solid rgba(148,163,184,.10);

    color: #64748b;

    font-size: 10px;

    font-weight: 750;

    text-align: left;

    text-transform: uppercase;

    letter-spacing: .6px;

    white-space: nowrap;
}


/* =========================================================
   TABLE CELLS
========================================================= */

.students-table tbody td {

    padding: 14px 18px;

    border-bottom:
        1px solid rgba(148,163,184,.07);

    background:
        rgba(8,13,24,.72);

    color: #aebbd0;

    font-size: 13px;

    white-space: nowrap;
}


.students-table tbody tr {

    transition:
        background .15s ease;
}


.students-table tbody tr:hover td {

    background:
        rgba(30,41,59,.45);
}


.students-table tbody tr:last-child td {

    border-bottom: none;
}


/* =========================================================
   NUMBER
========================================================= */

.student-number {

    color: #64748b;

    font-size: 12px;

    font-weight: 700;
}


/* =========================================================
   STUDENT NAME
========================================================= */

.student-name-cell {

    display: flex;

    align-items: center;

    gap: 10px;
}


.student-avatar {

    width: 34px;

    height: 34px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            rgba(37,99,235,.17),
            rgba(124,58,237,.17)
        );

    border:
        1px solid rgba(96,165,250,.14);

    color: #93c5fd;

    font-size: 11px;

    font-weight: 800;
}


.student-name {

    color: #f1f5f9;

    font-size: 13px;

    font-weight: 700;
}


/* =========================================================
   UID
========================================================= */

.student-uid {

    color: #93c5fd;

    font-weight: 700;

    letter-spacing: .2px;
}


/* =========================================================
   MOBILE
========================================================= */

.student-mobile {

    color: #cbd5e1;
}


/* =========================================================
   DATE
========================================================= */

.student-date {

    color: #94a3b8;
}


/* =========================================================
   STATUS
========================================================= */

.status-badge {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 5px 9px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .35px;
}


.status-badge::before {

    content: "";

    width: 5px;

    height: 5px;

    border-radius: 50%;
}


.status-active {

    background:
        rgba(74,222,128,.10);

    border:
        1px solid rgba(74,222,128,.18);

    color: #86efac;
}


.status-active::before {

    background: #4ade80;

    box-shadow:
        0 0 8px rgba(74,222,128,.55);
}


.status-inactive {

    background:
        rgba(251,113,133,.10);

    border:
        1px solid rgba(251,113,133,.18);

    color: #fda4af;
}


.status-inactive::before {

    background: #fb7185;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.students-empty {

    padding: 70px 20px;

    text-align: center;
}


.students-empty-icon {

    width: 50px;

    height: 50px;

    margin: 0 auto 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        rgba(37,99,235,.08);

    border:
        1px solid rgba(96,165,250,.12);

    color: #60a5fa;

    font-size: 20px;
}


.students-empty h3 {

    margin: 0 0 7px;

    color: #e2e8f0;

    font-size: 18px;
}


.students-empty p {

    margin: 0;

    color: #64748b;

    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {

    .students-page .page-header {

        align-items: stretch;

        flex-direction: column;
    }

    .students-page .btn-primary {

        width: 100%;
    }

    .students-page .page-header h1 {

        font-size: 27px;
    }

    .students-card-header {

        align-items: flex-start;

        flex-direction: column;
    }

}


/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        transition: none !important;

        animation: none !important;
    }

}

</style>


<div class="students-page">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="page-header">

        <div>

            <h1>
                Student Management
            </h1>

            <p>
                View and manage all registered students.
            </p>

        </div>


        <div>

            <a
                href="import_students.php"
                class="btn btn-primary"
            >
                + Import Students
            </a>

        </div>

    </div>


    <!-- =====================================================
         STUDENTS CARD
    ====================================================== -->

    <div class="students-card">


        <div class="students-card-header">


            <div class="students-card-title">

                <div class="students-icon">
                    S
                </div>

                <div>

                    <h2>
                        Registered Students
                    </h2>

                    <span>
                        Student database
                    </span>

                </div>

            </div>


            <div class="student-count">

                <?= count($students) ?>

                Students

            </div>


        </div>


        <!-- =================================================
             TABLE
        ================================================== -->

        <div class="students-table-wrap">

            <table class="students-table">


                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Student
                        </th>

                        <th>
                            UID
                        </th>

                        <th>
                            Mobile Number
                        </th>

                        <th>
                            Date of Birth
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Added On
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($students)): ?>


                    <tr>

                        <td colspan="7">

                            <div class="students-empty">

                                <div class="students-empty-icon">
                                    —
                                </div>

                                <h3>
                                    No students found
                                </h3>

                                <p>
                                    Import students to start building your database.
                                </p>

                            </div>

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $students as $index => $student
                    ): ?>


                        <?php

                        $student_name =
                            trim($student['name']);

                        $initial =
                            strtoupper(
                                substr(
                                    $student_name,
                                    0,
                                    1
                                )
                            );

                        ?>


                        <tr>


                            <!-- NUMBER -->

                            <td>

                                <span class="student-number">

                                    <?= $index + 1 ?>

                                </span>

                            </td>


                            <!-- STUDENT -->

                            <td>

                                <div class="student-name-cell">


                                    <div class="student-avatar">

                                        <?= htmlspecialchars(
                                            $initial
                                        ) ?>

                                    </div>


                                    <div class="student-name">

                                        <?= htmlspecialchars(
                                            $student_name
                                        ) ?>

                                    </div>


                                </div>

                            </td>


                            <!-- UID -->

                            <td>

                                <span class="student-uid">

                                    <?= htmlspecialchars(
                                        $student['uid']
                                    ) ?>

                                </span>

                            </td>


                            <!-- MOBILE -->

                            <td>

                                <span class="student-mobile">

                                    <?= htmlspecialchars(
                                        $student['mobile']
                                    ) ?>

                                </span>

                            </td>


                            <!-- DOB -->

                            <td>

                                <span class="student-date">

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $student['date_of_birth']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>


                                <?php if (
                                    $student['status'] === 'active'
                                ): ?>


                                    <span
                                        class="status-badge status-active"
                                    >
                                        Active
                                    </span>


                                <?php else: ?>


                                    <span
                                        class="status-badge status-inactive"
                                    >
                                        Inactive
                                    </span>


                                <?php endif; ?>


                            </td>


                            <!-- CREATED -->

                            <td>

                                <span class="student-date">

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $student['created_at']
                                        )
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>


    </div>


</div>


<?php require_once '../includes/footer.php'; ?>