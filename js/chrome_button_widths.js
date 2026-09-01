/**
 * Chrome width hook — reset/participants/filter sizing is CSS-driven (vimeo_caption_player.css).
 * Kept for vpcSyncChromeButtonWidths() calls after filter/collection label updates.
 */
(function () {
    'use strict';

    function syncAllRows() {
        /* no-op: flex weights and CSS vars on .vpc-control-row control chrome widths */
    }

    window.vpcSyncChromeButtonWidths = syncAllRows;

    function boot() {
        syncAllRows();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
