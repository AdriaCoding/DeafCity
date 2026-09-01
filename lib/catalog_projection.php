<?php

require_once __DIR__ . '/videos_catalog.php';

/**
 * Convert one visible Catalog Video into the stable Preview player shape.
 *
 * This is the single Catalog → player projection seam. The Preview routes
 * choose collections; this function owns the player-facing facts.
 *
 * @param array<string, mixed> $video
 * @return array<string, mixed>|null
 */
function vpc_project_catalog_video(array $video) {
    if (!vpc_catalog_entry_is_visible($video)) {
        return null;
    }
    if (empty($video['id']) || !is_string($video['id'])) {
        return null;
    }

    $entry = array();
    if (!empty($video['vimeo_id'])) {
        $entry['video_id'] = preg_replace('/\D/', '', (string) $video['vimeo_id']);
    }
    if (!empty($video['embed_url']) && is_string($video['embed_url'])) {
        $entry['embed_url'] = $video['embed_url'];
    }
    if (!empty($video['captions']) && is_array($video['captions'])) {
        $tracks = vpc_caption_tracks_from_catalog_captions($video['captions']);
        if (count($tracks) > 0) {
            $entry['caption_tracks'] = $tracks;
        }
    }

    foreach (array('sign_language', 'edition', 'typology', 'participant') as $field) {
        $value = isset($video[$field]) ? trim((string) $video[$field]) : '';
        if ($value !== '') {
            $entry[$field] = $value;
        }
    }
    vpc_playlist_entry_apply_participant_fields($entry, $video);

    $tags = array();
    foreach (isset($video['tags']) && is_array($video['tags']) ? $video['tags'] : array() as $tag) {
        if (is_string($tag) && trim($tag) !== '' && !in_array(trim($tag), $tags, true)) {
            $tags[] = trim($tag);
        }
    }
    $entry['tags'] = $tags;

    if (!empty($video['thumbnail_url']) && is_string($video['thumbnail_url'])) {
        $entry['thumbnail_url'] = trim($video['thumbnail_url']);
    }
    if (isset($video['embed_params']) && is_array($video['embed_params']) && count($video['embed_params']) > 0) {
        $entry['embed_params'] = $video['embed_params'];
    }

    if (empty($entry['video_id']) && empty($entry['embed_url'])) {
        return null;
    }
    return $entry;
}
