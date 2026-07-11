/**
 * Equalize min-width of non-transport chrome buttons (nav, filters, language, Reset).
 * Prev / Play / Next keep their own sizing.
 */
(function () {
    'use strict';

    var CHROME_SELECTOR = '.preview-site-nav__btn, .vpc-picker-btn, .vpc-reset-btn';

    /**
     * @param {Element} row
     */
    function syncUniformChromeWidths(row) {
        if (!row || !row.querySelectorAll) {
            return;
        }

        var buttons = row.querySelectorAll(CHROME_SELECTOR);
        if (!buttons.length) {
            return;
        }

        var max = 0;
        var i;
        var saved = [];

        for (i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            saved[i] = {
                width: btn.style.width,
                minWidth: btn.style.minWidth,
            };
            btn.style.width = 'auto';
            btn.style.minWidth = '0';
        }

        for (i = 0; i < buttons.length; i++) {
            var w = Math.ceil(buttons[i].getBoundingClientRect().width);
            if (w > max) {
                max = w;
            }
        }

        for (i = 0; i < buttons.length; i++) {
            buttons[i].style.width = saved[i].width;
            if (max > 0) {
                buttons[i].style.minWidth = max + 'px';
            }
        }
    }

    function syncAllRows() {
        var rows = document.querySelectorAll('.vpc-control-row');
        for (var i = 0; i < rows.length; i++) {
            syncUniformChromeWidths(rows[i]);
        }
    }

    window.vpcSyncChromeButtonWidths = syncAllRows;

    function boot() {
        syncAllRows();
        if (typeof window.ResizeObserver === 'function') {
            var rows = document.querySelectorAll('.vpc-control-row');
            for (var i = 0; i < rows.length; i++) {
                (function (row) {
                    var observer = new window.ResizeObserver(function () {
                        syncUniformChromeWidths(row);
                    });
                    observer.observe(row);
                })(rows[i]);
            }
        } else {
            window.addEventListener('resize', syncAllRows);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
