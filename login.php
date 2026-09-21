<?php

session_start();

require_once __DIR__ . '/includes/db.php';

if (isset($_SESSION['teacher_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Please enter username and password.';

    } else {

        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, name
            FROM teachers
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);

        $teacher = $stmt->fetch();

        if ($teacher && password_verify($password, $teacher['password_hash'])) {

            session_regenerate_id(true);

            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            $_SESSION['teacher_username'] = $teacher['username'];

            header('Location: admin/dashboard.php');
            exit;

        } else {

            $error = 'Invalid username or password.';

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MODUS CBT - Login</title>

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

        .login-box {
            width: 360px;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
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
    </style>
</head>

<body>

<div class="login-box">

    <div class="logo">MODUS CBT</div>

    <div class="subtitle">
        Teacher Login
    </div>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Username</label>

        <input
            type="text"
            name="username"
            autocomplete="username"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Login
        </button>

    </form>

</div>

</body>
</html>