<?php
// Run: php8.4 preview/tests/aria_controls_test.php
//
// Every aria-controls value must point at an id that actually exists on the same
// rendered page. On About/Participants (no in-page Vimeo iframe — those transport
// buttons navigate to the home page instead of controlling anything in place), the
// bottom bar used to always emit aria-controls="<home page's iframe id>" regardless,
// pointing at an id absent from the page.

function ac_check_page($html, $label)
{
    if (!preg_match_all('/aria-controls="([^"]*)"/', $html, $m)) {
        fwrite(STDERR, "FAIL: {$label}: no aria-controls attributes found (test fixture is stale)\n");
        exit(1);
    }
    foreach ($m[1] as $id) {
        if ($id === '') {
            fwrite(STDERR, "FAIL: {$label}: empty aria-controls value (should be omitted, not emitted blank)\n");
            exit(1);
        }
        if (!preg_match('/\bid="' . preg_quote($id, '/') . '"/', $html)) {
            fwrite(STDERR, "FAIL: {$label}: aria-controls=\"{$id}\" has no matching id=\"{$id}\" on the page\n");
            exit(1);
        }
    }
    echo "PASS: {$label}: every aria-controls value resolves to a real id on the page (" . count($m[1]) . " checked)\n";
}

$previewDir = dirname(dirname(__FILE__));

$_GET = array();
ob_start();
include $previewDir . '/index.php';
$homeHtml = ob_get_clean();

ob_start();
include $previewDir . '/about/index.php';
$aboutHtml = ob_get_clean();

ob_start();
include $previewDir . '/participants/index.php';
$participantsHtml = ob_get_clean();

ac_check_page($homeHtml, 'home');
ac_check_page($aboutHtml, 'about');
ac_check_page($participantsHtml, 'participants');

// The transport controls on secondary pages navigate away rather than controlling an
// in-page iframe — they must not carry aria-controls at all (no id to point at).
if (preg_match('/class="vpc-prev-btn[^"]*"\s+aria-controls="/', $aboutHtml)) {
    fwrite(STDERR, "FAIL: about page's prev button still emits aria-controls (no in-page iframe to reference)\n");
    exit(1);
}
echo "PASS: about page's transport buttons omit aria-controls (no in-page iframe)\n";
if (preg_match('/class="vpc-prev-btn[^"]*"\s+aria-controls="/', $participantsHtml)) {
    fwrite(STDERR, "FAIL: participants page's prev button still emits aria-controls (no in-page iframe)\n");
    exit(1);
}
echo "PASS: participants page's transport buttons omit aria-controls (no in-page iframe)\n";

// The home page player DOES have an in-page iframe — its transport controls should
// still reference it (this must keep working, not just stop being wrong elsewhere).
if (!preg_match('/class="vpc-prev-btn[^"]*"\s+aria-controls="[^"]+__iframe"/', $homeHtml)) {
    fwrite(STDERR, "FAIL: home page's prev button should still carry aria-controls pointing at its real iframe\n");
    exit(1);
}
echo "PASS: home page's transport buttons still reference the real in-page iframe\n";

echo "\nAll aria_controls tests passed.\n";
