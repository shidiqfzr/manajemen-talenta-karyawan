// Menyiapkan basis data SQLite sementara lalu menjalankan server Laravel untuk pengujian E2E.
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const tmp = resolve(root, 'tests/e2e/.tmp');
const database = resolve(tmp, 'e2e.sqlite');
const port = process.env.E2E_PORT ?? '8765';

mkdirSync(tmp, { recursive: true });
rmSync(database, { force: true });
writeFileSync(database, '');

const env = {
    ...process.env,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
};

const migrate = spawnSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], { cwd: root, env, stdio: 'inherit' });
if (migrate.status !== 0) {
    process.exit(migrate.status ?? 1);
}

for (const script of ['seed-import-batch.php', 'seed-import-states.php']) {
    const seed = spawnSync('php', ['artisan', 'tinker', `--execute=include 'tests/e2e/support/${script}';`], { cwd: root, env, stdio: 'inherit' });
    if (seed.status !== 0) {
        process.exit(seed.status ?? 1);
    }
}

const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`], { cwd: root, env, stdio: 'inherit' });
const stop = () => server.kill();
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
process.on('exit', stop);
server.on('exit', (code) => process.exit(code ?? 0));
