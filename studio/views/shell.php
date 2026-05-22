<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio — DEAF.city</title>
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
        a.logout {
            font-size: 0.8rem;
            color: #555;
            text-decoration: none;
            letter-spacing: 0.05em;
        }
        a.logout:hover { color: #999; }
        main {
            padding: 3rem 2rem;
            color: #444;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <header>
        <h1>Studio</h1>
        <a class="logout" href="?action=logout">Log out</a>
    </header>
    <main>
        <!-- Studio features will be added here -->
    </main>
</body>
</html>
