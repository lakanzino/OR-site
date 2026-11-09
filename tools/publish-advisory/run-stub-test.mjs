import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const repo = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const require = createRequire(process.env.HJ_PHP_WASM_ROOT ? resolve(process.env.HJ_PHP_WASM_ROOT, 'package.json') : import.meta.url);
const { PHP } = await import(require.resolve('@php-wasm/universal'));
const { loadNodeRuntime, createNodeFsMountHandler } = await import(require.resolve('@php-wasm/node'));
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 1 } }));
await php.mount('/repo', createNodeFsMountHandler(repo));
const result = await php.run({ code: `<?php
foreach (array('/repo/themes/lax-theme-child/inc/publish-gate.php', '/repo/themes/lax-theme-child/inc/article-importer.php', '/repo/tools/publish-advisory/stub-test.php', '/repo/tests/integration/publish-advisory-wp.php') as $f) {
  try { token_get_all(file_get_contents($f), TOKEN_PARSE); } catch (ParseError $e) { echo "FAIL syntax: $f: " . $e->getMessage(); exit(1); }
}
echo "PASS PHP syntax\n";
require '/repo/tools/publish-advisory/stub-test.php';
` });
process.stdout.write(result.text); if (result.errors) process.stderr.write(result.errors);
process.exit(result.exitCode === 0 && result.text.includes('no override checkbox') ? 0 : 1);
