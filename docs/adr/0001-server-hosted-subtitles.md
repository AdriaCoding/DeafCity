# Server-hosted caption files for subtitle playback

The Website plays Videos via the Vimeo player (CDN, encoding, privacy controls) but renders Subtitles from caption files stored on our server, synced by the Preview site player model — not from Vimeo native text tracks at playback time.

During Studio work, Subtitles live only in the Job folder until Publication. The Video itself is uploaded to Vimeo directly by the Producer before Intake (see [ADR-0003](0003-producer-uploads-video-to-vimeo-directly.md)). **Publication** then (1) uploads all reviewed WebVTT files to Vimeo as text tracks, and (2) saves the same files under `data/captions/` for the Catalog and Preview player. The server copy is what the modern player fetches; Vimeo tracks are the publish-time mirror for embeds and legacy `?texttrack=` compatibility. Re-Publish after subtitle edits must update both copies.

The legacy homepage still uses Vimeo `?texttrack=` today; the Preview site player replaces that for playback but Publication still pushes tracks to Vimeo.

**Considered options:** Vimeo text tracks only (legacy home); server VTT only at playback with Vimeo sync at Publication (chosen); Vimeo-only with no server files (rejected — breaks Preview player and Studio iteration).
