<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($stepLabel) ?> — Studio</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #0a0a0a;
            font-family: system-ui, sans-serif;
            color: #e0e0e0;
        }
        main { padding: 3rem 2rem; max-width: 560px; color: #888; line-height: 1.6; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/partials/studio-header.php'; ?>
    <main>
        <h2 style="color:#e0e0e0;font-weight:500;margin-bottom:0.75rem;"><?= htmlspecialchars($stepLabel) ?></h2>
        <p>Aquest pas del procés encara no està implementat. La feina i el fitxer de subtítols esborrany estan desats — podeu tornar al catàleg quan aquesta funcionalitat estigui disponible.</p>
    </main>
</body>
</html>
