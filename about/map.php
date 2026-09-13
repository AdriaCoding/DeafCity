<?php
$langJsonVer = is_readable($rootDir . '/data/languages.json') ? filemtime($rootDir . '/data/languages.json') : 1;
$cityJsonVer = is_readable($rootDir . '/data/deafcity.json') ? filemtime($rootDir . '/data/deafcity.json') : 1;
?>
<section class="sign-language-map" id="sign-language-map">
    <div id="sign-language-map-canvas"
         data-languages-url="/data/languages.json?v=<?= (int) $langJsonVer ?>"
         data-deafcity-url="/data/deafcity.json?v=<?= (int) $cityJsonVer ?>"></div>
    <p class="sign-language-map-attribution">
        Language data based on
        <a href="https://doi.org/10.5281/zenodo.18840935"
           title="Hammarström, Harald, Robert Forkel, Martin Haspelmath &amp; Sebastian Bank. 2026. Glottolog 5.3. Leipzig: Max Planck Institute for Evolutionary Anthropology. DOI: 10.5281/zenodo.18840935.">Glottolog 5.3</a>.
        Classification adapted by DEAF.city.
    </p>
</section>
