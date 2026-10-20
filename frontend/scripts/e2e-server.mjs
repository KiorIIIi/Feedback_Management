import { spawn } from "node:child_process";
import { mkdir, writeFile, access } from "node:fs/promises";
import { appendFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { randomBytes } from "node:crypto";
import net from "node:net";

const root = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);
const backend = path.join(root, "backend");
const php =
  process.env.PORTAL_TEST_PHP ||
  "C:/laragon/bin/php/php-8.5.10-Win32-vs17-x64/php.exe";
const runRoot = path.join(
  root,
  ".local",
  "e2e",
  `${Date.now()}-${process.pid}`,
);
const children = new Set();
let stopping = false;

function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  for (const child of children) child.kill();
  process.exitCode = code;
  setTimeout(() => process.exit(code), 500).unref();
}
process.on("SIGINT", () => stop());
process.on("SIGTERM", () => stop());

async function assertFree(port) {
  await new Promise((resolve, reject) => {
    const probe = net.createServer();
    probe.once("error", () =>
      reject(
        new Error(
          `E2E port ${port} is occupied; no existing service will be reused or stopped.`,
        ),
      ),
    );
    probe.listen(port, "127.0.0.1", () => probe.close(resolve));
  });
}

function start(binary, args, cwd, env) {
  const child = spawn(binary, args, {
    cwd,
    env,
    stdio: ["ignore", "pipe", "pipe"],
    windowsHide: true,
  });
  children.add(child);
  child.stdout.on("data", (chunk) =>
    appendFileSync(path.join(runRoot, "services.log"), chunk),
  );
  child.stderr.on("data", (chunk) =>
    appendFileSync(path.join(runRoot, "services.log"), chunk),
  );
  child.on("error", (error) => {
    console.error(error.message);
    stop(1);
  });
  child.on("exit", (code) => {
    children.delete(child);
    if (child.longRunning && !stopping) {
      console.error("An isolated E2E service stopped unexpectedly.");
      stop(code || 1);
    }
  });
  return child;
}

async function ready(url) {
  for (let attempt = 0; attempt < 80; attempt++) {
    try {
      if ((await fetch(url, { signal: AbortSignal.timeout(1000) })).ok) return;
    } catch {
      /* Wait only for our new isolated services. */
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  throw new Error(`Isolated E2E service did not become ready: ${url}`);
}

try {
  await access(php);
  await assertFree(8001);
  await assertFree(5174);
  for (const folder of ["", "cache", "sessions", "private", "views"])
    await mkdir(path.join(runRoot, folder), { recursive: true });
  const database = path.join(runRoot, "database.sqlite");
  // Laravel's cached-path resolver treats drive-letter paths as relative.
  const cachePath = (name) =>
    path
      .relative(backend, path.join(runRoot, "cache", name))
      .replaceAll("\\", "/");
  await writeFile(database, "", { flag: "wx" });
  const env = {
    ...process.env,
    APP_ENV: "testing",
    APP_DEBUG: "false",
    APP_URL: "http://127.0.0.1:5174",
    APP_KEY: `base64:${randomBytes(32).toString("base64")}`,
    DB_CONNECTION: "sqlite",
    DB_DATABASE: database,
    DB_URL: "",
    DATABASE_URL: "",
    SESSION_DRIVER: "file",
    SESSION_COOKIE: "clsu_portal_e2e_session",
    SESSION_FILES_PATH: path.join(runRoot, "sessions"),
    SESSION_SECURE_COOKIE: "false",
    SESSION_ENCRYPT: "true",
    CACHE_STORE: "file",
    CACHE_PREFIX: `clsu_e2e_${process.pid}`,
    CACHE_FILE_PATH: path.join(runRoot, "cache"),
    PRIVATE_STORAGE_PATH: path.join(runRoot, "private"),
    VIEW_COMPILED_PATH: path.join(runRoot, "views"),
    APP_CONFIG_CACHE: cachePath("config.php"),
    APP_ROUTES_CACHE: cachePath("routes.php"),
    APP_SERVICES_CACHE: cachePath("services.php"),
    APP_PACKAGES_CACHE: cachePath("packages.php"),
    QUEUE_CONNECTION: "database",
    DEMO_SEED: "true",
    PORTAL_TRUSTED_ORIGINS: "",
    PORTAL_API_TARGET: "http://127.0.0.1:8001",
    // Production/local login limits remain 10. Limiter behavior has separate feature tests.
    PORTAL_TEST_LOGIN_LIMIT: "120",
  };
  for (const args of [
    ["migrate", "--force", "--no-interaction"],
    ["db:seed", "--force", "--no-interaction"],
  ]) {
    const child = start(php, ["artisan", ...args], backend, env);
    const code = await new Promise((resolve) => child.once("exit", resolve));
    if (code !== 0) throw new Error("Isolated E2E database setup failed.");
  }
  const api = start(
    php,
    [
      "-S",
      "127.0.0.1:8001",
      "-t",
      path.join(backend, "public"),
      path.join(
        backend,
        "vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php",
      ),
    ],
    path.join(backend, "public"),
    env,
  );
  api.longRunning = true;
  await ready("http://127.0.0.1:8001/up");
  const worker = start(
    php,
    ["artisan", "queue:work", "--sleep=1", "--tries=3", "--timeout=90"],
    backend,
    env,
  );
  worker.longRunning = true;
  const ui = start(
    process.execPath,
    [
      "node_modules/vite/bin/vite.js",
      "--host",
      "127.0.0.1",
      "--port",
      "5174",
      "--strictPort",
    ],
    path.join(root, "frontend"),
    env,
  );
  ui.longRunning = true;
  await ready("http://127.0.0.1:5174");
  console.log(`Isolated E2E app ready. Test artifacts: ${runRoot}`);
} catch (error) {
  console.error(error.message);
  stop(1);
}
