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
The Nextcloud App Store installs only the app itself. The scanner is a Python script, so these must exist on the
**server** (inside the Nextcloud container if you use Docker):

| Component | Needed | Link |
|-----------|--------|------|
| Nextcloud 25+ | required | |
| Python 3 (`/usr/bin/python3`) | required | https://www.python.org/downloads/ |
| Pillow | required | https://pypi.org/project/pillow/ |
| NumPy | required | https://pypi.org/project/numpy/ |
| PyMySQL | required | https://pypi.org/project/PyMySQL/ |
| ImageHash | required | https://pypi.org/project/ImageHash/ |
| pillow-heif | optional (HEIC/HEIF photos) | https://pypi.org/project/pillow-heif/ |
| FFmpeg | optional (video scanning) | https://ffmpeg.org/download.html |

On Debian/Ubuntu:

```bash
apt-get update && apt-get install -y python3-pip python3-pil python3-numpy python3-pymysql ffmpeg
python3 -m pip install --break-system-packages imagehash pillow-heif
```

Dupli checks these itself: if something required is missing, a banner at the top of the app names it, links to it and
shows the command above, and new scans are refused until it is fixed (instead of staying "pending").

**Docker note:** the Nextcloud image is replaced on every update, which removes packages installed inside it. Put the
commands above in a script under `/docker-entrypoint-hooks.d/before-starting/` so they are re-applied on every start.

## Installation
Download the latest release zip and extract to your Nextcloud `apps/` directory, then enable via `occ app:enable urbanduplicati`.
