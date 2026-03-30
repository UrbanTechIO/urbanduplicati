"""
db.py — reads Nextcloud config.php and provides a MySQL connection.
"""
import os
import re
import pymysql
import pymysql.cursors

_conn = None
_config = None


def _parse_config():
    """Parse Nextcloud config.php to extract DB credentials."""
    server_root = os.environ.get('SERVER_ROOT', '/var/www/html')
    config_path = os.path.join(server_root, 'config', 'config.php')

    with open(config_path, 'r') as f:
        content = f.read()

    def get_value(key):
        # Match 'key' => 'value' or 'key' => "value"
        m = re.search(r"'%s'\s*=>\s*'([^']*)'" % key, content)
        if m:
            return m.group(1)
        m = re.search(r"'%s'\s*=>\s*\"([^\"]*)\"" % key, content)
        if m:
            return m.group(1)
        return None

    return {
        'host':    get_value('dbhost') or 'localhost',
        'db':      get_value('dbname') or 'nextcloud',
        'user':    get_value('dbuser') or 'nextcloud',
        'passwd':  get_value('dbpassword') or '',
        'prefix':  get_value('dbtableprefix') or 'oc_',
        'datadir': get_value('datadirectory') or '/var/www/html/data',
    }


def get_config():
    global _config
    if _config is None:
        _config = _parse_config()
    return _config


def get_connection():
    global _conn
    if _conn is None or not _conn.open:
        cfg = get_config()
        host = cfg['host']
        port = 3306
        if ':' in host:
            host, port = host.rsplit(':', 1)
            port = int(port)
        _conn = pymysql.connect(
            host=host,
            port=port,
            user=cfg['user'],
            password=cfg['passwd'],
            database=cfg['db'],
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True,
        )
    return _conn


def fetchall(sql, params=()):
    conn = get_connection()
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return cur.fetchall()


def fetchone(sql, params=()):
    rows = fetchall(sql, params)
    return rows[0] if rows else None


def execute(sql, params=()):
    conn = get_connection()
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return cur.rowcount


def lastinsertid():
    conn = get_connection()
    with conn.cursor() as cur:
        cur.execute('SELECT LAST_INSERT_ID() as id')
        return cur.fetchone()['id']


def prefix():
    return get_config()['prefix']


def table(name):
    return prefix() + name


def close():
    global _conn
    if _conn:
        _conn.close()
        _conn = None
