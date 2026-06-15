# Changelog

All notable changes to Image Governance are recorded here.

## 0.1.9 - 2026-06-15

- Added an All Media collection control as the first Media Library term.
- Preserved the current Media Library mode when filtering by collection terms.
- Highlighted the active collection filter, including All Media when no collection filter is active.
- Made dropping an image onto All Media remove its collection terms.

## 0.1.8 - 2026-06-15

- Removed the governance modal close icon and dismiss button.
- Made saving governance details the only way to close the upload governance modal.

## 0.1.7 - 2026-06-15

- Fixed Recount Usage control so it submits the plugin scan action instead of inheriting Media Library bulk action state.
- Changed Media Library collection term clicks to filter the library by that collection.
- Updated Unknown / None authority filtering to include missing, empty, and legacy null values.
- Set new image attachments to authority level `0` when no authority value exists.

## 0.1.6 - 2026-06-15

- Simplified the Media Library collection bar to show only collection terms.
- Added server-backed pending upload detection so list-mode and editor uploads can trigger governance prompts.
- Added duplicate image filename prevention before upload, with the existing file URL in the error.
- Added best-effort source metadata capture for uploads that provide an original source URL.

## 0.1.5 - 2026-06-15

- Moved Recount Usage into a refresh icon in the Usage Count column header.
- Removed Manage Collections and collection picker controls from the Media Library top bar.
- Made collection buttons assign selected images when clicked and accept image drops.
- Improved grid-mode collection drag handling.
- Fixed Image Collections count links to show assigned image counts and open a filtered Media Library view.

## 0.1.4 - 2026-06-15

- Tightened GitHub updater cache handling so equal-version release responses are short-lived.
- Ignored old release-cache formats that could hide newly published updates.
- Added latest GitHub version metadata to the plugin row for diagnostics.

## 0.1.3 - 2026-06-15

- Limited the governance modal to newly uploaded images only.
- Removed modal triggers from existing image selection, bulk selection, and image detail viewing.
- Removed the separate Tools scanner page and kept recounting as a Media Library action.
- Added post type labels to image usage output.
- Added clearer collection drop targets and an Assign Selected fallback control.

## 0.1.2 - 2026-06-15

- Improved GitHub update detection on the WordPress Plugins screen.
- Reduced stale release cache risk after publishing a new GitHub release.
- Cleared the plugin release cache after successful upgrader runs.

## 0.1.1 - 2026-06-15

- Added a media-modal intervention for images missing governance details.
- Removed the repeated "Image Governance" prefix from attachment field labels.
- Added visible Media Library Recount Usage and Manage Collections controls.
- Added collection assignment checkboxes to the image detail screen.
- Added drag-and-drop collection assignment for Media Library rows and grid tiles.
- Added automatic usage indexing when public content is saved or deleted.

## 0.1.0 - 2026-06-15

- Added initial Image Governance plugin scaffold.
- Added image governance attachment metadata fields.
- Added Media Library columns, filters, and bulk actions.
- Added image collections taxonomy and drag-and-drop assignment support.
- Added manual image usage scanner.
- Added attribution page output and optional footer attribution link.
- Added GitHub release updater for native WordPress plugin updates.
