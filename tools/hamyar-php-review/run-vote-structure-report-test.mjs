// آزمون واحد تجمیع ساختار رأی با PHP واقعی در WASM و $wpdb شبیه‌سازی‌شده.
// به وردپرس، پایگاه داده یا شبکه وصل نمی‌شود و فقط فایل گزارش را بارگذاری می‌کند.
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const repo = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const require = createRequire(process.env.HJ_PHP_WASM_ROOT ? resolve(process.env.HJ_PHP_WASM_ROOT, 'package.json') : import.meta.url);
const { PHP } = await import(require.resolve('@php-wasm/universal'));
const { loadNodeRuntime, createNodeFsMountHandler } = await import(require.resolve('@php-wasm/node'));
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 1 } }));
await php.mount('/repo', createNodeFsMountHandler(repo));
await php.mount('/plugin', createNodeFsMountHandler(resolve(repo, 'plugins/hamyar-doctors')));
const result = await php.run({
  code: "<?php define('HJ_VOTE_STRUCTURE_UNIT_TEST', true); require '/repo/plugins/hamyar-doctors/tests/vote-structure-report-unit.php';",
});
process.stdout.write(result.text);
if (result.errors) process.stderr.write(result.errors);
if (result.exitCode !== 0 || !result.text.includes('DONE: vote structure aggregate unit test.')) process.exit(1);
process.exit(0);
