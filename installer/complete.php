<?php

session_start();

if (
    empty($_SESSION['installation_complete'])
) {
    header('Location: index.php');
    exit;
}

$admin =
    $_SESSION['installed_admin'] ?? 'admin';

$db =
    $_SESSION['installed_database'] ?? 'md_cbt';

unset(
    $_SESSION['installation_complete'],
    $_SESSION['installed_admin'],
    $_SESSION['installed_database']
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>MODUS CBT Installed</title>

<link
    rel="stylesheet"
    href="style.css"
>

</head>

<body>

<div class="installer">

    <div class="success-icon">
        ✓
    </div>

    <h1>
        MODUS CBT
    </h1>

    <p class="subtitle">
        Installation Complete
    </p>


    <div class="card success-card">

        <h2>
            Installation Successful
        </h2>

        <p class="description">
            MODUS CBT has been installed successfully on this computer.
        </p>


        <div class="success-list">

            <div>
                <span>✓</span>
                Database created
            </div>

            <div>
                <span>✓</span>
                Database tables imported
            </div>

            <div>
                <span>✓</span>
                Administrator created
            </div>

            <div>
                <span>✓</span>
                Configuration generated
            </div>

            <div>
                <span>✓</span>
                Required folders created
            </div>

            <div>
                <span>✓</span>
                Installer locked
            </div>

        </div>


        <div class="credentials">

            <div class="credential-row">

                <span>
                    Administrator
                </span>

                <strong>
                    <?= htmlspecialchars($admin) ?>
                </strong>

            </div>


            <div class="credential-row">

                <span>
                    Database
                </span>

                <strong>
                    <?= htmlspecialchars($db) ?>
                </strong>

            </div>

        </div>


        <a
            href="../login.php"
            class="button"
        >
            Open MODUS CBT →
        </a>

    </div>


    <div class="footer">
        MODUS CBT
    </div>

</div>

</body>

</html>