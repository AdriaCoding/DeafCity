<?php

/**
 * Studio configuration template.
 *
 * Copy this file to config.php and fill in the real values.
 * config.php is gitignored — never commit real credentials.
 */

// ─── Studio auth ─────────────────────────────────────────────────────────────

/** Plaintext password for the Studio admin login screen. */
define('STUDIO_PASSWORD', 'change-me');

/** Session lifetime in seconds (default: 24 hours). */
define('STUDIO_SESSION_LIFETIME', 86400);

// ─── Vimeo API ────────────────────────────────────────────────────────────────

/** OAuth 2 client ID from https://developer.vimeo.com/apps */
define('VIMEO_CLIENT_ID', '');

/** OAuth 2 client secret from the same Vimeo app. */
define('VIMEO_CLIENT_SECRET', '');

/** Personal access token with at least "public" and "private" scopes. */
define('VIMEO_ACCESS_TOKEN', '');

// ─── Gemini AI (subtitle translation) ────────────────────────────────────────

/**
 * Google AI Studio API key.
 *
 * Create one at https://aistudio.google.com/app/apikey
 * The key is injected into the translation subprocess environment at job spawn
 * time by spawnTranslationJob() in studio/index.php.
 *
 * Model used: gemini-2.0-flash (fast, cheap, supports Catalan).
 * Estimated cost: sub-cent per month at ~250K chars/month volume.
 */
define('GEMINI_API_KEY', '');
