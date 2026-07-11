<?php
/**
 * Issue #14 — Green active state for participant/tag collections (D21)
 *
 * Run: php preview/tests/green_collections_test.php
 */

function gc_assert_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function gc_assert_not_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label} — should not contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$homePage = dirname(dirname(__FILE__)) . '/index.php';
$jsPath = dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js';
$navPath = dirname(dirname(__FILE__)) . '/lib/site_nav_builder.php';
$cssPath = dirname(dirname(__FILE__)) . '/css/bottom-bar.css';
$pickerCssPath = dirname(dirname(__FILE__)) . '/components/vimeo_caption_player.css';

// ── Neutral home: no green collection nav button ─────────────────────────────
unset($_GET['participant']);
ob_start();
include $homePage;
$neutralHtml = ob_get_clean();

gc_assert_not_contains('data-collection="participants" class="preview-site-nav__btn is-active"', $neutralHtml, 'no SSR green Participants button on neutral home');
gc_assert_contains('data-collection="participants"', $neutralHtml, 'Participants button has data-collection hook');
gc_assert_contains('data-generic-label="Participants"', $neutralHtml, 'Participants button stores generic label for JS reset');
gc_assert_contains('vpc-chrome-btn__label">Participants</span>', $neutralHtml, 'neutral Participants label on home');

// ── Active participant collection: SSR green + name ───────────────────────────
$_GET['participant'] = 'Hamida';
ob_start();
include $homePage;
$activeHtml = ob_get_clean();

gc_assert_contains('data-collection="participants"', $activeHtml, 'participant home has collection hook');
gc_assert_contains('class="preview-site-nav__btn is-active"', $activeHtml, 'SSR is-active on participant collection');
gc_assert_contains('vpc-chrome-btn__label">Hamida</span>', $activeHtml, 'SSR shows participant name on nav button');
gc_assert_contains('aria-current="true"', $activeHtml, 'SSR aria-current on active collection button');

// ── CSS: brand green matches fixed-filter picker (D21) ───────────────────────
$navCss = file_get_contents($cssPath);
$pickerCss = file_get_contents($pickerCssPath);

gc_assert_contains('.preview-site-nav__btn.is-active', $navCss, 'collection active nav rule present');
gc_assert_contains('border-color: rgb(0, 120, 0)', $navCss, 'nav active border uses brand green');
gc_assert_contains('rgb(0, 120, 0)', $pickerCss, 'picker CSS defines brand green');
gc_assert_contains('border-color: rgb(0, 120, 0)', $navCss, 'nav green border matches picker token');

// ── JS: generic collection sync wired ────────────────────────────────────────
$js = file_get_contents($jsPath);
$navPhp = file_get_contents($navPath);

gc_assert_contains('syncCollectionNavButtons', $js, 'generic collection nav sync function');
gc_assert_contains('getCollectionNavState', $js, 'collection nav state resolver for future Tags');
gc_assert_contains('L.resolveParticipantsNavState', $js, 'player uses participants nav state from playlist logic');
gc_assert_not_contains('syncParticipantButtonLabel', $js, 'old participant-only sync removed');
gc_assert_contains('syncCollectionNavButtons()', $js, 'collection sync invoked on init');
gc_assert_contains("btn.classList.add('is-active')", $js, 'JS toggles is-active class');
gc_assert_contains("btn.classList.remove('is-active')", $js, 'JS clears is-active on neutral');
gc_assert_contains("querySelectorAll('.preview-site-nav__btn[data-collection]')", $js, 'targets all collection nav buttons generically');
gc_assert_contains('syncCollectionNavButtons();', $js, 'reset clears collection nav styling');
gc_assert_contains("'participants'", $navPhp, 'participants collection key in site nav');

unset($_GET['participant']);

echo "\nAll green_collections tests passed.\n";
