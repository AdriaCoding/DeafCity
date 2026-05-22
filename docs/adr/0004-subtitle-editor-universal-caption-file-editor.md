# Subtitle Editor is a universal caption file editor, reused across pipeline slices

The Subtitle Editor (Slice 2) is designed as a universal caption file editor rather than a one-off review screen for the Intake VTT. The same view, JS, and PHP handler serve every pipeline step that requires a Producer to author or correct subtitle cues: reviewing an uploaded caption file (Slice 2), correcting auto-generated cues (Slice 3), and reviewing translations (Slice 4).

**Considered options:**

- *One-off per slice* — build a focused, minimal editor for Slice 2, then fork or duplicate it for Slices 3 and 4 as those slices are designed. Lower upfront cost; each slice gets an editor tailored to its context.
- *Universal editor from Slice 2* (chosen) — design the editor as a generic component from the start. Slices 3 and 4 add behaviour to the shared surface rather than duplicating it. Higher upfront cost; eliminates the divergence risk.

The universal approach was chosen because:
1. The core editing loop — cue list + Vimeo player, edit text/timestamps, integrity check, save — is identical across all three slices. The only slice-specific variation is what file is loaded, what file is saved, and what `job.step` the pipeline advances to.
2. Forking the editor per slice would create three copies of the bidirectional player sync, the cue CRUD logic, the overlap validator, and the save handler. Any correction to the editor UX would require changes in three places.
3. The `VttParser` and `CaptionFileIntegrityChecker` PHP modules are already generic by construction — they operate on cue arrays, not on specific filenames or pipeline steps. The view and JS follow the same boundary.

When future slices extend the editor (e.g., cue split/merge for Slice 3), all new behaviour is added to the shared components.
