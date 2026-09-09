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
    'thumbnail_url' => 'https://i.vimeocdn.com/video/abc-d_960x540?r=pad&region=us',
    'captions' => [[
        'lang' => 'es',
        'label' => 'Spanish',
        'file' => '111.es.srt',
    ]],
];

$projected = vpc_project_catalog_video($video);
cpt_assert($projected['video_id'] === '111', 'projects Vimeo id');
cpt_assert($projected['caption_tracks'][0]['lang'] === 'es', 'projects caption language');
cpt_assert($projected['participant'] === 'Aurora', 'projects participant');
cpt_assert($projected['tags'] === ['humor'], 'deduplicates tags');
cpt_assert(isset($projected['thumbnail_base']), 'projects thumbnail_base');
cpt_assert(strpos($projected['thumbnail_base'], '_960x540') === false, 'thumbnail_base has no size suffix');
cpt_assert(strpos($projected['thumbnail_base'], 'r=pad') === false, 'thumbnail_base has no r=pad');
cpt_assert(vpc_project_catalog_video(['invisible' => true]) === null, 'filters invisible video');

$withStoredBase = $video;
$withStoredBase['thumbnail_base'] = 'https://i.vimeocdn.com/video/stored-d?region=us';
$fromStored = vpc_project_catalog_video($withStoredBase);
cpt_assert(
    $fromStored['thumbnail_base'] === 'https://i.vimeocdn.com/video/stored-d?region=us',
    'stored thumbnail_base is preferred over deriving from thumbnail_url'
);

echo "PASS: catalog projection\n";
