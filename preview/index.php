<?php
// PROTOTYPE — three layout variants for the DEAF.city preview page. Throwaway code.
// Question: What should the full page look like (player + about + credits)?
// Visit /preview/?variant=A  /preview/?variant=B  /preview/?variant=C

$variant = strtoupper(trim(isset($_GET['variant']) ? $_GET['variant'] : 'A'));
if (!in_array($variant, ['A', 'B', 'C'])) $variant = 'A';
$variantLabels = ['A' => 'Column', 'B' => 'Sidebar', 'C' => 'Panels'];

require __DIR__ . '/lib/videos_catalog.php';
$catalogJsonPath  = dirname(__DIR__) . '/data/catalog.json';
$studioConfigPath = dirname(__DIR__) . '/data/studio-config.json';
$catalog  = vpc_load_videos_catalog($catalogJsonPath);
$playlist = $catalog ? vpc_vimeo_playlist_all_from_catalog($catalog) : [];
$signLanguageOptions = $catalog ? vpc_sign_language_options_from_catalog($catalog, $studioConfigPath) : [];
$defaultSignLanguage = isset($signLanguageOptions[0]['value']) ? $signLanguageOptions[0]['value'] : '';
$vpc = null;
if (count($playlist) > 0) {
    $vpc = ['instance_id' => 'preview-playlist-demo', 'playlist' => $playlist];
    if (count($signLanguageOptions) > 0) {
        $vpc['sign_language_filter'] = ['options' => $signLanguageOptions, 'default' => $defaultSignLanguage];
    }
}

$about = [
    ['id' => 'deaf-city',        'title' => 'DEAF.city',        'text' => 'The Deaf community lives in a world that often overlooks its natural way of communicating: sign language. While many Deaf people rely on lip-reading, most hearing individuals don\'t understand sign languages, resulting in isolation and invisibility. For many, the rare appearance of a sign-language interpreter on television is the only acknowledgment of Deaf existence.'],
    ['id' => 'breaking-silence', 'title' => 'Breaking Silence', 'text' => 'DEAF.city uses visual-gestural humor to challenge hearing indifference, to celebrate Deaf culture and connect communities. Participants share stories and jokes, turning everyday misunderstandings into moments of laughter and reflection. Humor becomes a bridge between Deaf and hearing audiences, empowering storytellers to reclaim visibility and promote inclusion.'],
    ['id' => 'dissemination',    'title' => 'Dissemination',    'text' => 'The project spreads across an open-access video repository with multi-screen installations in museums, art centers and public spaces, using TVs, LED panels, or projections. The videos—paired with participant-generated sounds such as vocalizations, claps, and finger snaps—create a rich, engaging soundscape.'],
    ['id' => 'timeline',         'title' => 'Timeline',         'text' => 'Since its launch in Valencia in 2020, DEAF.city has expanded to Mexico City in 2021, and in 2023 to Bilbao and São Paulo. Twenty-six participants have shared humorous monologues in Spanish, Mexican, and Brazilian Sign Languages. In 2026, the project will grow to Marseille, Rome, Athens, Istanbul, Tunis, Algiers, and Barcelona, incorporating French, Italian, Greek, Turkish, Tunisian, Algerian, and Catalan sign languages.'],
    ['id' => 'silent-eloquence', 'title' => 'Silent Eloquence', 'text' => 'By blending humor, art, and activism, DEAF.city makes Deaf culture visible, fosters understanding, and builds bridges between Deaf and hearing communities worldwide.'],
];

