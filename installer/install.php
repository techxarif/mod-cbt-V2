<?php

session_start();

$lock_file = __DIR__ . '/../storage/installed.lock';

if (file_exists($lock_file)) {
    header('Location: ../index.php');
    exit;
}


$error = '';

$step = 1;

$db_host = 'localhost';
$db_name = 'md_cbt';
$db_user = 'root';
$db_pass = '';


/*
|--------------------------------------------------------------------------
| Process installation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? 'md_cbt');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';

    $admin_username =
        trim($_POST['admin_username'] ?? '');

    $admin_name =
        trim($_POST['admin_name'] ?? '');

    $admin_password =
        $_POST['admin_password'] ?? '';



    /*
    |--------------------------------------------------------------------------
    | Basic validation
    |--------------------------------------------------------------------------
    */

    if (
        $db_host === '' ||
        $db_name === '' ||
        $db_user === ''
    ) {

        $error =
            'Database host, database name and username are required.';

    } elseif (
        $admin_username === '' ||
        $admin_name === '' ||
        $admin_password === ''
    ) {

        $error =
            'Administrator details are required.';

    } elseif (
        strlen($admin_password) < 6
    ) {

        $error =
            'Administrator password must contain at least 6 characters.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | Connect to MySQL server
        |--------------------------------------------------------------------------
        */

        try {

            $server_pdo = new PDO(
                "mysql:host={$db_host};charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,

                    PDO::ATTR_EMULATE_PREPARES =>
                        false
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | Create database
            |--------------------------------------------------------------------------
            */

            $safe_db_name =
                preg_replace(
                    '/[^a-zA-Z0-9_]/',
                    '',
                    $db_name
                );

            if ($safe_db_name === '') {

                throw new Exception(
                    'Invalid database name.'
                );
            }


            $server_pdo->exec(
                "CREATE DATABASE IF NOT EXISTS
                 `{$safe_db_name}`
                 CHARACTER SET utf8mb4
                 COLLATE utf8mb4_unicode_ci"
            );


            /*
            |--------------------------------------------------------------------------
            | Connect to new database
            |--------------------------------------------------------------------------
            */

            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$safe_db_name};charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,

                    PDO::ATTR_EMULATE_PREPARES =>
                        false
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | Read database schema
            |--------------------------------------------------------------------------
            */

            $database_file =
                __DIR__ . '/../database.sql';


            if (!file_exists($database_file)) {

                throw new Exception(
                    'database.sql was not found.'
                );
            }


            $sql =
                file_get_contents(
                    $database_file
                );


            /*
            |--------------------------------------------------------------------------
            | Remove CREATE DATABASE / USE statements
            |--------------------------------------------------------------------------
            */

            $sql = preg_replace(
                '/CREATE\s+DATABASE.*?;/is',
                '',
                $sql
            );

            $sql = preg_replace(
                '/USE\s+[`a-zA-Z0-9_-]+\\s*;/i',
                '',
                $sql
            );


            /*
            |--------------------------------------------------------------------------
            | Import database
            |--------------------------------------------------------------------------
            */

            $pdo->exec($sql);


            /*
            |--------------------------------------------------------------------------
            | Create administrator
            |--------------------------------------------------------------------------
            */

            $password_hash =
                password_hash(
                    $admin_password,
                    PASSWORD_DEFAULT
                );


            /*
             * Remove existing admin username if present.
             * This keeps installation repeatable.
             */

            $stmt = $pdo->prepare("
                SELECT id
                FROM teachers
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([
                $admin_username
            ]);

            $existing_admin =
                $stmt->fetch();


            if ($existing_admin) {

                $stmt = $pdo->prepare("
                    UPDATE teachers
                    SET
                        password_hash = ?,
                        name = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $password_hash,
                    $admin_name,
                    $existing_admin['id']
                ]);

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO teachers
                    (
                        username,
                        password_hash,
                        name
                    )
                    VALUES
                    (?, ?, ?)
                ");

                $stmt->execute([
                    $admin_username,
                    $password_hash,
                    $admin_name
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Create required directories
            |--------------------------------------------------------------------------
            */

            $directories = [

                __DIR__ . '/../storage',

                __DIR__ . '/../uploads',

                __DIR__ . '/../uploads/questions',

                __DIR__ . '/../results',

                __DIR__ . '/../results/pdf'

            ];


            foreach ($directories as $directory) {

                if (!is_dir($directory)) {

                    mkdir(
                        $directory,
                        0777,
                        true
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Generate config.php
            |--------------------------------------------------------------------------
            */

            $config_content = <<<PHP
<?php

define('DB_HOST', {$this->phpString($db_host)});
define('DB_NAME', {$this->phpString($safe_db_name)});
define('DB_USER', {$this->phpString($db_user)});
define('DB_PASS', {$this->phpString($db_pass)});

define('APP_NAME', 'MODUS CBT');

define(
    'APP_URL',
    'http://' . (\$_SERVER['HTTP_HOST'] ?? 'localhost') . '/md-cbt/'
);

date_default_timezone_set('Asia/Kolkata');

PHP;


            /*
             * The heredoc above cannot call $this because this is
             * not a class. Generate config differently below.
             */

            $config_content =
                "<?php\n\n" .

                "define('DB_HOST', " .
                var_export($db_host, true) .
                ");\n\n" .

                "define('DB_NAME', " .
                var_export($safe_db_name, true) .
                ");\n\n" .

                "define('DB_USER', " .
                var_export($db_user, true) .
                ");\n\n" .

                "define('DB_PASS', " .
                var_export($db_pass, true) .
                ");\n\n" .

                "define('APP_NAME', 'MODUS CBT');\n\n" .

                "define(\n" .
                "    'APP_URL',\n" .
                "    'http://' . " .
                "(\$_SERVER['HTTP_HOST'] ?? 'localhost') . " .
                "'/md-cbt/'\n" .
                ");\n\n" .

                "date_default_timezone_set('Asia/Kolkata');\n";


            $config_file =
                __DIR__ . '/../config.php';


            if (
                file_put_contents(
                    $config_file,
                    $config_content
                ) === false
            ) {

                throw new Exception(
                    'Could not create config.php. Check folder permissions.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Create installation lock
            |--------------------------------------------------------------------------
            */

            $lock_content =
                "MODUS CBT INSTALLED\n" .
                "Installed: " .
                date('Y-m-d H:i:s') .
                "\n";


            file_put_contents(
                $lock_file,
                $lock_content
            );


            /*
            |--------------------------------------------------------------------------
            | Save session data for complete page
            |--------------------------------------------------------------------------
            */

            $_SESSION['installation_complete'] = true;

            $_SESSION['installed_admin'] =
                $admin_username;

            $_SESSION['installed_database'] =
                $safe_db_name;


            header(
                'Location: complete.php'
            );

            exit;


        } catch (Throwable $e) {

            $error =
                'Installation failed: ' .
                $e->getMessage();

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

<title>Install MODUS CBT</title>

<link
    rel="stylesheet"
    href="style.css"
>

</head>

<body>

<div class="installer">

    <div class="logo">
        M
    </div>

    <h1>
        MODUS CBT
    </h1>

    <p class="subtitle">
        Installation Wizard
    </p>


    <div class="step">

        <span class="done">
            ✓
        </span>

        <span>
            System
        </span>

        <i></i>

        <span class="active">
            2
        </span>

        <span>
            Database
        </span>

        <i></i>

        <span>
            3
        </span>

        <span>
            Admin
        </span>

    </div>


    <div class="card">

        <h2>
            Database & Administrator
        </h2>

        <p class="description">
            Enter the MySQL details and create the first MODUS CBT administrator.
        </p>


        <?php if ($error): ?>

            <div class="error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            autocomplete="off"
        >


            <div class="section-title">
                MySQL Database
            </div>


            <div class="form-grid">

                <div class="field">

                    <label>
                        Database Host
                    </label>

                    <input
                        type="text"
                        name="db_host"
                        value="<?= htmlspecialchars($db_host) ?>"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Database Name
                    </label>

                    <input
                        type="text"
                        name="db_name"
                        value="<?= htmlspecialchars($db_name) ?>"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        MySQL Username
                    </label>

                    <input
                        type="text"
                        name="db_user"
                        value="<?= htmlspecialchars($db_user) ?>"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        MySQL Password
                    </label>

                    <input
                        type="password"
                        name="db_pass"
                        value=""
                    >

                </div>

            </div>


            <div class="section-title">
                Administrator Account
            </div>


            <div class="form-grid">

                <div class="field">

                    <label>
                        Administrator Name
                    </label>

                    <input
                        type="text"
                        name="admin_name"
                        placeholder="Administrator"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Username
                    </label>

                    <input
                        type="text"
                        name="admin_username"
                        placeholder="admin"
                        required
                    >

                </div>


                <div class="field full">

                    <label>
                        Password
                    </label>

                    <input
                        type="password"
                        name="admin_password"
                        placeholder="Minimum 6 characters"
                        required
                    >

                </div>

            </div>


            <div class="info-box">

                The installer will automatically:

                <ul>

                    <li>
                        Create the MODUS CBT database
                    </li>

                    <li>
                        Import the database schema
                    </li>

                    <li>
                        Create the administrator
                    </li>

                    <li>
                        Generate config.php
                    </li>

                    <li>
                        Create required folders
                    </li>

                    <li>
                        Lock the installer after installation
                    </li>

                </ul>

            </div>


            <button
                type="submit"
                class="button"
            >
                Install MODUS CBT →
            </button>


        </form>

    </div>


    <div class="footer">
        MODUS CBT Installer
    </div>

</div>

</body>

</html>