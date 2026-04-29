<?php if (defined('DEBUG_MISC')): ?>
<pre>
<?php echo htmlspecialchars(print_r($playlists, true)); ?>
</pre>
<?php endif; ?>
<nav class="menu" id="menu1">
    <a href="#player" class="navitem player-link"></a>
    <!--<a href="#playlists" class="navitem playlists-link"></a>-->
    <a href="#clock" class="navitem clock-link"></a>
    <a href="#about" class="navitem about-link"></a>
    <?php if (defined('HOME_SHOW_MAP') && HOME_SHOW_MAP): ?>
        <a href="#map-page" class="navitem map-page-link"></a>
    <?php endif ?>
</nav>
<div id="everything">
<div id="player" data-playlist="<?php echo $selected_playlist; ?>">
    <?php $info = $this->app->getVideoCache()->getVideoInfo($selected_video_id); ?>
    <?php 
        $videowidheicss = "style=\"aspect-ratio:{$info['width']}/{$info['height']}\"";
    ?>
    <div id="current-video">
        <?php if (defined('FAKE_VIDEO')) :?>
        <iframe src="fakevideo.php" class="fake-video" <?php echo $videowidheicss; ?> title=""></iframe>
        <?php else: ?>
        <iframe src="https://player.vimeo.com/video/<?php echo $selected_video_id; ?>?texttrack=<?php echo app()->bestSubtitles($info['subtitles']); ?>" <?php echo $videowidheicss; ?> frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title=""></iframe>
        <?php endif; ?>
    </div>
</div>
<div id="controls">
    <span id="prev" class="disabled"><img src="img/previous.svg?v=9" width="30" height="30"></span>
    <!-- <span id="title"><?php echo htmlspecialchars($playlists['playlists'][$selected_playlist]['title']); ?></span> -->
    <select class="playlist-selector">
        <?php for ($i=0; $i<count($playlists['playlists']); $i++): ?>
            <option value="<?php echo $i; ?>" <?php if ($i==$selected_playlist) echo 'selected'; ?>>
                <?php echo htmlspecialchars($playlists['playlists'][$i]['title']); ?>
            </option>
        <?php endfor; ?>
    </select>
    <span id="next"><img src="img/next.svg?v=9" width="30" height="30"></span>
</div>
<!--  echo Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']); -->
<div id="playlists">
    <div id="all-lists">
        <h1>PLAYLISTS</h1>
        <ul>
            <?php for ($i=0; $i<count($playlists['playlists']); $i++): ?>
                <li id="playlist-<?php echo $i; ?>" <?php if ($i==$selected_playlist) echo 'class="selected"'; ?> data-title-caps="<?php echo htmlspecialchars(mb_strtoupper($playlists['playlists'][$i]['title'], 'UTF-8'));?>">
                    <span class="title"><?php echo htmlspecialchars($playlists['playlists'][$i]['title']); ?></span>
                    <span class="play-pause">
                        <span class="icon-play"></span>
                        <span class="icon-pause"></span>
                        <span class="icon-playing"></span>
                    </span>
                </li>
            <?php endfor; ?>
        </ul>
    </div>
    <div id="thumbs">
        <?php for($i=0; $i < count($playlists['playlists']); $i++): ?>
            <div class="playlist <?php if($i==$selected_playlist) echo 'selected'; ?>" id="thumbs-playlist-<?php echo $i; ?>">
                <?php for($j=0; $j < count($playlists['playlists'][$i]['vimeo_ids']); $j++): ?>
                    <?php 
                    $vimeo_id = $playlists['playlists'][$i]['vimeo_ids'][$j];
                    $info = $this->app->getVideoCache()->getVideoInfo($vimeo_id); 
                    ?>
                    <div class="video <?php if($j==0) echo 'current'; ?>" id="video-<?php echo $i.'-'.$j; ?>" data-vimeoid="<?php echo $vimeo_id; ?>" 
                         data-subtitles="<?php echo app()->bestSubtitles($info['subtitles']); ?>"
                         data-aspect="<?php echo "{$info['width']}/{$info['height']}"; ?>">
                        <div class="thumb-wrapper">
                            <div class="thumb">
                                <?php if (defined('DEBUG_MISC')) echo "<pre>$vimeo_id\n".htmlspecialchars(print_r($info,true))."</pre>"; ?>
                                <img src="<?php echo $info['thumb']['link']; ?>" width="<?php echo $info['thumb']['width']; ?>" height="<?php echo $info['thumb']['height']; ?>">
                            </div>
                            <div class="info">
                                <div class="header">
                                    <span class="year"><?php echo htmlspecialchars($info['year']); ?></span>
                                    <span class="title"><?php echo htmlspecialchars($info['title']); ?></span>
                                    <span class="num"><?php echo htmlspecialchars($info['num']); ?></span>
                                </div>
                                <div class="tags">
                                    <?php echo $info['tags_html']; ?>
                                </div>
                                <div class="footer">
                                    <span class="lang"><?php echo htmlspecialchars($info['lang']); ?></span>
                                    <span class="city"><?php echo htmlspecialchars($info['city']); ?></span>
                                    <span class="duration"><?php echo $info['duration']; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="play-pause">
                            <span class="icon-play"></span>
                            <span class="icon-pause"></span>
                            <span class="icon-playing"></span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>
