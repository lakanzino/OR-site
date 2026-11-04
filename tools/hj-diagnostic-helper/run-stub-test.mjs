// Isolated PHP WASM test: no WordPress and no database.
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const repo = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const require = createRequire(process.env.HJ_PHP_WASM_ROOT ? resolve(process.env.HJ_PHP_WASM_ROOT, 'package.json') : import.meta.url);
const { PHP } = await import(require.resolve('@php-wasm/universal'));
const { loadNodeRuntime, createNodeFsMountHandler } = await import(require.resolve('@php-wasm/node'));
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 1 } }));
await php.mount('/repo', createNodeFsMountHandler(repo));
const result = await php.run({ code: "<?php define('HJ_HELPER_ISOLATED_TEST', true); require '/repo/tools/hj-diagnostic-helper/tests/stub.php';" });
process.stdout.write(result.text);
if (result.errors) process.stderr.write(result.errors);
process.exit(result.exitCode === 0 && result.text.includes('DONE: isolated diagnostic helper stub fixture.') ? 0 : 1);
