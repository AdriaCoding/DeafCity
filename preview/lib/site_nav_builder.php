<?php

/**
 * Primary site routes for preview navigation.
 *
 * @return array<int, array<string, mixed>>
 */
function preview_site_nav_route_catalog()
{
    return array(
        array(
            'route' => 'home',
            'href' => '/preview/',
            'label_key' => 'player.nav.player',
        ),
        array(
            'route' => 'about',
            'href' => '/preview/about',
            'label_key' => 'player.nav.about',
        ),
        array(
            'route' => 'participants',
            'href' => '/preview/participants',
            'label_key' => 'player.nav.participants',
            'collection' => 'participants',
        ),
    );
}

/**
 * Build placement-aware nav link descriptors for site_nav.php rendering.
 *
 * @param string $currentRoute
 * @param string $placement 'chrome' hides current route; 'navbar' includes all with current active
 * @param array<string, string> $activeCollections
 * @return array<int, array<string, mixed>>
 */
function preview_build_site_nav_links($currentRoute, $placement, $activeCollections = array())
{
    if (!function_exists('preview_t')) {
        require_once __DIR__ . '/preview_locale.php';
    }

    $items = preview_site_nav_route_catalog();
    $links = array();

    foreach ($items as $item) {
        $isCurrent = ($currentRoute === $item['route']);
        if ($placement === 'chrome' && $isCurrent) {
            continue;
        }

        $defaultLabel = isset($item['label_key']) ? preview_t($item['label_key']) : '';
        $label = $defaultLabel;
        $class = 'preview-site-nav__btn';
        $ariaCurrent = '';
        $collectionKey = isset($item['collection']) ? (string) $item['collection'] : '';
        $dataCollection = '';
        $dataGenericLabel = '';

        if ($placement === 'navbar' && $isCurrent) {
            $class .= ' is-current';
            $ariaCurrent = 'page';
        } elseif ($placement === 'chrome' && $collectionKey !== ''
            && isset($activeCollections[$collectionKey])
            && trim((string) $activeCollections[$collectionKey]) !== '') {
            $label = trim((string) $activeCollections[$collectionKey]);
            $class .= ' is-active';
            $ariaCurrent = 'true';
        }

        if ($placement === 'chrome' && $collectionKey !== '') {
            $dataCollection = $collectionKey;
            $dataGenericLabel = $defaultLabel;
        }

        $links[] = array(
            'route' => $item['route'],
            'href' => $item['href'],
            'label' => $label,
            'class' => $class,
            'aria_current' => $ariaCurrent,
            'data_collection' => $dataCollection,
            'data_generic_label' => $dataGenericLabel,
        );
    }

    return $links;
}
