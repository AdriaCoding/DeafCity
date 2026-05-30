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

/**
 * Authenticated personal access token from the same Vimeo app.
 * Required scopes for Studio: private, upload, edit.
 * Generate at https://developer.vimeo.com/apps → Authentication.
 * Define once only — duplicate define() lines leave the first (stale) token active.
 */
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

// ─── Groq (subtitle transcription) ───────────────────────────────────────────

/**
 * Groq API key for the primary (cloud) transcription engine.
 *
 * Create one at https://console.groq.com/keys
 *
 * Groq transcribes Interpreter audio in ~1 s via an OpenAI-compatible Whisper
 * endpoint. On success the Producer lands straight in the Subtitle Editor; on a
 * transport/empty failure the Studio falls back to the local faster-whisper
 * engine automatically.
 *
 * Leave this blank ('') to disable the cloud engine entirely and always use the
 * local engine — the no-egress lever for hosts that never provisioned Groq.
 *
 * Billing note: a paid Groq account is required for production volumes; the free
 * tier has a low rate/upload cap (~25 MB). Interpreter voice audio egresses to
 * Groq (US cloud) on every cloud transcription — accepted by the project owner.
 */
define('GROQ_API_KEY', '');

/** Groq Whisper model id. Default: whisper-large-v3-turbo. */
define('GROQ_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo');

/**
 * Base URL of the OpenAI-compatible transcription host.
 * Default Groq: https://api.groq.com/openai/v1
 * Repoint at OpenAI or a self-hosted endpoint without code changes.
 */
define('GROQ_BASE_URL', 'https://api.groq.com/openai/v1');

/** Per-request timeout in seconds for the inline Groq call. Default: 20. */
define('GROQ_TIMEOUT_SECONDS', 20);

/**
 * Local fallback model id (faster-whisper / CTranslate2 int8), read from
 * studio/models/ in the dedicated studio/.venv. Default: whisper-large-v3-turbo.
 */
define('STUDIO_LOCAL_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo');