<div id="clock">
    <div id="clock-proper" class="clock">
        <iframe src="realtime/index.html"></iframe>
    </div>
    <?php if(defined('GALLERY')): ?>
    <div id="gallery" class="gallery">
        <?php include(__DIR__ . '/_gallery.php'); ?>
    </div>
    <?php else: ?>
    <div id="about-deafcity">
        <?php include(__DIR__ . '/about/deafcity.php'); ?>
    </div>
    <?php endif; ?>
</div>
<div id="about" class="about">
    <div id="about-todo">
        <?php include(__DIR__ . '/about/todo.php'); ?>
        
    </div>
</div>
<?php if (defined('HOME_SHOW_MAP') && HOME_SHOW_MAP): ?>
    <div id="map-page">
        <div id="map-filter" class="map-filter">
            <div class="map-filter-header">Legend</div>
            <ul class="map-filter-list">
                <li class="map-filter-item" id="map-filter-deafcity" data-branch="deafcity">
                    <label>
                        <span class="map-filter-icon"><span class="deafcity-marker"></span></span>
                        <span class="map-filter-text">DEAF.city</span>
                        <input type="checkbox" checked class="map-filter-checkbox">
                    </label>
                </li>
                <li class="map-filter-item" id="map-filter-dummy">
                    <label>
                        <span class="map-filter-icon"></span>
                        <span class="map-filter-text">xxxx</span>
                        <input type="checkbox" checked class="map-filter-checkbox">
                    </label>
                </li>
            </ul>
        </div>
        <div id="map-container">
            <div id="map-map">

            </div>
        </div>
    </div>
<?php endif ?>
<div id="trio">
    <?php include(__DIR__ . '/about/trio.php'); ?>
</div>
<div id="credits" class="about">
    <div id="credits-bottom">
        <?php include(__DIR__ . '/about/credits.php'); ?>
    </div>
</div>
</div>
<nav class="menu" id="menu2">
    <a href="#player" class="navitem player-link"></a>
    <!--<a href="#playlists" class="navitem playlists-link"></a>-->
    <a href="#clock" class="navitem clock-link"></a>
    <a href="#about" class="navitem about-link"></a>
    <?php if (defined('HOME_SHOW_MAP') && HOME_SHOW_MAP): ?>
        <a href="#map-page" class="navitem map-page-link"></a>
    <?php endif ?>
</nav>
<script>
    files_md5 = {
        deafcity_json: "<?php echo md5_file(__DIR__.'/../data/deafcity.json'); ?>"
    };
</script>
<script
  src="https://code.jquery.com/jquery-3.5.1.js"
  integrity="sha256-QWo7LDvxbWT2tbbQ97B53yJnYU3WhH/C8ycbRAkjPDc="
  crossorigin="anonymous"></script>
<script src="https://player.vimeo.com/api/player.js"></script>
<?php if (defined('HOME_SHOW_MAP') && HOME_SHOW_MAP): ?>
<script src="leaflet/leaflet.js"></script>
<?php endif; ?>
<script src="<?php echo asset('js/deafcity.js'); ?>"></script>
<script src="<?php echo asset('js/gallery.js'); ?>"></script>
<?php if (defined('HOME_SHOW_MAP') && HOME_SHOW_MAP): ?>
<script src="<?php echo asset('js/map.js'); ?>"></script>
<?php endif; ?>
