// Real PHP parser and isolated hook-stub test. Requires @php-wasm/node and
// @php-wasm/universal; HJ_PHP_WASM_ROOT can point to their npm install prefix.
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repo = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const require = createRequire(process.env.HJ_PHP_WASM_ROOT
  ? resolve(process.env.HJ_PHP_WASM_ROOT, 'package.json')
  : import.meta.url);
const { PHP } = await import(require.resolve('@php-wasm/universal'));
const { loadNodeRuntime, createNodeFsMountHandler } = await import(require.resolve('@php-wasm/node'));
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 1 } }));
await php.mount('/repo', createNodeFsMountHandler(repo));
const result = await php.run({ code: `<?php
foreach (array('/repo/plugins/hamyar-doctors/includes/iran-locations-seeder.php', '/repo/plugins/hamyar-doctors/includes/demo-doctors.php', '/repo/plugins/hamyar-doctors/includes/admin-data-status.php', '/repo/plugins/hamyar-doctors/tests/admin-data-status-wp.php', '/repo/plugins/hamyar-doctors/hamyar-doctors.php', '/repo/tools/hamyar-php-review/seed-hook-test.php') as $f) {
    try { token_get_all(file_get_contents($f), TOKEN_PARSE); }
    catch (ParseError $e) { echo "FAIL syntax: $f: " . $e->getMessage() . "\\n"; exit(1); }
}
echo "PASS: PHP parser accepted all changed PHP files and harness.\\n";
define('HJ_ISOLATED_SEED_HOOK_TEST', true);
require '/repo/tools/hamyar-php-review/seed-hook-test.php';
` });
process.stdout.write(result.text);
if (result.errors) process.stderr.write(result.errors);
process.exit(result.exitCode === 0 && result.text.includes('PASS: three automatic admin hooks absent') ? 0 : 1);
