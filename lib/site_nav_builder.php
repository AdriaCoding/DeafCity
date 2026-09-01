<?php

require_once __DIR__ . '/site_base.php';

/**
 * Primary site routes for preview navigation.
 *
 * @return array<int, array<string, mixed>>
 */
function preview_site_nav_route_catalog()
{
    return array(
        array(
            'route' => 'about',
            'href' => preview_route_path('about'),
            'label_key' => 'player.nav.about',
        ),
        array(
            'route' => 'participants',
            'href' => preview_route_path('participants'),
            'label_key' => 'player.nav.participants',
            'collection' => 'participants',
        ),
    );
}

/**
 * Base path for a preview site route (no query string).
 *
 * @param string $route
 * @return string
 */
function preview_route_base_path($route)
{
    return preview_route_path($route);
}

/**
 * Language switcher options for the bottom navbar on About/Participants pages.
 *
 * @param string $route
 * @param string $currentLang
 * @return array<int, array{id: string, label: string, href: string, selected: bool}>
 */
function preview_build_language_switcher_options($route, $currentLang)
{
    if (!function_exists('preview_languages_from_config')) {
        require_once __DIR__ . '/preview_locale.php';
    }

    $configPath = preview_resolve_data_dir() . '/studio-config.json';
    $languages = preview_languages_from_config($configPath);
    $languageIds = preview_language_ids_from_config($configPath);

    $byId = array();
    foreach ($languages as $lang) {
        $byId[$lang['id']] = $lang;
    }

    $orderedIds = $languageIds;
    usort($orderedIds, function ($a, $b) use ($byId) {
        $labelA = isset($byId[$a]['label']) ? (string) $byId[$a]['label'] : (string) $a;
        $labelB = isset($byId[$b]['label']) ? (string) $byId[$b]['label'] : (string) $b;

        return strcasecmp($labelA, $labelB);
    });

    $basePath = preview_route_base_path($route);
    $currentLang = (string) $currentLang;
    $options = array();

    foreach ($orderedIds as $id) {
        if (!isset($byId[$id])) {
            continue;
        }
        $options[] = array(
            'id' => $id,
            'label' => $byId[$id]['label'],
            'href' => preview_append_lang_query($basePath, $id),
            'selected' => ($id === $currentLang),
        );
    }

    return $options;
}

/**
 * Build nav link descriptors for bottom_bar.php rendering.
 *
 * Always returns visible routes (about, participants); home/Reproductor is not shown.
 * Active collections (e.g. participant name on the player page) surface on their nav link.
 *
 * @param string $currentRoute
 * @param string $placement Retained for call-site compatibility; no longer omits routes.
 * @param array<string, string> $activeCollections
 * @param string $currentLang
 * @return array<int, array<string, mixed>>
 */
function preview_build_site_nav_links($currentRoute, $placement, $activeCollections = array(), $currentLang = 'en')
{
    if (!function_exists('preview_t')) {
        require_once __DIR__ . '/preview_locale.php';
    }

    $items = preview_site_nav_route_catalog();
    $links = array();

    foreach ($items as $item) {
        $isCurrent = ($currentRoute === $item['route']);

        $defaultLabel = isset($item['label_key']) ? preview_t($item['label_key']) : '';
        $label = $defaultLabel;
        $class = 'preview-site-nav__btn';
        $ariaCurrent = '';
        $collectionKey = isset($item['collection']) ? (string) $item['collection'] : '';
        $dataCollection = '';
        $dataGenericLabel = '';
        $collectionActive = ($collectionKey !== ''
            && isset($activeCollections[$collectionKey])
            && trim((string) $activeCollections[$collectionKey]) !== '');

        if ($collectionActive) {
            $label = trim((string) $activeCollections[$collectionKey]);
            $class .= ' is-active';
            if ($collectionKey === 'participants') {
                $class .= ' preview-site-nav__btn--person-name';
            }
        }

        if ($isCurrent) {
            $class .= ' is-current';
            $ariaCurrent = 'page';
        } elseif ($collectionActive) {
            $ariaCurrent = 'true';
        }

        if ($collectionKey !== '') {
            $dataCollection = $collectionKey;
            $dataGenericLabel = $defaultLabel;
        }

        $href = preview_append_lang_query($item['href'], $currentLang);

        $links[] = array(
            'route' => $item['route'],
            'href' => $href,
            'label' => $label,
            'class' => $class,
            'aria_current' => $ariaCurrent,
            'data_collection' => $dataCollection,
            'data_generic_label' => $dataGenericLabel,
            // Key behind the generic label, so an in-session Website language switch can
            // repaint this link without re-rendering the page (ADR-0017).
            'label_key' => isset($item['label_key']) ? (string) $item['label_key'] : '',
        );
    }

    return $links;
}
