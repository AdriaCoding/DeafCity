/**
 * Transport controls on About / Participants (no in-page player).
 * Reset navigates to neutral /preview/; other transport opens the player (issue 01 / 02).
 * Syncs Participants nav from saved playback session (issue #04).
 * DEAF+HEARING force-ON handoff (DH10/DH11) + session chrome mirror (DH9).
 */
(function () {
    'use strict';

    var bar = document.querySelector('.vpc-bottom-bar--player[data-secondary-page="true"]');
    if (!bar) {
        return;
    }

    var L = typeof window.VpcPlaylistLogic !== 'undefined' ? window.VpcPlaylistLogic : null;

    function readSession() {
        if (!L || typeof L.parsePlaybackSession !== 'function') {
            return null;
        }
        try {
            var sessionRaw = sessionStorage.getItem(L.PLAYBACK_SESSION_KEY);
            return sessionRaw ? L.parsePlaybackSession(sessionRaw) : null;
        } catch (e) {
            return null;
        }
    }

    function syncParticipantsNavFromSession() {
        if (!L || typeof L.resolveParticipantsNavState !== 'function') {
            return;
        }
        var btn = bar.querySelector('.preview-site-nav__btn[data-collection="participants"]');
        if (!btn) {
            return;
        }
        var genericLabel = btn.getAttribute('data-generic-label') || 'Participants';
        var session = readSession();
        var participantName = session && typeof session.participantName === 'string'
            ? session.participantName.trim()
            : '';
        var navState = L.resolveParticipantsNavState({
            isParticipantMode: participantName !== '',
            participantName: participantName,
            onPlayerPage: false,
            isPlaying: false,
            currentVideoParticipant: '',
        });
        var labelEl = btn.querySelector('.vpc-chrome-btn__label');
        var displayLabel = navState.label || genericLabel;
        if (labelEl) {
            labelEl.textContent = displayLabel;
        } else {
            btn.textContent = displayLabel;
        }
        if (navState.isActive) {
            btn.classList.add('is-active');
        } else {
            btn.classList.remove('is-active');
        }
        if (typeof window.vpcSyncChromeButtonWidths === 'function') {
            window.vpcSyncChromeButtonWidths();
        }
    }

    function syncDeafHearingFromSession() {
        var btn = bar.querySelector('.vpc-deaf-hearing-btn');
        if (!btn) {
            return;
        }
        var session = readSession();
        var tagOn = !!(
            session
            && session.filterState
            && typeof session.filterState.tag === 'string'
            && session.filterState.tag !== ''
        );
        if (tagOn) {
            btn.classList.add('is-active');
            btn.setAttribute('aria-pressed', 'true');
        } else {
            btn.classList.remove('is-active');
            btn.setAttribute('aria-pressed', 'false');
        }
        if (typeof window.vpcSyncChromeButtonWidths === 'function') {
            window.vpcSyncChromeButtonWidths();
        }
    }

    syncParticipantsNavFromSession();
    syncDeafHearingFromSession();

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

    var deafBtn = bar.querySelector('.vpc-deaf-hearing-btn');
    if (deafBtn && !deafBtn.disabled) {
        bind(deafBtn, 'deaf-hearing');
    }
}());
