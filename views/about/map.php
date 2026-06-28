<?php
$studioConfig = json_decode(file_get_contents($rootDir . '/data/studio-config.json'), true);
$catalog = json_decode(file_get_contents($rootDir . '/data/catalog.json'), true);

$videoCounts = array();
foreach ($catalog['videos'] as $video) {
    $eid = isset($video['edition']) ? $video['edition'] : '';
    if ($eid) {
        $prev = isset($videoCounts[$eid]) ? $videoCounts[$eid] : 0;
        $videoCounts[$eid] = $prev + 1;
    }
}

$mapData = [];
foreach ($studioConfig['editions'] as $edition) {
    if (!isset($edition['coordinates'])) continue;
    if (!isset($videoCounts[$edition['id']])) continue;
    $mapData[] = [
        'id'          => $edition['id'],
        'label'       => $edition['label'],
        'lat'         => $edition['coordinates'][0],
        'lon'         => $edition['coordinates'][1],
        'video_count' => $videoCounts[$edition['id']],
    ];
}
?>
<div id="city-map">
    <div id="city-map-canvas"></div>
</div>
<script>window.DEAF_CITY_MAP_DATA = <?= json_encode($mapData, JSON_UNESCAPED_UNICODE) ?>;</script>
