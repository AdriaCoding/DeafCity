<!DOCTYPE html>
<html lang="en">
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
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 2rem;
            border-bottom: 1px solid #1e1e1e;
        }
        h1 { font-size: 0.95rem; font-weight: 500; letter-spacing: 0.15em; text-transform: uppercase; color: #888; }
        a { font-size: 0.8rem; color: #555; text-decoration: none; }
        a:hover { color: #999; }
        main { padding: 3rem 2rem; max-width: 560px; color: #888; line-height: 1.6; }
    </style>
</head>
<body>
    <header>
        <h1>Studio</h1>
        <a href="./">← Studio home</a>
    </header>
    <main>
        <h2 style="color:#e0e0e0;font-weight:500;margin-bottom:0.75rem;"><?= htmlspecialchars($stepLabel) ?></h2>
        <p>This pipeline step is not built yet. Your job and draft subtitle file are saved — return from the Studio home when this slice ships.</p>
    </main>
</body>
</html>
