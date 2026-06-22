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
            'chrome_label' => 'Player',
        ),
        array(
            'route' => 'about',
            'href' => '/preview/about',
            'chrome_label' => 'About',
        ),
        array(
            'route' => 'participants',
            'href' => '/preview/participants',
            'chrome_label' => 'Participants',
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
    $items = preview_site_nav_route_catalog();
    $links = array();

    foreach ($items as $item) {
        $isCurrent = ($currentRoute === $item['route']);
        if ($placement === 'chrome' && $isCurrent) {
            continue;
        }

        $label = $item['chrome_label'];
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
            $dataGenericLabel = $item['chrome_label'];
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
