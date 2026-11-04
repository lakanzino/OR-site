// Separate PHP requests because constants cannot be redefined within one request.
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const repo = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const require = createRequire(process.env.HJ_PHP_WASM_ROOT ? resolve(process.env.HJ_PHP_WASM_ROOT, 'package.json') : import.meta.url);
const { PHP } = await import(require.resolve('@php-wasm/universal'));
const { loadNodeRuntime, createNodeFsMountHandler } = await import(require.resolve('@php-wasm/node'));
const modes = { undefined: '', false: "define('HJ_ALLOW_AUTO_SEED', false);", string: "define('HJ_ALLOW_AUTO_SEED', 'true');", true: "define('HJ_ALLOW_AUTO_SEED', true);" };
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 1 } }));
await php.mount('/repo', createNodeFsMountHandler(repo));
for (const [mode, definition] of Object.entries(modes)) {
  const result = await php.run({ code: `<?php define('HJ_ISOLATED_SEED_HOOK_TEST', true); define('HJ_TEST_GUARD_MODE', '${mode}'); ${definition} require '/repo/tools/hamyar-php-review/seed-wrapper-guard-test.php';` });
  process.stdout.write(result.text);
  if (result.errors) process.stderr.write(result.errors);
  if (result.exitCode !== 0 || !result.text.includes(`PASS: direct wrapper guard mode ${mode}`)) process.exit(1);
}
process.exit(0);
