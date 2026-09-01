<!DOCTYPE html>
<html lang="ca">
<head>
<?php
$colorsPath = __DIR__ . '/../css/colors.css';
$colorsVersion = is_file($colorsPath) ? (string) filemtime($colorsPath) : '1';
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = [];
}
$csrfToken = \Studio\Csrf::issueToken($_SESSION);
?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/colors.css?v=<?= htmlspecialchars($colorsVersion, ENT_QUOTES) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio — DEAF.city</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--studio-bg);
            font-family: var(--studio-font);
            color: var(--studio-text);
        }
        .gate {
            width: 100%;
            max-width: 320px;
            padding: 2.5rem 2rem;
        }
        h1 {
            font-size: 1.1rem;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-bottom: 2rem;
            color: var(--studio-text-muted);
        }
        .error {
            font-size: 0.85rem;
            color: var(--studio-danger);
            margin-bottom: 1rem;
        }
        input[type="password"] {
            display: block;
            width: 100%;
            padding: 0.65rem 0.75rem;
            background: var(--studio-input-bg);
            border: 1px solid var(--studio-border);
            border-radius: 4px;
            color: var(--studio-text);
            font-size: 1rem;
            margin-bottom: 0.75rem;
            outline: none;
        }
        input[type="password"]:focus { border-color: var(--studio-text-muted); }
        button {
            display: block;
            width: 100%;
            padding: 0.65rem;
            background: var(--studio-btn-solid-bg);
            color: var(--studio-btn-solid-text);
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.05em;
        }
        button:hover { background: var(--studio-btn-solid-bg-hover); }
    </style>
</head>
<body>
    <div class="gate">
        <h1>Studio</h1>
        <?php if (!empty($lockoutMessage)): ?>
            <p class="error"><?= htmlspecialchars($lockoutMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif ($showError): ?>
            <p class="error">Contrasenya incorrecta.</p>
        <?php endif; ?>
        <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <input type="password" name="password" placeholder="Contrasenya" autofocus autocomplete="current-password">
            <button type="submit">Entra</button>
        </form>
    </div>
</body>
</html>
