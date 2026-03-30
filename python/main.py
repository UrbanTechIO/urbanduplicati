#!/usr/bin/env python3
"""
UrbanDuplicati scanner — entry point.
Usage: python3 main.py -t <task_id>
"""
import argparse
import logging
import os
import sys

# Set up logging to file and stderr
log_level = os.environ.get('UD_LOGLEVEL', 'INFO').upper()
logging.basicConfig(
    level=getattr(logging, log_level, logging.INFO),
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    stream=sys.stderr,
)
log = logging.getLogger('ud_main')

# Add app directory to path so we can import our modules
APP_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, APP_DIR)

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='UrbanDuplicati background scanner')
    parser.add_argument('-t', dest='task_id', type=int, required=False,
                        help='Task ID to process')
    args = parser.parse_args()

    if not args.task_id:
        parser.print_help()
        sys.exit(1)

    log.info('UrbanDuplicati scanner starting, task_id=%d', args.task_id)
    log.info('SERVER_ROOT=%s', os.environ.get('SERVER_ROOT', 'not set'))

    try:
        from scanner import run_task
        run_task(args.task_id)
    except Exception as e:
        log.exception('Fatal error: %s', e)
        sys.exit(1)

    sys.exit(0)
