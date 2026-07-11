/**
 * Transport controls on About / Participants (no in-page player).
 * Reset navigates to neutral /preview/; other transport opens the player (issue 01 / 02).
 */
(function () {
    'use strict';

    var bar = document.querySelector('.vpc-bottom-bar--player[data-secondary-page="true"]');
    if (!bar) {
        return;
    }

    function playerUrl(intent) {
        var path = '/preview/';
        var params = [];
        var pageUrl = document.querySelector('meta[name="page-url"]');
        var match = window.location.search.match(/[?&]lang=([^&]+)/);
        if (match && match[1]) {
            params.push('lang=' + encodeURIComponent(decodeURIComponent(match[1])));
        }
        if (intent === 'reset') {
            try {
                sessionStorage.removeItem('vpc-playback-session');
                sessionStorage.removeItem('vpc-nav-intent');
            } catch (e) {}
        } else if (intent && intent !== 'reset') {
            try {
                sessionStorage.setItem('vpc-nav-intent', intent);
            } catch (e) {}
        }
        if (params.length) {
            path += '?' + params.join('&');
        }
        return path;
    }

    function bind(btn, intent) {
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            window.location.href = playerUrl(intent);
        });
    }

    bind(bar.querySelector('.vpc-reset-btn'), 'reset');
    bind(bar.querySelector('.vpc-play-pause-btn'), 'play');
    bind(bar.querySelector('.vpc-prev-btn'), 'prev');
    bind(bar.querySelector('.vpc-next-btn'), 'next');
}());
