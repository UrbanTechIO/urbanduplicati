"""
scanner.py — walks Nextcloud file storage, hashes images/videos, finds duplicates.
Writes results to oc_ud_groups and oc_ud_group_files.
"""
import os
import time
import urllib.request
import urllib.parse
import logging
from PIL import Image, ImageOps
import imagehash
import db

log = logging.getLogger('ud_scanner')

IMAGE_MIMES = {'image/jpeg', 'image/png', 'image/gif', 'image/webp',
               'image/tiff', 'image/bmp', 'image/heic', 'image/heif'}
VIDEO_MIMES = {'video/mp4', 'video/avi', 'video/mkv', 'video/mov',
               'video/quicktime', 'video/x-msvideo', 'video/webm',
               'video/mpeg', 'video/3gpp'}


def get_hash_fn(algo: str):
    algos = {
        'dhash':   imagehash.dhash,
        'phash':   imagehash.phash,
        'whash':   imagehash.whash,
        'average': imagehash.average_hash,
    }
    return algos.get(algo, imagehash.dhash)


def hamming(h1, h2) -> int:
    return h1 - h2


def get_nc_file_path(fileid: int, datadir: str) -> str | None:
    """
    Look up the actual filesystem path for a Nextcloud file by fileid.
    Uses oc_filecache to find storage + path, then resolves to disk path.
    """
    row = db.fetchone(
        '''SELECT fc.path, s.id as storage_id, s.numeric_id
           FROM oc_filecache fc
           JOIN oc_storages s ON s.numeric_id = fc.storage
           WHERE fc.fileid = %s''',
        (fileid,)
    )
    if not row:
        return None

    storage_id = row['storage_id']
    rel_path = row['path']

    # Local storage: id looks like 'local::/path/to/data/user/'
    if storage_id.startswith('local::'):
        base = storage_id[7:]  # strip 'local::'
        full = os.path.join(base, rel_path)
        if os.path.exists(full):
            return full

    # home:: storage: 'home::userid'
    if storage_id.startswith('home::'):
        user = storage_id[6:]
        full = os.path.join(datadir, user, 'files', rel_path.replace('files/', '', 1))
        if os.path.exists(full):
            return full
        # try direct
        full2 = os.path.join(datadir, user, rel_path)
        if os.path.exists(full2):
            return full2

    return None


def get_target_files(task_settings: dict) -> list[dict]:
    """
    Returns list of {fileid, filename, filepath, filesize, mimetype} for all
    eligible files in the target directories.
    """
    target_dir_ids = task_settings['target_directory_ids']
    target_mtype   = task_settings['target_mtype']
    datadir        = db.get_config()['datadir']

    # Determine which mime types to include
    if target_mtype == 0:
        mime_parts = ('image',)
    elif target_mtype == 1:
        mime_parts = ('video',)
    else:
        mime_parts = ('image', 'video')

    files = []
    visited = set()

    def scan_dir(fileid):
        if fileid in visited:
            return
        visited.add(fileid)

        children = db.fetchall(
            '''SELECT fc.fileid, fc.name, fc.path, fc.size, fc.mimetype,
                      m.mimetype as mime_str, mp.mimetype as mime_part,
                      s.id as storage_id
               FROM oc_filecache fc
               JOIN oc_mimetypes m  ON m.id  = fc.mimetype
               JOIN oc_mimetypes mp ON mp.id = fc.mimepart
               JOIN oc_storages  s  ON s.numeric_id = fc.storage
               WHERE fc.parent = %s''',
            (fileid,)
        )

        for child in children:
            if child['mime_part'] == 'httpd/unix-directory' or child['mime_str'] == 'httpd/unix-directory':
                scan_dir(child['fileid'])
            elif child['mime_part'] in mime_parts:
                # Resolve disk path
                storage_id = child['storage_id']
                rel_path = child['path']
                disk_path = None
                if storage_id.startswith('local::'):
                    base = storage_id[7:]
                    p = os.path.join(base, rel_path)
                    if os.path.exists(p):
                        disk_path = p
                elif storage_id.startswith('home::'):
                    user = storage_id[6:]
                    p = os.path.join(datadir, user, rel_path)
                    if os.path.exists(p):
                        disk_path = p

                if disk_path:
                    files.append({
                        'fileid':   child['fileid'],
                        'filename': child['name'],
                        'filepath': os.path.dirname(child['path']),
                        'filesize': child['size'],
                        'mimetype': child['mime_str'],
                        'mime_part': child['mime_part'],
                        'disk_path': disk_path,
                    })

    for dir_id in target_dir_ids:
        scan_dir(int(dir_id))

    return files


