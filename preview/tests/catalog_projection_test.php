<?php

require dirname(dirname(__FILE__)) . '/lib/catalog_projection.php';

function cpt_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$video = [
    'id' => 'lse_111',
    'vimeo_id' => '111',
    'title' => 'Example',
    'sign_language' => 'lse',
    'edition' => '2020-valencia',
    'typology' => 'acudits',
    'participant' => 'Aurora',
    'tags' => ['humor', 'humor'],
    'thumbnail_url' => 'https://example.com/thumb.jpg',
    'captions' => [[
        'lang' => 'es',
        'label' => 'Spanish',
        'file' => '111.es.vtt',
    ]],
];

$projected = vpc_project_catalog_video($video);
cpt_assert($projected['video_id'] === '111', 'projects Vimeo id');
cpt_assert($projected['caption_tracks'][0]['lang'] === 'es', 'projects caption language');
cpt_assert($projected['participant'] === 'Aurora', 'projects participant');
cpt_assert($projected['tags'] === ['humor'], 'deduplicates tags');
cpt_assert(vpc_project_catalog_video(['invisible' => true]) === null, 'filters invisible video');

echo "PASS: catalog projection\n";
