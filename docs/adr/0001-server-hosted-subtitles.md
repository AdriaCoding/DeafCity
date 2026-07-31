# Server-hosted caption files for subtitle playback

The Website plays Videos through the Vimeo player for video delivery, but the Preview site renders Subtitles from Caption files stored on the server. Preview does not use Vimeo native text tracks at playback time. It reads each Video's caption references from the Catalog and fetches the corresponding WebVTT file through `preview/captions-static.php`, which returns timed cues as JSON for the outer caption layer.

The Video itself is uploaded to Vimeo directly by the Producer before Intake (see [ADR-0003](0003-producer-uploads-video-to-vimeo-directly.md)). Caption files are created or updated under `data/captions/` during Intake, Subtitle Editor review, upload, and translation. The Catalog stores the language, label, and server filename; it is the source of truth for Preview playback and Studio workflows.

**Publication** saves the caption references in the Catalog and, by default, best-effort mirrors the already-existing server files to Vimeo as text tracks. It does not obtain Caption data from Vimeo, and a Vimeo outage does not invalidate a successful server Publication. Re-Publish or an explicit bulk sync can refresh Vimeo's backup tracks after subtitle edits.

Vimeo text tracks remain useful for legacy embeds and external Vimeo playback, but they are a push-only backup representation. They must never overwrite the server Caption files or Catalog caption metadata.

**Considered options:** Vimeo text tracks as the playback source (rejected — Preview needs server-owned cues and Studio iteration); server Caption files as the playback source with Vimeo push-only backup (chosen); Vimeo-only with no server files (rejected — breaks Preview playback and Studio workflows).