$logos = [
    ['src' => '/img/ministerio.png',   'alt' => 'Ministerio de Cultura de España'],
    ['src' => '/img/ecausp2.png',      'alt' => 'ECA-USP'],
    ['src' => '/img/bilbaomuseoa2.png','alt' => 'Museo de Bellas Artes de Bilbao'],
    ['src' => '/img/uam2.png',         'alt' => 'UAM'],
    ['src' => '/img/cccc.png',         'alt' => 'Centre del Carme Cultura Contemporània'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DEAF.city</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if ($variant === 'A'): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Mulish:wght@400;600&display=swap" rel="stylesheet">
    <?php elseif ($variant === 'B'): ?>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
    <?php else: ?>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Jost:wght@300;400&display=swap" rel="stylesheet">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/preview/components/vimeo_caption_player.css?v=14">
    <style>
        /* ── Shared reset ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { background: #fff; min-height: 100%; }
        a { color: inherit; }

        .preview-block { margin-bottom: 0; }

        /* ── Full-bleed trio video (ultrawide ~5.3:1) ── */
        .trio-wrap {
            position: relative;
            width: 100%;
            padding-bottom: 18.95%;
            background: #000;
            overflow: hidden;
        }
        .trio-wrap iframe {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: 0;
        }

        /* ── Responsive 16:9 embed ── */
        .embed-16x9 {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
        }
        .embed-16x9 iframe {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: 0;
        }

        /* ── Prototype switcher ── */
        .proto-bar {
            position: fixed;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            align-items: stretch;
            background: #111;
            color: #fff;
            border-radius: 9999px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.5);
            overflow: hidden;
            font-family: monospace;
            font-size: 0.75rem;
            user-select: none;
            white-space: nowrap;
        }
        .proto-bar button {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            padding: 0.55rem 1rem;
            font-size: 1rem;
            line-height: 1;
            transition: background 0.1s;
            display: flex;
            align-items: center;
        }
        .proto-bar button:hover { background: rgba(255,255,255,0.15); }
        .proto-bar .proto-key {
            background: #007800;
            padding: 0 0.75rem;
            font-weight: bold;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
        }
        .proto-bar .proto-name {
            padding: 0 0.9rem 0 0.5rem;
            display: flex;
            align-items: center;
            color: #aaa;
            letter-spacing: 0.04em;
        }

<?php if ($variant === 'A'): ?>
        /* ════════════════════════════
           A — Editorial Column
           Cormorant Garamond + Mulish
           ════════════════════════════ */
        body { font-family: 'Mulish', sans-serif; color: #111; }

        .vA-about {
            max-width: 720px;
            margin: 0 auto;
            padding: 5rem 2rem 1rem;
        }
        .vA-intro-video { margin-bottom: 4.5rem; }

        .vA-section { margin-bottom: 3.5rem; }
        .vA-eyebrow {
            font-size: 0.625rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #007800;
            margin-bottom: 0.6rem;
        }
        .vA-heading {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 4vw, 2.875rem);
            font-weight: 600;
            line-height: 1.1;
            color: #111;
            margin-bottom: 1rem;
        }
        .vA-text {
            font-size: clamp(0.9375rem, 1.4vw, 1.0625rem);
            line-height: 1.85;
            color: #333;
        }

        /* footer / credits */
        .vA-footer {
            max-width: 720px;
            margin: 3rem auto 0;
            padding: 3rem 2rem 5rem;
            border-top: 1px solid #e8e8e8;
        }
        .vA-byline {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1875rem;
            font-weight: 600;
            color: #111;
            margin-bottom: 2.5rem;
        }
        .vA-byline a { color: #007800; text-decoration: none; }
        .vA-byline a:hover { text-decoration: underline; }
        .vA-credits {
            font-size: 0.75rem;
            line-height: 1.75;
            color: #666;
        }
        .vA-credits p { margin-bottom: 1.125rem; }
        .vA-credits b { color: #444; }
        .vA-credits a { color: #007800; text-decoration: none; }
        .vA-credits a:hover { text-decoration: underline; }
        .vA-logos {
            margin-top: 2.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem 2rem;
            align-items: center;
        }
        .vA-logos img {
            height: 32px;
            width: auto;
            opacity: 0.5;
            transition: opacity 0.2s;
        }
        .vA-logos img:hover { opacity: 1; }

<?php elseif ($variant === 'B'): ?>
        /* ════════════════════════════
           B — Sidebar
           DM Serif Display + DM Sans
           ════════════════════════════ */
        body { font-family: 'DM Sans', sans-serif; color: #111; }

        .vB-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            max-width: 1280px;
            margin: 0 auto;
            padding: 4rem 2rem;
            gap: 0 4rem;
            align-items: start;
        }
        @media (max-width: 860px) {
            .vB-layout { grid-template-columns: 1fr; gap: 2.5rem 0; }
            .vB-sidebar { position: static !important; }
            .vB-content { border-left: none; padding-left: 0; }
        }

        .vB-sidebar {
            position: sticky;
            top: 2rem;
        }
        .vB-sidebar-video { margin-bottom: 1.75rem; }
        .vB-nav {
            display: flex;
            flex-direction: column;
            border-top: 1px solid #eee;
        }
        .vB-nav-link {
            font-family: 'DM Serif Display', serif;
            font-size: 0.9375rem;
            color: #bbb;
            text-decoration: none;
            padding: 0.6rem 0;
            border-bottom: 1px solid #eee;
            transition: color 0.15s;
        }
        .vB-nav-link:hover { color: #333; }
        .vB-nav-link.active { color: #007800; font-style: italic; }

        .vB-content {
            padding-left: 2rem;
            border-left: 1px solid #eee;
        }
        .vB-section { margin-bottom: 4rem; }
        .vB-section:last-child { margin-bottom: 0; }
        .vB-heading {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(2rem, 3.5vw, 2.75rem);
            line-height: 1.15;
            color: #111;
            margin-bottom: 1.25rem;
        }
        .vB-section p {
            font-size: 1.0625rem;
            line-height: 1.85;
            color: #333;
            font-weight: 300;
        }

        /* footer / credits */
        .vB-footer {
            background: #f9f9f9;
            border-top: 1px solid #eee;
        }
        .vB-footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 3rem 2rem 4rem;
        }
        .vB-footer-heading {
            font-family: 'DM Serif Display', serif;
            font-size: 1.25rem;
            color: #333;
            margin-bottom: 2rem;
        }
        .vB-credits {
            font-size: 0.75rem;
            line-height: 1.75;
            color: #666;
            columns: 2 300px;
            column-gap: 2.5rem;
        }
        .vB-credits p { margin-bottom: 1rem; break-inside: avoid; }
        .vB-credits b { color: #444; font-weight: 500; }
        .vB-credits a { color: #007800; text-decoration: none; }
        .vB-credits a:hover { text-decoration: underline; }
        .vB-logos {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem 2rem;
            align-items: center;
        }
        .vB-logos img {
            height: 32px;
            width: auto;
            opacity: 0.5;
            transition: opacity 0.2s;
        }
        .vB-logos img:hover { opacity: 1; }
        .vB-byline {
            margin-top: 1.75rem;
            font-size: 0.8125rem;
            color: #888;
        }
        .vB-byline a { color: #007800; text-decoration: none; }
        .vB-byline a:hover { text-decoration: underline; }

<?php else: ?>
        /* ════════════════════════════
           C — Panels
           Syne + Jost
           ════════════════════════════ */
        body { font-family: 'Jost', sans-serif; color: #111; }

        .vC-section {
            display: grid;
            grid-template-columns: 2fr 3fr;
            border-bottom: 1px solid #e8e8e8;
        }
        @media (max-width: 680px) {
            .vC-section { grid-template-columns: 1fr; }
            .vC-left { padding: 2.5rem 1.5rem 1.25rem; border-right: none; border-bottom: 1px solid #e8e8e8; }
            .vC-right { padding: 1.25rem 1.5rem 2.5rem; }
        }
        .vC-left {
            display: flex;
            align-items: center;
            padding: 3.5rem 2.5rem;
            border-right: 1px solid #e8e8e8;
        }
        .vC-heading {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: clamp(2rem, 5vw, 3.875rem);
            line-height: 1;
            color: #007800;
            letter-spacing: -0.025em;
        }
        .vC-right {
            display: flex;
            align-items: center;
            padding: 3.5rem 3rem 3.5rem 2.5rem;
        }
        .vC-right p {
            font-size: clamp(0.9375rem, 1.3vw, 1.0625rem);
            line-height: 1.85;
            font-weight: 300;
            color: #333;
        }

        /* deafcity video inset between sections */
        .vC-video-panel {
            background: #f6f6f6;
            padding: 3rem;
            border-bottom: 1px solid #e8e8e8;
        }
        .vC-video-inner { max-width: 860px; margin: 0 auto; }
        .vC-video-eyebrow {
            font-family: 'Syne', sans-serif;
            font-size: 0.625rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #007800;
            margin-bottom: 1rem;
        }

        /* footer / credits */
        .vC-footer { background: #111; color: #aaa; }
        .vC-footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2.5rem 2rem 3.5rem;
        }
        .vC-byline {
            font-family: 'Syne', sans-serif;
            font-size: 0.75rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #fff;
            margin-bottom: 1.75rem;
        }
        .vC-byline a { color: #5fbb5f; text-decoration: none; }
        .vC-byline a:hover { text-decoration: underline; }
        .vC-credits {
            font-size: 0.6875rem;
            line-height: 1.65;
            color: #555;
            columns: 2 280px;
            column-gap: 2rem;
        }
        .vC-credits p { margin-bottom: 0.75rem; break-inside: avoid; }
        .vC-credits b { color: #888; font-weight: 400; }
        .vC-credits a { color: #666; text-decoration: none; }
        .vC-credits a:hover { color: #aaa; }
        .vC-logos {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem 1.5rem;
            align-items: center;
        }
        .vC-logos img {
            height: 26px;
            width: auto;
            opacity: 0.25;
            transition: opacity 0.2s;
        }
        .vC-logos img:hover { opacity: 0.8; }
<?php endif; ?>
    </style>
</head>
<body>

<!-- ── Video player (unchanged) ── -->
<div class="preview-block">
<?php if ($vpc !== null): ?>
    <?php require __DIR__ . '/components/vimeo_caption_player.php'; ?>
<?php else: ?>
    <p style="font-family:sans-serif;padding:1rem;color:#666;">
        No playlist loaded. Check that <code>data/catalog.json</code> exists.
    </p>
<?php endif; ?>
</div>

<?php if ($variant === 'A'): ?>
<!-- ════════════════════════════════════════════
     VARIANT A — Editorial Column
     ════════════════════════════════════════════ -->

<div class="vA-about">
    <div class="vA-intro-video">
        <div class="embed-16x9">
            <iframe src="https://player.vimeo.com/video/584475715?dnt=1&title=0&byline=0&portrait=0"
                    allow="fullscreen" allowfullscreen title="DEAF.city"></iframe>
        </div>
    </div>
    <?php foreach ($about as $s): ?>
    <div class="vA-section" id="<?= htmlspecialchars($s['id']) ?>">
        <div class="vA-eyebrow">About</div>
        <h2 class="vA-heading"><?= htmlspecialchars($s['title']) ?></h2>
        <p class="vA-text"><?= htmlspecialchars($s['text']) ?></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="trio-wrap">
    <iframe src="https://player.vimeo.com/video/1129809829?dnt=1&title=0&byline=0&portrait=0"
            allow="fullscreen" allowfullscreen title="DEAF.city participants"></iframe>
</div>

<footer class="vA-footer">
    <p class="vA-byline">
        DEAF.city is a project by <a href="https://www.antoniabad.info" target="_blank">Antoni Abad</a>
        &nbsp;&nbsp;<a href="https://www.instagram.com/antoni__abad/" target="_blank">Contact</a>
    </p>
    <div class="vA-credits">
        <p><b>2026 Marseille, Roma, Tunis, Algiers and Barcelona</b> — Supported by <a href="https://www.cultura.gob.es/cultura/mc/bellasartes/portada.html" target="_blank">Ministerio de Cultura de España, Dirección General de Patrimonio Cultural y Bellas Artes</a></p>
        <p><b>2026 Alger — <a href="https://en.wikipedia.org/wiki/Algerian_Sign_Language" target="_blank">*LSA Algerian Sign Language</a></b><br>
        Participants: Atifa Kaizra, Hamida Taleb, Hassen Djouad, Mahieddine Charrad, Mhamed Lamine Said, Mustapha Iskeur — Interpreter: Hamid Fadhel — Coordination: Shahinez Guir — Collaboration: <a href="https://jiser.org/" target="_blank">Jiser Reflexions Mediterrànies</a></p>
        <p><b>2026 Roma — <a href="https://en.wikipedia.org/wiki/Italian_Sign_Language" target="_blank">*LIS Lingua dei Segni Italiana</a></b><br>
        Participants: Carolina Ambrosio, Lorenzo Laudo, Massimo Paletta, Olivier Fidalgo, Paula Severi, Serena Rosaria Conte — Interpreter: Giada Santini — Coordination: Lorenzo Laudo — Collaboration: <a href="https://www.accademiaspagna.org/" target="_blank">Academia de España en Roma</a></p>
        <p><b>2026 Marseille — <a href="https://en.wikipedia.org/wiki/French_Sign_Language" target="_blank">*LSF Langue des Signes Française</a></b><br>
        Participants: Alysone Fecil, Hugo Gathier, Lola Colin — Interpreter: Julie Klène — Coordination: Franca Trovato — Collaboration: <a href="https://www.beauxartsdemarseille.fr/lecole-ses-engagements/nous-connaitre/pisourde/" target="_blank">Program Pisourd-e / Beaux-Arts Marseille</a></p>
        <p><b>2023 São Paulo — <a href="https://en.wikipedia.org/wiki/Brazilian_Sign_Language" target="_blank">*LIBRAS Língua Brasileira de Sinais</a></b><br>
        Supported by <a href="https://www.eca.usp.br/institucional" target="_blank">ECA-USP</a> — Participants: Ana Laura Rocha Vendrame, Edvaldo Carmo dos Santos, Fabio de Sa e Silva, Fernanda Oliveira Santos, Idenilson Batista Souza, Vitória Lopes Porto Justa — Interpreter: Karina Regina da Silva Oliveira — Coordination: Isart Santos</p>
        <p><b>2023 Bilbao — <a href="https://en.wikipedia.org/wiki/Spanish_Sign_Language" target="_blank">*LSE Lengua de Signos Española</a></b><br>
        Supported by <a href="https://bilbaomuseoa.eus/en/exhibitions/multiverso-3/" target="_blank">Museo de Bellas Artes de Bilbao</a> — Participants: Amaia Mejía, Aitor Bedialauneta, Eduardo Amorós, Iñaki Montero — Interpreter: Janire Martín — Collaboration: <a href="https://euskal-gorrak.org/" target="_blank">Euskal Gorrak</a>, <a href="https://bilbaoarte.org/" target="_blank">BilboArte Fundazioa</a></p>
        <p><b>2021 Mexico City — <a href="https://en.wikipedia.org/wiki/Mexican_Sign_Language" target="_blank">*LSM Lengua de Señas Mexicana</a></b><br>
        Supported by <a href="http://www.ler.uam.mx" target="_blank">UAM Lerma</a> y <a href="http://www.cua.uam.mx" target="_blank">Cuajimalpa</a> — Participants: Gustavo Méndez, Indira López Cardona, Ixchel Solís García, Luis Alberto Valencia Beltrán, Luis Eduardo Méndez, Martha Cristina de Díaz, Mauricio Iván Álvarez García, Miguel Díaz, Verónica Álvarez — Interpreters: Ixchel Solís García, Daniela Vite</p>
        <p><b>2020 Valencia — <a href="https://en.wikipedia.org/wiki/Spanish_Sign_Language" target="_blank">*LSE Lengua de Signos Española</a></b><br>
        Supported by <a href="https://www.consorcimuseus.gva.es/centro-del-carmen/exposicion/apertura-antoni-abad-deaf-city/?lang=es" target="_blank">Centre del Carme Cultura Contemporània</a> — Collaboration: <a href="https://www.fesord.org/val/inicio/" target="_blank">Federació De Persones Sordes CV</a>, <a href="https://www.lasnaves.com/?lang=es" target="_blank">Las Naves</a> — Participants: Aurora López, David Riutort, Daniel Bautista, Josep Antoni Gimeno, Mónica Díez, Pepa Burgal, Sonia Piqueras — Interpreter: Carmen Tos</p>
    </div>
    <div class="vA-logos">
        <?php foreach ($logos as $logo): ?>
        <img src="<?= htmlspecialchars($logo['src']) ?>" alt="<?= htmlspecialchars($logo['alt']) ?>">
        <?php endforeach; ?>
    </div>
</footer>

<?php elseif ($variant === 'B'): ?>
<!-- ════════════════════════════════════════════
     VARIANT B — Sidebar
     ════════════════════════════════════════════ -->

<div class="vB-layout">
    <aside class="vB-sidebar">
        <div class="vB-sidebar-video">
            <div class="embed-16x9">
                <iframe src="https://player.vimeo.com/video/584475715?dnt=1&title=0&byline=0&portrait=0"
                        allow="fullscreen" allowfullscreen title="DEAF.city"></iframe>
            </div>
        </div>
        <nav class="vB-nav" aria-label="About sections">
            <?php foreach ($about as $s): ?>
            <a class="vB-nav-link" href="#<?= htmlspecialchars($s['id']) ?>">
                <?= htmlspecialchars($s['title']) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="vB-content">
        <?php foreach ($about as $s): ?>
        <section class="vB-section" id="<?= htmlspecialchars($s['id']) ?>">
            <h2 class="vB-heading"><?= htmlspecialchars($s['title']) ?></h2>
            <p><?= htmlspecialchars($s['text']) ?></p>
        </section>
        <?php endforeach; ?>
    </main>
</div>

<div class="trio-wrap">
    <iframe src="https://player.vimeo.com/video/1129809829?dnt=1&title=0&byline=0&portrait=0"
            allow="fullscreen" allowfullscreen title="DEAF.city participants"></iframe>
</div>

<footer class="vB-footer">
    <div class="vB-footer-inner">
        <p class="vB-footer-heading">Credits</p>
        <div class="vB-credits">
            <p><b>2026 Marseille, Roma, Tunis, Algiers and Barcelona</b> — Supported by <a href="https://www.cultura.gob.es/cultura/mc/bellasartes/portada.html" target="_blank">Ministerio de Cultura de España</a></p>
            <p><b>2026 Alger — <a href="https://en.wikipedia.org/wiki/Algerian_Sign_Language" target="_blank">LSA</a></b> Atifa Kaizra, Hamida Taleb, Hassen Djouad, Mahieddine Charrad, Mhamed Lamine Said, Mustapha Iskeur — Interpreter: Hamid Fadhel — <a href="https://jiser.org/" target="_blank">Jiser Reflexions Mediterrànies</a></p>
            <p><b>2026 Roma — <a href="https://en.wikipedia.org/wiki/Italian_Sign_Language" target="_blank">LIS</a></b> Carolina Ambrosio, Lorenzo Laudo, Massimo Paletta, Olivier Fidalgo, Paula Severi, Serena Rosaria Conte — Interpreter: Giada Santini — <a href="https://www.accademiaspagna.org/" target="_blank">Academia de España en Roma</a></p>
            <p><b>2026 Marseille — <a href="https://en.wikipedia.org/wiki/French_Sign_Language" target="_blank">LSF</a></b> Alysone Fecil, Hugo Gathier, Lola Colin — Interpreter: Julie Klène — <a href="https://www.beauxartsdemarseille.fr/lecole-ses-engagements/nous-connaitre/pisourde/" target="_blank">Beaux-Arts Marseille</a></p>
            <p><b>2023 São Paulo — <a href="https://en.wikipedia.org/wiki/Brazilian_Sign_Language" target="_blank">LIBRAS</a></b> Ana Laura Rocha Vendrame, Edvaldo Carmo dos Santos, Fabio de Sa e Silva, Fernanda Oliveira Santos, Idenilson Batista Souza, Vitória Lopes Porto Justa — Interpreter: Karina Regina da Silva Oliveira — <a href="https://www.eca.usp.br/institucional" target="_blank">ECA-USP</a></p>
            <p><b>2023 Bilbao — <a href="https://en.wikipedia.org/wiki/Spanish_Sign_Language" target="_blank">LSE</a></b> Amaia Mejía, Aitor Bedialauneta, Eduardo Amorós, Iñaki Montero — Interpreter: Janire Martín — <a href="https://bilbaomuseoa.eus/en/exhibitions/multiverso-3/" target="_blank">Museo de Bellas Artes de Bilbao</a></p>
            <p><b>2021 Mexico City — <a href="https://en.wikipedia.org/wiki/Mexican_Sign_Language" target="_blank">LSM</a></b> Gustavo Méndez, Indira López Cardona, Ixchel Solís García, Luis Alberto Valencia Beltrán, Luis Eduardo Méndez, Martha Cristina de Díaz, Mauricio Iván Álvarez García, Miguel Díaz, Verónica Álvarez — Interpreters: Ixchel Solís García, Daniela Vite — <a href="http://www.ler.uam.mx" target="_blank">UAM</a></p>
            <p><b>2020 Valencia — <a href="https://en.wikipedia.org/wiki/Spanish_Sign_Language" target="_blank">LSE</a></b> Aurora López, David Riutort, Daniel Bautista, Josep Antoni Gimeno, Mónica Díez, Pepa Burgal, Sonia Piqueras — Interpreter: Carmen Tos — <a href="https://www.consorcimuseus.gva.es/centro-del-carmen/exposicion/apertura-antoni-abad-deaf-city/?lang=es" target="_blank">Centre del Carme Cultura Contemporània</a></p>
        </div>
        <div class="vB-logos">
            <?php foreach ($logos as $logo): ?>
            <img src="<?= htmlspecialchars($logo['src']) ?>" alt="<?= htmlspecialchars($logo['alt']) ?>">
            <?php endforeach; ?>
        </div>
        <p class="vB-byline">
            DEAF.city is a project by <a href="https://www.antoniabad.info" target="_blank">Antoni Abad</a>
            &nbsp;&nbsp;<a href="https://www.instagram.com/antoni__abad/" target="_blank">Contact</a>
        </p>
    </div>
</footer>

<?php else: ?>
<!-- ════════════════════════════════════════════
     VARIANT C — Panels
     ════════════════════════════════════════════ -->

<div class="vC-about">
    <?php foreach ($about as $i => $s): ?>
    <div class="vC-section" id="<?= htmlspecialchars($s['id']) ?>">
        <div class="vC-left">
            <h2 class="vC-heading"><?= htmlspecialchars($s['title']) ?></h2>
        </div>
        <div class="vC-right">
            <p><?= htmlspecialchars($s['text']) ?></p>
        </div>
    </div>
    <?php if ($i === 1): /* deafcity video after 2nd section */ ?>
    <div class="vC-video-panel">
        <div class="vC-video-inner">
            <p class="vC-video-eyebrow">The project</p>
            <div class="embed-16x9">
                <iframe src="https://player.vimeo.com/video/584475715?dnt=1&title=0&byline=0&portrait=0"
                        allow="fullscreen" allowfullscreen title="DEAF.city"></iframe>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="trio-wrap">
    <iframe src="https://player.vimeo.com/video/1129809829?dnt=1&title=0&byline=0&portrait=0"
            allow="fullscreen" allowfullscreen title="DEAF.city participants"></iframe>
</div>

<footer class="vC-footer">
    <div class="vC-footer-inner">
        <p class="vC-byline">
            A project by <a href="https://www.antoniabad.info" target="_blank">Antoni Abad</a>
            &nbsp;&nbsp;<a href="https://www.instagram.com/antoni__abad/" target="_blank">Contact</a>
        </p>
        <div class="vC-credits">
            <p><b>2026 — Marseille, Roma, Tunis, Algiers, Barcelona</b> Ministerio de Cultura de España, Dirección General de Patrimonio Cultural y Bellas Artes</p>
            <p><b>2026 Alger LSA</b> Atifa Kaizra, Hamida Taleb, Hassen Djouad, Mahieddine Charrad, Mhamed Lamine Said, Mustapha Iskeur — Hamid Fadhel — Shahinez Guir — Jiser Reflexions Mediterrànies</p>
            <p><b>2026 Roma LIS</b> Carolina Ambrosio, Lorenzo Laudo, Massimo Paletta, Olivier Fidalgo, Paula Severi, Serena Rosaria Conte — Giada Santini — Academia de España en Roma</p>
            <p><b>2026 Marseille LSF</b> Alysone Fecil, Hugo Gathier, Lola Colin — Julie Klène — Franca Trovato — Beaux-Arts Marseille / Pisourd-e</p>
            <p><b>2023 São Paulo LIBRAS</b> Ana Laura Rocha Vendrame, Edvaldo Carmo dos Santos, Fabio de Sa e Silva, Fernanda Oliveira Santos, Idenilson Batista Souza, Vitória Lopes Porto Justa — Karina Regina da Silva Oliveira — ECA-USP</p>
            <p><b>2023 Bilbao LSE</b> Amaia Mejía, Aitor Bedialauneta, Eduardo Amorós, Iñaki Montero — Janire Martín — Museo de Bellas Artes de Bilbao — Euskal Gorrak, BilboArte Fundazioa</p>
            <p><b>2021 Mexico City LSM</b> Gustavo Méndez, Indira López Cardona, Ixchel Solís García, Luis Alberto Valencia Beltrán, Luis Eduardo Méndez, Martha Cristina de Díaz, Mauricio Iván Álvarez García, Miguel Díaz, Verónica Álvarez — Ixchel Solís García, Daniela Vite — UAM Lerma y Cuajimalpa</p>
            <p><b>2020 Valencia LSE</b> Aurora López, David Riutort, Daniel Bautista, Josep Antoni Gimeno, Mónica Díez, Pepa Burgal, Sonia Piqueras — Carmen Tos — Centre del Carme Cultura Contemporània — Federació De Persones Sordes CV, Las Naves</p>
        </div>
        <div class="vC-logos">
            <?php foreach ($logos as $logo): ?>
            <img src="<?= htmlspecialchars($logo['src']) ?>" alt="<?= htmlspecialchars($logo['alt']) ?>">
            <?php endforeach; ?>
        </div>
    </div>
</footer>
<?php endif; ?>

<!-- ── Prototype switcher ── -->
<div class="proto-bar" role="navigation" aria-label="Prototype variant switcher">
    <button id="proto-prev" aria-label="Previous variant">&#8592;</button>
    <span class="proto-key" id="proto-key"><?= htmlspecialchars($variant) ?></span>
    <span class="proto-name" id="proto-name"><?= htmlspecialchars($variantLabels[$variant]) ?></span>
    <button id="proto-next" aria-label="Next variant">&#8594;</button>
</div>
<script>
(function () {
    var order = ['A', 'B', 'C'];
    var labels = <?= json_encode($variantLabels, JSON_HEX_TAG) ?>;
    var current = <?= json_encode($variant, JSON_HEX_TAG) ?>;

    function go(key) {
        var url = new URL(window.location.href);
        url.searchParams.set('variant', key);
        window.location.href = url.toString();
    }

    document.getElementById('proto-prev').addEventListener('click', function () {
        var i = order.indexOf(current);
        go(order[(i - 1 + order.length) % order.length]);
    });
    document.getElementById('proto-next').addEventListener('click', function () {
        var i = order.indexOf(current);
        go(order[(i + 1) % order.length]);
    });

    document.addEventListener('keydown', function (e) {
        if (document.activeElement && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
        if (e.key === 'ArrowLeft')  { e.preventDefault(); document.getElementById('proto-prev').click(); }
        if (e.key === 'ArrowRight') { e.preventDefault(); document.getElementById('proto-next').click(); }
    });

    <?php if ($variant === 'B'): ?>
    // Highlight active nav link on scroll (Variant B only)
    var links = document.querySelectorAll('.vB-nav-link');
    var sections = Array.from(links).map(function (a) {
        return document.getElementById(a.getAttribute('href').slice(1));
    });
    function onScroll() {
        var scrollY = window.scrollY + 120;
        var active = 0;
        sections.forEach(function (s, i) { if (s && s.offsetTop <= scrollY) active = i; });
        links.forEach(function (a, i) { a.classList.toggle('active', i === active); });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    <?php endif; ?>
}());
</script>

<script src="/preview/js/vimeo_caption_player.js?v=13" defer></script>
</body>
</html>
