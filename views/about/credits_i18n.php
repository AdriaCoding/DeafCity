<?php
/**
 * Credits, localised. Static content (cities, sign-language names, institution
 * and people names, links, logos) is NOT translated — cities live in
 * content.edition.*, sign languages in content.sign_language.*, and proper
 * nouns/links are not translatable. Only the rubric labels are i18n keys.
 */
if (!function_exists('preview_t')) {
    require_once dirname(dirname(__DIR__)) . '/preview/lib/preview_locale.php';
}
if (!isset($GLOBALS['preview_i18n']) || !($GLOBALS['preview_i18n'] instanceof PreviewI18n)) {
    $__credits_locale = preview_bootstrap_locale();
    $GLOBALS['preview_i18n'] = $__credits_locale['i18n'];
}
$sb  = htmlspecialchars(preview_t('about.credits.label.supported_by'), ENT_QUOTES, 'UTF-8');
$pa  = htmlspecialchars(preview_t('about.credits.label.participants'), ENT_QUOTES, 'UTF-8');
$itp = htmlspecialchars(preview_t('about.credits.label.interpreter'), ENT_QUOTES, 'UTF-8');
$its = htmlspecialchars(preview_t('about.credits.label.interpreters'), ENT_QUOTES, 'UTF-8');
$co  = htmlspecialchars(preview_t('about.credits.label.coordination'), ENT_QUOTES, 'UTF-8');
$cl  = htmlspecialchars(preview_t('about.credits.label.collaboration'), ENT_QUOTES, 'UTF-8');
$tt  = htmlspecialchars(preview_t('about.credits.label.thanks_to'), ENT_QUOTES, 'UTF-8');
$pby = htmlspecialchars(preview_t('about.credits.project_by'), ENT_QUOTES, 'UTF-8');
$ct  = htmlspecialchars(preview_t('about.credits.contact'), ENT_QUOTES, 'UTF-8');
?>
<p><b>2026 Marseille, Roma, Tunis, Algiers and Barcelona <?= $sb ?> <a href="https://www.cultura.gob.es/cultura/mc/bellasartes/portada.html" target="_blank">Ministerio de Cultura de España, Dirección General de Patrimonio Cultural y Bellas Artes</a></b>

<p><b>2026 Alger <a href="https://en.wikipedia.org/wiki/Algerian_Sign_Language" target="_blank">*LSA Algerian Sign Language</a></b>
  <?= $sb ?> <a href="https://www.cultura.gob.es/cultura/mc/bellasartes/portada.html" target="_blank">Ministerio de Cultura de España, Dirección General de Patrimonio Cultural y Bellas Artes</a>
  <?= $pa ?> Atifa Kaizra, Hamida Taleb, Hassen Djouad, Mahieddine Charrad, Mhamed Lamine Said, Mustapha Iskeur
  <?= $itp ?> Hamid Fadhel <?= $co ?> Shahinez Guir
  <?= $cl ?> <a href="https://jiser.org/" target="_blank">Jiser Reflexions Mediterrànies</a>
  <?= $tt ?> Association Les Signes d'Or de la Wilaya d'Alger, Xavier de Luca, Walid Aïdoud, Box 24.</p>

<p><b>2026 Roma <a href="https://en.wikipedia.org/wiki/Italian_Sign_Language" target="_blank">*LIS Lingua dei Segni Italiana</a></b>
  <?= $sb ?> <a href="https://www.cultura.gob.es/cultura/mc/bellasartes/portada.html" target="_blank">Ministerio de Cultura de España, Dirección General de Patrimonio Cultural y Bellas Artes</a>
  <?= $pa ?> Carolina Ambrosio, Lorenzo Laudo, Massimo Paletta, Olivier Fidalgo, Paula Severi, Serena Rosaria Conte
  <?= $itp ?> Giada Santini <?= $co ?> Lorenzo Laudo
  <?= $cl ?> <a href="https://www.accademiaspagna.org/" target="_blank">Academia de España en Roma</a>
  <?= $tt ?> Valeria Bottalico, Yann Leto.</p>

<p><b>2026 Marseille <a href="https://en.wikipedia.org/wiki/French_Sign_Language" target="_blank">*LSF Langue des Signes Française</a></b>
  <?= $sb ?> <a href="https://www.cultura.gob.es/cultura/mc/bellasartes/portada.html" target="_blank">Ministerio de Cultura de España, Dirección General de Patrimonio Cultural y Bellas Artes</a>
  <?= $pa ?> Alysone Fecil, Hugo GATHIER, Lola Colin
  <?= $itp ?> Julie Klène <?= $co ?> Franca Trovato
  <?= $cl ?> <a href="https://www.beauxartsdemarseille.fr/lecole-ses-engagements/nous-connaitre/pisourde/" target="_blank">Program Pisourd-e/Beaus-Arts Marseille</a>
  <?= $tt ?> Diane Guyot, Frederic Pradeau, Yann Leto, Antoni Muntadas, Sylvia Amar.</p>

