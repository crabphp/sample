"""An isolated full-process shop, never the user's development server."""
import json
import os
from pathlib import Path
import signal
import socket
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT.parent / 'review-2026-11-06'
STATE = ROOT / 'storage/shop-ui-runtime.json'

def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]

if sys.argv[1] == 'init':
    http, redis = free_port(), free_port()
    folder = ROOT / 'storage/shop-ui-runtime'
    folder.mkdir(exist_ok=True)
    OUT.mkdir(exist_ok=True)
    state = dict(http=http, redis=redis, container=f'crab-shop-ui-{os.getpid()}', folder=str(folder))
    env = dict(CRAB_STORAGE=str(folder), DB_DSN=f'sqlite:{folder}/shop.sqlite', DB_ASYNC='false',
        APP_URL=f'http://127.0.0.1:{http}', SERVER_ADDRESS=f'tcp://127.0.0.1:{http}', SERVER_WORKERS='2',
        REDIS_URL=f'redis://127.0.0.1:{redis}', REDIS_PREFIX='shop-ui:', BROADCAST_PREFIX='shop-ui:',
        BROADCAST_CARRIER='redis', CACHE_STORE='file', QUEUE_STORE='database',
        WAREHOUSE_URL=f'http://127.0.0.1:{http}',
        LOG_PATH=str(folder/'app.log'), MAIL_PATH=str(folder/'mail'), REPORTS_PATH=str(folder/'reports'),
        APP_DEBUG='false', CLOCKWERK_TOKEN='shop-ui-test', LOGIN_LIMIT='1000', REGISTER_LIMIT='1000', MAIL_FAIL_RATE='0')
    state['env'] = env
    STATE.write_text(json.dumps(state, indent=2))
    subprocess.run(['podman', 'run', '-d', '--name', state['container'], '-p', f'127.0.0.1:{redis}:6379', 'docker.io/library/redis:7-alpine'], check=True)
    for command in [['migrate'], ['shop:seed'], ['arc:cache']]:
        subprocess.run(['php', 'cua', *command], cwd=ROOT, env=os.environ | env, check=True)
    print(json.dumps({key: value for key,value in state.items() if key != 'env'}))
else:
    state = json.loads(STATE.read_text())
    env = os.environ | state['env']
    if sys.argv[1] == 'serve':
        os.chdir(ROOT)
        os.execvpe('php', ['php', 'cua', 'dev'], env)
    elif sys.argv[1] == 'browser':
        env |= dict(PLAYWRIGHT_MODULE=os.environ.get('PLAYWRIGHT_MODULE', 'playwright'), SHOP_SCREENSHOTS=str(OUT))
        raise SystemExit(subprocess.run(['node', '--test', 'tests/browser/shop.test.mjs'], cwd=ROOT, env=env).returncode)
    elif sys.argv[1] == 'redis-tests':
        env['SAMPLE_TEST_REDIS_URL'] = env['REDIS_URL']
        env['LOGIN_LIMIT'] = '10'
        env['REGISTER_LIMIT'] = '10'
        raise SystemExit(subprocess.run(['composer', 'test'], cwd=ROOT, env=env).returncode)
    elif sys.argv[1] == 'stop':
        pids = []
        for proc in Path('/proc').iterdir():
            if not proc.name.isdigit():
                continue
            try:
                environment = (proc / 'environ').read_bytes().split(b'\0')
                if ('CRAB_STORAGE=' + state['folder']).encode() in environment:
                    pids.append(int(proc.name))
            except (OSError, ProcessLookupError):
                pass
        for pid in pids:
            try:
                os.kill(pid, signal.SIGTERM)
            except ProcessLookupError:
                pass
        deadline = time.monotonic() + 8
        while time.monotonic() < deadline and any(Path(f'/proc/{pid}').exists() for pid in pids):
            time.sleep(.1)
        for pid in pids:
            try:
                os.kill(pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
        print('Stopped only processes with this fixture’s CRAB_STORAGE.')
    elif sys.argv[1] == 'reset':
        with socket.socket() as check:
            if check.connect_ex(('127.0.0.1', state['http'])) == 0:
                raise SystemExit('Stop the isolated server before resetting its fixture.')
        for suffix in ['', '-wal', '-shm']:
            (Path(state['folder']) / ('shop.sqlite' + suffix)).unlink(missing_ok=True)
        for command in [['migrate'], ['shop:seed']]:
            subprocess.run(['php', 'cua', *command], cwd=ROOT, env=env, check=True)
    elif sys.argv[1] == 'http-tests':
        env['SHOP_TEST_REDIS_URL'] = env['REDIS_URL']
        raise SystemExit(subprocess.run(['node', '--test', 'tests/browser/http-checkout.test.mjs'], cwd=ROOT, env=env).returncode)
    elif sys.argv[1] == 'cleanup':
        subprocess.run(['podman', 'stop', '--time', '5', state['container']], check=True)
        subprocess.run(['podman', 'rm', state['container']], check=True)
        print(f"Removed Redis. Test ports were {state['http']} and {state['redis']}.")
