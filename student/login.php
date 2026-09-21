<?php

require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Already logged in
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['student_id'])) {
    header('Location: waiting.php');
    exit;
}

$error = '';

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $uid = strtoupper(trim($_POST['uid'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if ($uid === '' || $password === '') {

        $error = 'Please enter your UID and password.';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                uid,
                password_hash,
                status
            FROM students
            WHERE uid = ?
            LIMIT 1
        ");

        $stmt->execute([$uid]);

        $student = $stmt->fetch();

        if (
            $student &&
            $student['status'] === 'active' &&
            password_verify($password, $student['password_hash'])
        ) {

            /*
             * Regenerate session ID after successful login.
             */
            session_regenerate_id(true);

            $_SESSION['student_id'] = (int)$student['id'];
            $_SESSION['student_uid'] = $student['uid'];
            $_SESSION['student_name'] = $student['name'];

            header('Location: waiting.php');
            exit;

        } else {

            $error = 'Invalid UID or password.';
        }
    }
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Student Login - MODUS CBT</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;

    width: 100%;
    min-height: 100%;

    font-family: Arial, Helvetica, sans-serif;

    background: #063b68;
}


/* =========================================================
   PAGE
========================================================= */

.login-page {

    min-height: 100vh;

    background:
        linear-gradient(
            rgba(3, 48, 86, 0.91),
            rgba(3, 48, 86, 0.91)
        ),
        radial-gradient(
            circle at 20% 20%,
            rgba(255,255,255,0.12),
            transparent 30%
        ),
        radial-gradient(
            circle at 80% 80%,
            rgba(255,255,255,0.08),
            transparent 30%
        );

    position: relative;

    overflow: hidden;
}


/* =========================================================
   TOP BLUE BAR
========================================================= */

.top-bar {

    height: 27px;

    background: #28638f;

    display: flex;

    align-items: center;

    justify-content: flex-end;

    padding-right: 14px;

    color: white;

    font-size: 13px;
}

.top-home {

    display: flex;

    align-items: center;

    gap: 7px;

    padding: 0 8px;
}

.home-icon {

    width: 17px;
    height: 17px;

    background: #20a84b;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 10px;

    border-radius: 2px;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 82px;

    background: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom: 1px solid #d5d5d5;

    padding: 0 22px;
}


/* =========================================================
   MODUS BRAND
========================================================= */

.brand {

    display: flex;

    align-items: center;

    gap: 13px;
}

.logo {

    width: 55px;
    height: 55px;

    border-radius: 50%;

    background: #20a84b;

    border: 4px solid #f0f0f0;

    display: flex;

    align-items: center;

    justify-content: center;

    color: white;

    font-size: 27px;

    font-weight: 900;
}

.brand-text {

    display: flex;

    flex-direction: column;
}

.brand-name {

    color: #174f80;

    font-size: 25px;

    font-weight: 800;

    letter-spacing: 0.5px;
}

.brand-subtitle {

    color: #20a84b;

    font-size: 10px;

    font-weight: 700;

    letter-spacing: 0.7px;
}


/* =========================================================
   CANDIDATE HEADER
========================================================= */

.candidate-area {

    height: 62px;

    display: flex;

    align-items: center;

    gap: 9px;
}

.candidate-photo {

    width: 52px;
    height: 52px;

    border: 1px solid #888;

    background: #eeeeee;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

    color: #555;
}

.candidate-info {

    font-size: 11px;

    line-height: 17px;

    color: #555;
}

.candidate-label {

    display: inline-block;

    width: 92px;
}

.candidate-value {

    color: #e88716;

    font-weight: 700;
}


/* =========================================================
   ORANGE SYSTEM BAR
========================================================= */

.system-bar {

    min-height: 45px;

    background: #f28c00;

    color: white;

    padding: 5px 9px;
}

.system-name {

    font-size: 16px;

    font-weight: 500;

    line-height: 19px;
}

.system-warning {

    font-size: 11px;

    line-height: 14px;
}


/* =========================================================
   MAIN LOGIN AREA
========================================================= */

.login-area {

    min-height: calc(100vh - 154px);

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 40px 20px;

    position: relative;
}


/*
 * Decorative CBT background.
 */

.login-area::before {

    content: "";

    position: absolute;

    inset: 0;

    background:
        linear-gradient(
            135deg,
            transparent 0 35%,
            rgba(255,255,255,0.025) 35% 36%,
            transparent 36% 65%,
            rgba(255,255,255,0.025) 65% 66%,
            transparent 66%
        );

    pointer-events: none;
}


/* =========================================================
   LOGIN BOX
========================================================= */

.login-box {

    position: relative;

    width: 360px;

    max-width: 100%;

    background:
        rgba(255,255,255,0.94);

    border: 1px solid rgba(255,255,255,0.8);

    box-shadow:
        0 12px 35px rgba(0,0,0,0.28);

    padding: 20px 16px 25px;

    z-index: 2;
}


/* =========================================================
   LOGIN TITLE
========================================================= */

.login-title {

    font-size: 16px;

    font-weight: 700;

    color: #333;

    padding-bottom: 12px;

    margin-bottom: 17px;

    border-bottom: 1px solid #dddddd;
}


/* =========================================================
   ERROR
========================================================= */

.error-message {

    background: #fce8e6;

    border: 1px solid #e5a19b;

    color: #a52218;

    font-size: 13px;

    padding: 9px 10px;

    margin-bottom: 14px;
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 14px;
}

.form-label {

    display: block;

    font-size: 11px;

    color: #666;

    margin-bottom: 4px;
}

.input-wrapper {

    position: relative;
}

.input {

    width: 100%;

    height: 34px;

    border: 1px solid #d2d2d2;

    background: rgba(255,255,255,0.85);

    padding: 0 38px 0 10px;

    font-size: 12px;

    color: #333;

    outline: none;
}

.input:focus {

    border-color: #2580bd;

    box-shadow:
        0 0 0 1px rgba(37,128,189,0.12);
}

.input::placeholder {

    color: #999;
}

.input-icon {

    position: absolute;

    right: 9px;

    top: 50%;

    transform: translateY(-50%);

    color: #777;

    font-size: 14px;

    pointer-events: none;
}


/* =========================================================
   LOGIN BUTTON
========================================================= */

.login-button {

    width: 100%;

    height: 37px;

    border: none;

    background: #247fbc;

    color: white;

    font-size: 12px;

    font-weight: 700;

    letter-spacing: 0.5px;

    cursor: pointer;

    margin-top: 2px;
}

.login-button:hover {

    background: #176b9f;
}

.login-button:active {

    transform: translateY(1px);
}


/* =========================================================
   HELP TEXT
========================================================= */

.login-help {

    text-align: center;

    color: #777;

    font-size: 11px;

    margin-top: 17px;

    line-height: 17px;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    position: fixed;

    left: 0;
    right: 0;
    bottom: 0;

    height: 32px;

    background: #063b68;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

    z-index: 10;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 650px) {

    .header {

        height: auto;

        min-height: 82px;

        padding: 10px 12px;

        gap: 10px;
    }

    .brand-name {

        font-size: 19px;
    }

    .brand-subtitle {

        font-size: 8px;
    }

    .logo {

        width: 45px;
        height: 45px;

        font-size: 22px;
    }

    .candidate-area {

        display: none;
    }

    .login-area {

        min-height: calc(100vh - 154px);

        padding: 25px 15px 50px;
    }

    .login-box {

        width: 100%;

        max-width: 360px;
    }
}


/* =========================================================
   VERY SMALL SCREENS
========================================================= */

@media (max-width: 400px) {

    .brand-text {

        display: none;
    }

    .brand {

        margin: auto;
    }

    .system-name {

        font-size: 14px;
    }

    .system-warning {

        font-size: 10px;
    }
}

</style>

</head>


<body>


<div class="login-page">


    <!-- =================================================
         TOP BAR
    ================================================= -->

    <div class="top-bar">

        <div class="top-home">

            <span class="home-icon">
                🏠
            </span>

            <span>
                Home
            </span>

        </div>

    </div>


    <!-- =================================================
         HEADER
    ================================================= -->

    <header class="header">


        <div class="brand">

            <div class="logo">
                M
            </div>

            <div class="brand-text">

                <div class="brand-name">
                    MODUS CBT
                </div>

                <div class="brand-subtitle">
                    EXCELLENCE IN COMPUTER BASED TESTING
                </div>

            </div>

        </div>


        <!-- Candidate information -->

        <div class="candidate-area">

            <div class="candidate-photo">
                👤
            </div>

            <div class="candidate-info">

                <div>

                    <span class="candidate-label">
                        Candidate Name
                    </span>

                    :

                    <span class="candidate-value">
                        Student Login
                    </span>

                </div>

                <div>

                    <span class="candidate-label">
                        Subject Name
                    </span>

                    :

                    <span class="candidate-value">
                        CBT Examination
                    </span>

                </div>

            </div>

        </div>

    </header>


    <!-- =================================================
         SYSTEM BAR
    ================================================= -->

    <div class="system-bar">

        <div class="system-name">
            System Name : MODUS CBT
        </div>

        <div class="system-warning">
            Candidate Login Portal &nbsp;|&nbsp;
            Please use your assigned UID and password to continue.
        </div>

    </div>


    <!-- =================================================
         LOGIN AREA
    ================================================= -->

    <main class="login-area">


        <div class="login-box">


            <div class="login-title">
                Login
            </div>


            <?php if ($error): ?>

                <div class="error-message">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                autocomplete="off"
            >


                <!-- UID -->

                <div class="form-group">

                    <label class="form-label">
                        Username
                    </label>

                    <div class="input-wrapper">

                        <input
                            type="text"
                            name="uid"
                            class="input"
                            placeholder="Enter UID"
                            maxlength="50"
                            autocomplete="off"
                            required
                        >

                        <span class="input-icon">
                            ▣
                        </span>

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label class="form-label">
                        Password
                    </label>

                    <div class="input-wrapper">

                        <input
                            type="password"
                            name="password"
                            class="input"
                            placeholder="Enter password"
                            maxlength="100"
                            autocomplete="off"
                            required
                        >

                        <span class="input-icon">
                            🔒
                        </span>

                    </div>

                </div>


                <!-- LOGIN -->

                <button
                    type="submit"
                    class="login-button"
                >
                    LOGIN
                </button>


            </form>


            <div class="login-help">

                Use the UID and password provided by your
                examination center.

            </div>


        </div>

    </main>


    <!-- =================================================
         FOOTER
    ================================================= -->

    <footer class="footer">

        © <?= date('Y') ?> MODUS CBT &nbsp; | &nbsp;
        Computer Based Testing Platform

    </footer>


</div>


</body>

</html>