# Dupli — Duplicate Media Finder for Nextcloud

Dupli scans your Nextcloud media library and finds duplicate photos and videos using perceptual hashing.

![Screenshot 113313](./img/Screenshot%202026-03-31%20113313.png)
![Screenshot 113333](./img/Screenshot%202026-03-31%20113333.png)
![Screenshot 113352](./img/Screenshot%202026-03-31%20113352.png)
![Screenshot 113406](./img/Screenshot%202026-03-31%20113406.png)
![Screenshot 165120](./img/Screenshot%202026-03-31%20165120.png)
![Screenshot 165145](./img/Screenshot%202026-03-31%20165145.png)
![Screenshot 165219](./img/Screenshot%202026-03-31%20165219.png)


## Features
- Perceptual hash detection (dHash, pHash, wHash)
- Bulk delete with glob filter patterns (e.g. `IMG*`)
- Folder protection rules
- Audit log with CSV export
- Inline image preview

## Requirements
- Nextcloud 25+
- Python 3 with `imagehash`, `Pillow`, `numpy`, `pymysql`

## Installation
Download the latest release zip and extract to your Nextcloud `apps/` directory, then enable via `occ app:enable urbanduplicati`.
