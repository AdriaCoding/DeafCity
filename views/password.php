<div id="password">
    <div id="wrapper">
        <div id="gif">
            <img src="img/pegolino4.gif" alt="background">
        </div>
        <div id="form">
            <form method="POST">
                <input type="password" name="password">
                <button type="submit">DEAF.city</button>
                <?php if ($password_error): ?>
                    <div id="error">
                        <p>INCORRECT PASSWORD</p>
                    </div>
                <?php endif ?>
            </form>
        </div>
    </div>
</div>