def hash_image(disk_path: str, algo: str, hash_size: int, exif_transpose: bool):
    try:
        img = Image.open(disk_path)
        if exif_transpose:
            img = ImageOps.exif_transpose(img)
        img = img.convert('RGB')
        return get_hash_fn(algo)(img, hash_size=hash_size)
    except Exception as e:
        log.warning('Failed to hash image %s: %s', disk_path, e)
        return None



def hash_video(disk_path, algo, hash_size):
    import subprocess, tempfile
    try:
        with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as tmp:
            tmp_path = tmp.name
        for seek in ["1", "0"]:
            r = subprocess.run(["ffmpeg", "-y", "-ss", seek, "-i", disk_path, "-frames:v", "1", "-q:v", "2", tmp_path], capture_output=True, timeout=30)
            if r.returncode == 0 and os.path.exists(tmp_path) and os.path.getsize(tmp_path) > 0:
                break
        if os.path.exists(tmp_path) and os.path.getsize(tmp_path) > 0:
            img = Image.open(tmp_path).convert("RGB")
            h = get_hash_fn(algo)(img, hash_size=hash_size)
            os.unlink(tmp_path)
            return h
    except Exception as e:
        log.warning("Failed to hash video %s: %s", disk_path, e)
    return None

def find_duplicates(files: list[dict], task_settings: dict) -> list[list[dict]]:
    """
    Hash all files and group duplicates by hamming distance <= threshold.
    Returns list of groups, each group is a list of file dicts.
    """
    algo       = task_settings['hashing_algorithm']
    hash_size  = task_settings['hash_size']
    threshold  = task_settings['precision']
    exif       = task_settings['exif_transpose']
    target_mtype = task_settings['target_mtype']

    import math
    hashed = []
    total = len(files)
    task_id = task_settings['id']

    for i, f in enumerate(files):
        h = None
        if f['mime_part'] == 'image':
            h = hash_image(f['disk_path'], algo, hash_size, exif)
        elif f['mime_part'] == 'video':
            h = hash_video(f['disk_path'], algo, hash_size)
        if h is not None:
            hashed.append((h, f))

        # Update progress every 10 files
        if (i + 1) % 10 == 0 or i == total - 1:
            db.execute(
                'UPDATE oc_ud_tasks SET files_scanned = %s WHERE id = %s',
                (i + 1, task_id)
            )

    # Group by hamming distance
    used = set()
    groups = []
    for i, (h1, f1) in enumerate(hashed):
        if i in used:
            continue
        group = [f1]
        used.add(i)
        for j, (h2, f2) in enumerate(hashed):
            if j in used or j == i:
                continue
            if hamming(h1, h2) <= threshold:
                group.append(f2)
                used.add(j)
        if len(group) > 1:
            groups.append(group)

    return groups


def save_results(task_id: int, groups: list[list[dict]]):
    """Write duplicate groups to oc_ud_groups and oc_ud_group_files."""
    # Clear old results for this task
    db.execute('DELETE FROM oc_ud_group_files WHERE task_id = %s', (task_id,))
    db.execute('DELETE FROM oc_ud_groups WHERE task_id = %s', (task_id,))

    for group_id, group in enumerate(groups, start=1):
        # Compute a representative hash string (use first file's hash as label)
        db.execute(
            'INSERT INTO oc_ud_groups (task_id, group_id, hash) VALUES (%s, %s, %s)',
            (task_id, group_id, '')
        )
        for f in group:
            db.execute(
                '''INSERT INTO oc_ud_group_files
                   (task_id, group_id, fileid, filename, filepath, filesize)
                   VALUES (%s, %s, %s, %s, %s, %s)''',
                (task_id, group_id, f['fileid'], f['filename'], f['filepath'], f['filesize'])
            )

    log.info('Saved %d duplicate groups for task %d', len(groups), task_id)


