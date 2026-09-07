/* caption-editor-host.js — framed caption editor talks to the video page
 * without navigating the iframe to continguts-video (that URL is XFO DENY). */
(function (root) {
    'use strict';

    var MESSAGE_TYPE = 'studio-caption-editor-done';

    function exitEditor(win, redirectUrl) {
        if (win.parent && win.parent !== win) {
            win.parent.postMessage({ type: MESSAGE_TYPE }, win.location.origin);
            return 'posted';
        }
        win.location.href = redirectUrl;
        return 'redirected';
    }

    function handleHostMessage(event, expectedOrigin, close) {
        if (event.origin !== expectedOrigin) {
            return false;
        }
        if (!event.data || event.data.type !== MESSAGE_TYPE) {
            return false;
        }
        close();
        return true;
    }

    var api = {
        MESSAGE_TYPE: MESSAGE_TYPE,
        exitEditor: exitEditor,
        handleHostMessage: handleHostMessage,
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
    root.StudioCaptionEditorHost = api;
}(typeof window !== 'undefined' ? window : globalThis));
