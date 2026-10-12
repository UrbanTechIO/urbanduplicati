# Changelog

## 1.2.0 - 2026-10-12
### Added
- Dependency check: a banner lists missing server components (Python 3, Pillow, NumPy, PyMySQL, ImageHash, optional pillow-heif and FFmpeg) with links and the install command
- `GET /api/v1/dependencies` endpoint reporting the status of each component
- Database migration that creates the app tables on fresh installs (contributed by @Nuhser)
- README requirements table and Docker guidance

### Changed
- New scans are refused with a clear error (HTTP 412) when required components are missing, instead of staying "pending" forever
- `info.xml` declares `python3` as a required command; supports Nextcloud 25 to 35

### Fixed
- Scans stuck at "pending" after a Nextcloud image update wiped the Python packages
- Removed a leftover debug file from the package

## 1.1.3 - 2026-05-13
### Added
- Per-file selection checkboxes inside each duplicate group card with "Delete X selected" button for manual precise cleanup
- Stop button in the bulk-delete progress overlay to halt the operation between batches
- Conflict resolution modal (Rule 4): when the largest unprotected file is bigger than the largest protected file, the group is skipped and a resolution dialog is shown at the end

### Changed
- Rule 1 (all-unprotected groups): now keeps the LARGEST file and deletes the rest, instead of deleting all
- Rule 3 ("Delete all but one protected copy" opt-in): now keeps the single LARGEST protected file and deletes everything else
- Bulk delete now processes one group per HTTP request (batch size 1) to stay within proxy timeout limits
- Duplicate files are permanently deleted, bypassing the Nextcloud trash bin — prevents multi-minute copy-to-trash operations that caused timeouts for large video files

### Fixed
- Bulk delete no longer fails or times out on large video files: trash bin copy step is bypassed, enabling fast permanent deletion
- Cloudflare 524 timeout errors eliminated for large video group deletes
- HTTP 500 errors on video deletes caused by failed trash-bin move operations are resolved
- Bulk delete operation no longer aborts on a single failed batch; failures are logged and skipped gracefully


## 1.1.2 - 2026-05-11
### Added
- Hash cache: perceptual hashes are stored in the database after the first scan and reused on subsequent runs — only new or changed files are re-hashed, making re-scans of large libraries (400 GB+) dramatically faster
- BK-tree comparison: replaced the O(n²) pairwise hash comparison with an O(n log n) BK-tree nearest-neighbour search, reducing the grouping phase from hours to seconds for large libraries

### Fixed
- HEIC/HEIF photos (iPhone) now supported via pillow-heif — previously skipped silently
- Very large images (>89 MP) no longer crash the scanner (PIL decompression bomb limit raised to 300 MP)
- Scan completion notification now uses the internal container URL instead of the external Cloudflare domain, fixing broken notifications


## 1.1.1 - 2026-05-10
### Fixed
- Nextcloud 33 compatibility: replace removed OC_App::getAppPath() and OC::$server->getConfig() with supported NC33 APIs
- App now boots correctly on Nextcloud 33.x without crashing every request


## 1.1.0 - 2026-03-30
### Added
- Perceptual hash duplicate detection (dHash, pHash, wHash)
- Bulk delete with glob filter patterns
- Protection rules for folders
- Audit log with CSV export
- Inline image preview lightbox
- Scan progress polling

### Fixed
- Filter pattern correctly applied during bulk delete
- Group cleanup respects active filter