def run_task(task_id: int):
    """Main entry point for running a scan task."""
    log.info('Starting scan task %d', task_id)

    # Load task from DB
    task = db.fetchone(
        'SELECT * FROM oc_ud_tasks WHERE id = %s',
        (task_id,)
    )
    if not task:
        log.error('Task %d not found', task_id)
        return

    import json
    collector_settings = json.loads(task['collector_settings']) if isinstance(task['collector_settings'], str) else task['collector_settings']
    target_dir_ids     = json.loads(task['target_directory_ids']) if isinstance(task['target_directory_ids'], str) else task['target_directory_ids']

    # Compute hamming threshold from similarity_threshold
    hash_size = int(collector_settings.get('hash_size', 16))
    sim_threshold = int(collector_settings.get('similarity_threshold', 90))
    num_bits = hash_size ** 2
    if sim_threshold == 100:
        precision = int(hash_size / 8)
    else:
        precision = num_bits - int(round(num_bits / 100.0 * sim_threshold))
        if precision == 0:
            precision = 1

    task_settings = {
        'id':                  task_id,
        'target_directory_ids': target_dir_ids,
        'target_mtype':        int(collector_settings.get('target_mtype', 2)),
        'hashing_algorithm':   collector_settings.get('hashing_algorithm', 'dhash'),
        'hash_size':           hash_size,
        'precision':           precision,
        'exif_transpose':      bool(collector_settings.get('exif_transpose', True)),
    }

    # Mark task as running
    import os
    db.execute(
        'UPDATE oc_ud_tasks SET py_pid = %s, files_scanned = 0, errors = %s WHERE id = %s',
        (os.getpid(), '', task_id)
    )

    try:
        # Get all eligible files
        log.info('Scanning directories: %s', target_dir_ids)
        files = get_target_files(task_settings)
        log.info('Found %d files to scan', len(files))

        # Update files_total
        db.execute(
            'UPDATE oc_ud_tasks SET files_total = %s WHERE id = %s',
            (len(files), task_id)
        )

        if not files:
            log.warning('No files found for task %d', task_id)
            db.execute(
                'UPDATE oc_ud_tasks SET finished_time = %s, py_pid = 0 WHERE id = %s',
                (int(time.time()), task_id)
            )
            return

        # Find duplicates
        groups = find_duplicates(files, task_settings)
        log.info('Found %d duplicate groups', len(groups))

        # Save results
        save_results(task_id, groups)

        # Mark finished
        db.execute(
            'UPDATE oc_ud_tasks SET finished_time = %s, py_pid = 0, files_scanned = %s WHERE id = %s',
            (int(time.time()), len(files), task_id)
        )
        log.info('Task %d completed successfully', task_id)

        # Send Nextcloud notification if enabled
        try:
            secret = db.get_app_value('urbanduplicati', 'internal_secret', '')
            base_url = db.get_config().get('base_url', 'http://localhost')
            if secret:
                url = base_url.rstrip('/') + '/index.php/apps/urbanduplicati/api/v1/notify/' + str(task_id)
                data = urllib.parse.urlencode({'secret': secret}).encode()
                req = urllib.request.Request(url, data=data, method='POST')
                req.add_header('OCS-APIREQUEST', 'true')
                urllib.request.urlopen(req, timeout=10)
                log.info('Notification sent for task %d', task_id)
        except Exception as ne:
            log.warning('Could not send notification for task %d: %s', task_id, ne)

    except Exception as e:
        log.exception('Task %d failed: %s', task_id, e)
        db.execute(
            'UPDATE oc_ud_tasks SET errors = %s, py_pid = 0, finished_time = %s WHERE id = %s',
            (str(e)[:500], int(time.time()), task_id)
        )
