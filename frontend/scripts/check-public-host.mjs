import assert from "node:assert/strict";
import { readFile, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { chromium } from "@playwright/test";

const directory = new URL("../../.local/public-host/", import.meta.url);
const runtime = JSON.parse(
  await readFile(new URL("runtime.json", directory), "utf8"),
);
assert.equal(runtime.APP_ENV, "production");
assert.match(runtime.DB_DATABASE, /^clsu_public_demo_[a-f0-9]+$/);
const url = (await readFile(new URL("url.txt", directory), "utf8")).trim();
assert.equal(runtime.APP_URL, url);
assert.match(url, /^https:\/\/[a-z0-9]+(?:-[a-z0-9]+)+\.trycloudflare\.com$/);
const accounts = JSON.parse(
  await readFile(new URL("access.json", directory), "utf8"),
);
const checks = [];
const browser = await chromium.launch({ channel: "chrome" });
try {
  const context = await browser.newContext({ baseURL: url });
  const page = await context.newPage();
  page.setDefaultTimeout(20000);
  page.setDefaultNavigationTimeout(30000);
  const browserErrors = [];
  page.on("pageerror", (error) => browserErrors.push(error.message));
  const home = await page.goto("/");
  assert.equal(home.status(), 200);
  assert.match(home.headers()["content-security-policy"], /default-src 'self'/);
  await page
    .getByRole("heading", { name: "How can we help?", exact: true })
    .waitFor();
  await page.waitForLoadState("networkidle");
  assert.equal(
    await page
      .locator("img")
      .evaluateAll((images) =>
        images.every((image) => image.complete && image.naturalWidth > 0),
      ),
    true,
  );
  assert.equal((await context.request.get("/api/me")).status(), 401);
  for (const path of [
    "/.env",
    "/.local/public-host/access.json",
    "/backend/.env",
    "/scripts/public-runtime.php",
  ]) {
    assert.equal((await context.request.get(path)).status(), 404);
  }
  checks.push(
    "HTTPS frontend, original assets, response headers, unauthenticated API, and private-file isolation",
  );

  const requester = accounts.find(
    (account) => account.email === "andrea@clsu.demo",
  );
  await page.goto("/login?role=requester");
  await page.getByLabel("Email address", { exact: true }).fill(requester.email);
  await page.getByLabel("Password", { exact: true }).fill(requester.password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await page.waitForURL("**/app/requester");
  await page.reload();
  await page.getByRole("button", { name: "Sign out", exact: true }).waitFor();
  const session = (await context.cookies()).find(
    (cookie) => cookie.name === "clsu_public_demo",
  );
  assert.ok(session?.secure && session?.httpOnly);
  const me = await (await context.request.get("/api/me")).json();
  assert.equal(me.role, "requester");
  checks.push(
    "Requester UI sign-in, secure session cookie, browser refresh, and session restoration",
  );

  const headers = { Origin: url, "Sec-Fetch-Site": "same-origin" };
  async function send(client, method, path, data, expected = 200) {
    const response = await client[method](path, {
      ...(data ? { data } : {}),
      headers,
    });
    assert.equal(
      response.status(),
      expected,
      `${method.toUpperCase()} ${path}`,
    );
    return response.json();
  }
  async function signIn(email) {
    const account = accounts.find((item) => item.email === email);
    const roleContext = await browser.newContext({ baseURL: url });
    const user = await send(roleContext.request, "post", "/api/login", {
      email: account.email,
      password: account.password,
    });
    assert.equal(user.role, account.role);
    return roleContext;
  }
  const admin = await signIn("admin@clsu.demo");
  const personnel = await signIn("mark@clsu.demo");
  const catalog = await send(admin.request, "get", "/api/catalog");
  const all = await send(admin.request, "get", "/api/requests");
  const foreign = all.data.find((record) => record.requester_id !== me.id);
  assert.ok(foreign);
  assert.equal(
    (await context.request.get(`/api/requests/${foreign.id}`)).status(),
    404,
  );
  const detail = await send(
    context.request,
    "get",
    `/api/requests/${all.data.find((record) => record.requester_id === me.id).id}`,
  );
  assert.ok(detail.events.every((event) => event.visibility === "requester"));
  assert.equal((await context.request.get("/api/personnel")).status(), 404);
  checks.push(
    "Administrator and personnel sign-in, requester ownership, and internal-note privacy",
  );

  const created = await send(
    context.request,
    "post",
    "/api/requests",
    {
      subject: "Synthetic public-host verification",
      description:
        "Synthetic test request verifying the public demo deployment. No campus work is requested.",
      location: "Synthetic verification location",
      service_type_id: catalog.service_types[0].id,
      urgency: "Low",
      impact: "Individual",
      requesting_office_id: me.office_id,
    },
    201,
  );
  assert.ok(created.job_number);
  const mark = catalog.personnel.find(
    (person) => person.name === "Mark Dela Cruz",
  );
  let updated = await send(
    admin.request,
    "patch",
    `/api/requests/${created.id}`,
    {
      expected_version: created.record_version,
      action: "manage",
      personnel_id: mark.id,
      priority: "Low",
      status: "Assigned",
      message: "Synthetic assignment for deployment verification.",
    },
  );
  await send(
    admin.request,
    "patch",
    `/api/requests/${created.id}`,
    {
      expected_version: created.record_version,
      action: "manage",
      message: "Synthetic stale update; must be rejected.",
    },
    409,
  );
  updated = await send(
    personnel.request,
    "patch",
    `/api/requests/${created.id}`,
    {
      expected_version: updated.record_version,
      action: "manage",
      status: "In Progress",
      message: "Synthetic deployment check is in progress.",
    },
  );
  updated = await send(
    personnel.request,
    "patch",
    `/api/requests/${created.id}`,
    {
      expected_version: updated.record_version,
      action: "manage",
      status: "Completed",
      message: "Verified the synthetic public-host workflow.",
      outcome:
        "Synthetic verification completed; no physical service was performed.",
    },
  );
  assert.equal(updated.status, "Completed");
  await send(
    context.request,
    "post",
    `/api/requests/${created.id}/evaluation`,
    { rating: 5, remarks: "Synthetic deployment verification." },
    201,
  );
  await send(
    context.request,
    "post",
    `/api/requests/${created.id}/evaluation`,
    { rating: 5 },
    422,
  );
  checks.push(
    "Synthetic submission, assignment, staff progress/completion, stale-version rejection, and one verified evaluation",
  );

  const report = await send(
    admin.request,
    "post",
    "/api/reports",
    { days: 30 },
    202,
  );
  let outcome;
  for (let attempt = 0; attempt < 20; attempt++) {
    outcome = await send(admin.request, "get", `/api/reports/${report.id}`);
    if (["completed", "failed"].includes(outcome.status)) break;
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  assert.equal(
    outcome.status,
    "completed",
    "Queued report must complete before download",
  );
  const download = await admin.request.get(outcome.download_url);
  assert.equal(download.status(), 200);
  assert.match(await download.text(), /Control number/);
  assert.equal((await context.request.get(outcome.download_url)).status(), 404);
  const anonymous = await browser.newContext({ baseURL: url });
  assert.equal(
    (await anonymous.request.get(outcome.download_url)).status(),
    401,
  );
  assert.equal(
    (
      await anonymous.request.post("/api/login", {
        data: { email: "admin@clsu.demo", password: "DemoPass!2026" },
        headers,
      })
    ).status(),
    422,
  );
  checks.push(
    "Queued report completion, private CSV download, download authorization, and rejection of published local-demo password",
  );

  await page.goto("/app/requests");
  await page
    .getByRole("heading", { name: "My requests and history", exact: true })
    .waitFor();
  await page
    .getByRole("link", { name: "Synthetic public-host verification", exact: true })
    .first()
    .waitFor();
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({
    path: fileURLToPath(new URL("verified-desktop.png", directory)),
    fullPage: true,
  });
  await page.setViewportSize({ width: 390, height: 844 });
  assert.equal(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
    true,
  );
  assert.deepEqual(browserErrors, []);
  checks.push(
    "Authenticated deep links, desktop rendering, mobile reflow, and no JavaScript errors",
  );
  const results = { url, verifiedAt: new Date().toISOString(), checks };
  await writeFile(
    new URL("verification.json", directory),
    JSON.stringify(results, null, 2),
  );
  console.log(JSON.stringify(results, null, 2));
} finally {
  await browser.close();
}
