<?php

session_start();

require_once __DIR__ . '/includes/db.php';

if (isset($_SESSION['teacher_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (
        $name === '' ||
        $username === '' ||
        $password === '' ||
        $confirm_password === ''
    ) {

        $error = 'Please fill in all fields.';

    } elseif (strlen($name) < 2) {

        $error = 'Please enter a valid name.';

    } elseif (strlen($username) < 3) {

        $error = 'Username must contain at least 3 characters.';

    } elseif (strlen($password) < 6) {

        $error = 'Password must contain at least 6 characters.';

    } elseif ($password !== $confirm_password) {

        $error = 'Passwords do not match.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | CHECK USERNAME
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM teachers
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);

            if ($stmt->fetch()) {

                $error = 'This username is already registered.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | CREATE TEACHER
                |--------------------------------------------------------------------------
                */

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare("
                    INSERT INTO teachers
                    (
                        username,
                        password_hash,
                        name
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $username,
                    $password_hash,
                    $name
                ]);

                $success = 'Teacher account created successfully. You can now login.';
            }

        } catch (PDOException $e) {

            $error = 'Unable to create account. Please try again.';
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

    <title>MODUS CBT - Teacher Signup</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #f4f5f7;

            font-family: Arial, sans-serif;
        }

        .signup-box {

            width: 380px;

            background: white;

            padding: 35px;

            border-radius: 12px;

            box-shadow:
                0 10px 35px rgba(0,0,0,0.08);
        }

        .logo {

            text-align: center;

            font-size: 26px;

            font-weight: 700;

            margin-bottom: 8px;
        }

        .subtitle {

            text-align: center;

            color: #777;

            margin-bottom: 30px;
        }

        label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;
        }

        input {

            width: 100%;

            padding: 12px;

            margin-bottom: 18px;

            border: 1px solid #ddd;

            border-radius: 7px;

            font-size: 15px;
        }

        input:focus {

            outline: none;

            border-color: #111;
        }

        button {

            width: 100%;

            padding: 13px;

            border: none;

            border-radius: 7px;

            background: #111;

            color: white;

            font-size: 15px;

            cursor: pointer;
        }

        button:hover {

            background: #333;
        }

        .error {

            background: #ffecec;

            color: #c00;

            padding: 10px;

            border-radius: 7px;

            margin-bottom: 18px;

            font-size: 14px;
        }

        .success {

            background: #ecfdf3;

            color: #087443;

            padding: 10px;

            border-radius: 7px;

            margin-bottom: 18px;

            font-size: 14px;
        }

        .login-link {

            text-align: center;

            margin-top: 20px;

            font-size: 13px;

            color: #777;
        }

        .login-link a {

            color: #111;

            font-weight: 600;

            text-decoration: none;
        }

        .login-link a:hover {

            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="signup-box">

    <div class="logo">
        MODUS CBT
    </div>

    <div class="subtitle">
        Create Teacher Account
    </div>


    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <form method="POST">

        <label>
            Full Name
        </label>

        <input
            type="text"
            name="name"
            placeholder="Enter your full name"
            autocomplete="name"
            required
        >


        <label>
            Username
        </label>

        <input
            type="text"
            name="username"
            placeholder="Choose a username"
            autocomplete="username"
            required
        >


        <label>
            Password
        </label>

        <input
            type="password"
            name="password"
            placeholder="Minimum 6 characters"
            autocomplete="new-password"
            required
        >


        <label>
            Confirm Password
        </label>

        <input
            type="password"
            name="confirm_password"
            placeholder="Re-enter password"
            autocomplete="new-password"
            required
        >


        <button type="submit">
            Create Teacher Account
        </button>

    </form>


    <div class="login-link">

        Already have an account?

        <a href="login.php">
            Login
        </a>

    </div>

</div>

</body>

</html>