<p><b>2023 São Paulo <a href="https://en.wikipedia.org/wiki/Brazilian_Sign_Language" target="_blank">*LIBRAS Língua Brasileira de Sinais</a></b>
  <?= $sb ?> <a href="https://www.eca.usp.br/institucional" target="_blank">ECA-USP Escola de Comunicações e Artes/Universidade de São Paulo</a>
  <?= $pa ?> Ana Laura Rocha Vendrame, Edvaldo Carmo dos Santos, Fabio de Sa e Silva, Fernanda Oliveira Santos, Idenilson Batista Souza, Vitória Lopes Porto Justa
  <?= $itp ?> Karina Regina da Silva Oliveira <?= $co ?> Isart Santos
  <?= $tt ?> Martin Grossmann, Rubens Rewald, Sandro Costa, Marcelo Godoy, Paulo Hartmann, André Fratti Costa.</p>

<p><b>2023 Bilbao <a href="https://en.wikipedia.org/wiki/Spanish_Sign_Language" target="_blank">*LSE Lengua de Signos Española</a></b>
  <?= $sb ?> <a href="https://bilbaomuseoa.eus/en/exhibitions/multiverso-3/" target="_blank">Museo de Bellas Artes de Bilbao</a>
  <?= $pa ?> Amaia Mejía, Aitor Bedialauneta, Eduardo Amorós, Iñaki Montero
  <?= $itp ?> Janire Martín
  <?= $cl ?> <a href="https://euskal-gorrak.org/" target="_blank">Euskal Gorrak</a>, <a href="https://bilbaoarte.org/" target="_blank">BilboArte Fundazioa</a>
  <?= $tt ?> Javier Riaño, Miriam Isasi, Aitor Arakistain, Txuspo Poyo.</p>

<p><b>2021 Mexico City <a href="https://en.wikipedia.org/wiki/Mexican_Sign_Language" target="_blank">*LSM Lengua de Señas Mexicana</a></b>
  <?= $sb ?> Unidades <a href="http://www.ler.uam.mx" target="_blank">Lerma</a> y <a href="http://www.cua.uam.mx" target="_blank">Cuajimalpa</a> de la Universidad Autónoma Metropolitana
  <?= $pa ?> Gustavo Méndez, Indira López Cardona, Ixchel Solís García, Luis Alberto Valencia Beltrán, Luis Eduardo Méndez, Martha Cristina de Díaz, Mauricio Iván Álvarez García, Miguel Díaz, Verónica Álvarez
  <?= $its ?> Ixchel Solís García, Daniela Vite
  <?= $tt ?> Octavio Mercado, Mónica Benítez, Hugo Solís, Angélica Martínez de la Peña, Andrea Barojas, Carolina Belén González, Gabriela Villa, Isabel García Hidalgo, César Martínez, Luis Eudardo Vaquera.</p>

<p><b>2020 Valencia <a href="https://en.wikipedia.org/wiki/Spanish_Sign_Language" target="_blank">*LSE Lengua de Signos Española</a></b>
  <?= $sb ?> <a href="https://www.consorcimuseus.gva.es/centro-del-carmen/exposicion/apertura-antoni-abad-deaf-city/?lang=es" target="_blank">Centre del Carme Cultura Contemporània</a>
  <?= $cl ?> <a href="https://www.fesord.org/val/inicio/" target="_blank">Federació De Persones Sordes CV</a>, <a href="https://www.lasnaves.com/?lang=es" target="_blank">Las Naves</a>
  <?= $pa ?> Aurora López, David Riutort, Daniel Bautista, Josep Antoni Gimeno, Mónica Díez, Pepa Burgal, Sonia Piqueras
  <?= $itp ?> Carmen Tos
  <?= $tt ?> Matteo Sisti Sette, Roc Parés, Daniel Julià, Maribel Domènech, Salomé Cuesta, Justina Pérez Cantos.</p>

<p><b><?= $pby ?> <a href="https://www.antoniabad.info" target="_blank">Antoni Abad</a> &nbsp; <a href="https://www.instagram.com/antoni__abad/" target="_blank"><?= $ct ?></a></b></p>

<p><img class="logos" src="/img/ministerio.png" width="367" height="80"/> &nbsp; &nbsp; <img src="/img/ecausp2.png" width="150" height="45"/> &nbsp; &nbsp;
  <img src="/img/bilbaomuseoa2.png" width="154" height="30"/> &nbsp; &nbsp; <img src="/img/uam2.png" width="100" height="100"/> &nbsp; &nbsp; <img src="/img/cccc.png" width="100" height="63"/></p>
