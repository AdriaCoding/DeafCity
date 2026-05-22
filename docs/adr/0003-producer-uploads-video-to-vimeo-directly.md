# Producer uploads Video to Vimeo directly; Studio takes a Vimeo ID

The Studio does not upload Videos to Vimeo. The Producer uploads Videos to Vimeo by any means available to them (Vimeo web UI, batch upload tools, Vimeo mobile app) before opening the Studio. At Intake the Producer pastes the Vimeo URL or numeric ID; the Studio extracts the numeric ID, calls `GET /videos/{id}` to fetch and store the Video title, and stores the `vimeo_id` in `job.json`.

**Publication** therefore no longer includes a video upload step. It only: (1) uploads WebVTT text tracks to Vimeo for each reviewed caption file, (2) copies the same files to `data/captions/`, and (3) writes the Catalog entry.

**Considered options:**

- *TUS upload from browser to Vimeo at Intake* — eliminated PHP upload-size limits and kept the pipeline self-contained, but introduced Vimeo API complexity at Intake (TUS token, progress tracking, cancel/cleanup logic) and prevented batch pre-upload workflows the Producer already relies on.
- *Chunked upload to server, then Vimeo at Publication* — avoids PHP limits but stores large video files on the server during the whole pipeline, requires reassembly logic, and still blocks batch pre-upload.
- *Producer uploads directly; Studio takes Vimeo ID* (chosen) — zero server storage for the Video, no upload complexity in the Studio, allows the Producer to batch-upload a shoot's worth of Videos before any subtitle work begins. The only new Studio call is a single `GET /videos/{id}` at Intake to confirm the ID is valid and retrieve the title.
