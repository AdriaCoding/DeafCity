<!DOCTYPE html>
<html lang="ca">
<head>
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
            background: #0a0a0a;
            font-family: system-ui, sans-serif;
            color: #e0e0e0;
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
            color: #888;
        }
        .error {
            font-size: 0.85rem;
            color: #e05555;
            margin-bottom: 1rem;
        }
        input[type="password"] {
            display: block;
            width: 100%;
            padding: 0.65rem 0.75rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 1rem;
            margin-bottom: 0.75rem;
            outline: none;
        }
        input[type="password"]:focus { border-color: #555; }
        button {
            display: block;
            width: 100%;
            padding: 0.65rem;
            background: #e0e0e0;
            color: #0a0a0a;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.05em;
        }
        button:hover { background: #fff; }
    </style>
</head>
<body>
    <div class="gate">
        <h1>Studio</h1>
        <?php if ($showError): ?>
            <p class="error">Contrasenya incorrecta.</p>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="password" name="password" placeholder="Contrasenya" autofocus autocomplete="current-password">
            <button type="submit">Entra</button>
        </form>
    </div>
</body>
</html>
