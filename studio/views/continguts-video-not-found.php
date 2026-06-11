<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vídeo no trobat — Studio</title>
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
        h1 {
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #888;
        }
        .header-nav { display: flex; gap: 1.25rem; align-items: center; }
        a.nav-link {
            font-size: 0.8rem;
            color: #555;
            text-decoration: none;
            letter-spacing: 0.05em;
        }
        a.nav-link:hover { color: #999; }
        main {
            max-width: 42rem;
            padding: 3rem 2rem;
        }
        p {
            color: #888;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        a.back-link {
            font-size: 0.85rem;
            color: #9ab8ff;
            text-decoration: none;
        }
        a.back-link:hover { color: #c0d4ff; }
    </style>
</head>
<body>
<header>
    <h1>Studio</h1>
    <div class="header-nav">
        <a class="nav-link" href="./">← Catàleg</a>
        <a class="nav-link" href="?action=logout">Tanca la sessió</a>
    </div>
</header>
<main>
    <p>Vídeo no trobat al catàleg.</p>
    <a class="back-link" href="./">← Torna al catàleg</a>
</main>
</body>
</html>
