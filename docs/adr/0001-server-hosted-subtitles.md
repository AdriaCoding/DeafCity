# Server-hosted caption files for subtitle playback

The Website plays Videos via the Vimeo player (CDN, encoding, privacy controls) but renders Subtitles from caption files stored on our server, synced by the Preview site player model — not from Vimeo native text tracks.

Vimeo text tracks are harder to manage than server-side WebVTT during Studio iteration: Producers edit Subtitles locally, Publication saves `.vtt` files under `data/captions/`, and the player fetches them via a static endpoint. The legacy homepage still uses `?texttrack=` today; the modern Website (starting from `/preview/`) will replace that approach.

**Considered options:** Vimeo text tracks only (current production home); server VTT only (chosen); both with Vimeo as backup (rejected for now — dual source of truth).
