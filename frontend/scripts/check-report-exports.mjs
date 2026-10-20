import assert from "node:assert/strict";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { chromium } from "@playwright/test";

const root = new URL("../../.local/public-host/", import.meta.url);
const output = new URL("reports-qa/", root);
await mkdir(output, { recursive: true });
const url = (await readFile(new URL("url.txt", root), "utf8")).trim();
const runtime = JSON.parse(await readFile(new URL("runtime.json", root), "utf8"));
assert.match(runtime.DB_DATABASE, /^clsu_public_demo_[a-f0-9]+$/);
assert.equal(runtime.APP_URL, url);
const accounts = JSON.parse(await readFile(new URL("access.json", root), "utf8"));
const admin = accounts.find(account => account.role === "admin");
const browser = await chromium.launch({ channel: "chrome" });
const checks = [];
try {
  const context = await browser.newContext({ baseURL: url, viewport: { width: 1440, height: 1100 } });
  const page = await context.newPage();
  page.setDefaultTimeout(30000);
  page.setDefaultNavigationTimeout(45000);
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  await page.goto("/login?role=staff");
  await page.getByLabel("Email address", { exact: true }).fill(admin.email);
  await page.getByLabel("Password", { exact: true }).fill(admin.password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await page.waitForURL("**/app/admin");
  await page.goto("/app/reports");
  await page.getByRole("heading", { name: "Requests by category", exact: true }).waitFor();
  await page.getByLabel("Reporting period", { exact: true }).selectOption("month");
  const month = await page.getByLabel("Report month", { exact: true }).inputValue();
  assert.match(month, /^\d{4}-\d{2}$/);
  await page.getByRole("heading", { name: "Requests by category", exact: true }).waitFor();
  const response = await context.request.get(`/api/report-summary?month=${month}`);
  assert.equal(response.status(), 200);
  const report = await response.json();
  assert.equal(await page.locator(".stat").first().locator("strong").textContent(), String(report.summary.total));
  for (const key of ["categories", "priorities", "statuses", "personnel"]) {
    assert.equal(report[key].reduce((total, row) => total + row.total, 0), report.summary.total);
  }
  checks.push("Public monthly summary and all four breakdown totals agree");
  for (const format of ["pdf", "xlsx", "csv"]) {
    await page.getByLabel("Export format", { exact: true }).selectOption(format);
    await page.getByRole("button", { name: "Export report", exact: true }).click();
    const link = page.getByRole("link", { name: `Download report ${format.toUpperCase()}` });
    await link.waitFor({ timeout: 60000 });
    const href = await link.getAttribute("href");
    const event = page.waitForEvent("download");
    await link.click();
    const download = await event;
    assert.match(download.suggestedFilename(), new RegExp(`\\.${format}$`));
    await download.saveAs(fileURLToPath(new URL(`report.${format}`, output)));
    const anonymous = await browser.newContext({ baseURL: url });
    assert.equal((await anonymous.request.get(href)).status(), 401);
    await anonymous.close();
    checks.push(`Public queue completed ${format.toUpperCase()} and download rejects anonymous access`);
  }
  await page.evaluate(() => document.fonts.ready);
  for (const [name, width, height] of [["desktop", 1440, 1100], ["mobile", 390, 844]]) {
    await page.setViewportSize({ width, height });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    await page.screenshot({ path: fileURLToPath(new URL(`${name}.png`, output)), fullPage: true, animations: "disabled" });
    checks.push(`${name} report page fits viewport`);
  }
  const feedback = await (await context.request.get("/api/feedback")).json();
  if (feedback.general.length) {
    const id = feedback.general[0].id;
    const detail = await (await context.request.get(`/api/feedback/general/${id}`)).json();
    await page.goto("/app/feedback");
    await page.locator(`a[href='/app/feedback/general/${id}']`).filter({ hasText: "View full feedback" }).click();
    await page.locator(".feedback-full-message").waitFor();
    assert.equal(await page.locator(".feedback-full-message").textContent(), detail.record.message);
    for (const [name, width, height] of [["desktop", 1440, 1100], ["mobile", 390, 844]]) {
      await page.setViewportSize({ width, height });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
      await page.screenshot({ path: fileURLToPath(new URL(`feedback-${name}.png`, output)), fullPage: true, animations: "disabled" });
    }
    const anonymous = await browser.newContext({ baseURL: url });
    assert.equal((await anonymous.request.get(`/api/feedback/general/${id}`)).status(), 401);
    await anonymous.close();
    checks.push("Public feedback list opens the complete private message on desktop/mobile and rejects anonymous detail access");
  }
  if (feedback.evaluations.length) {
    const id = feedback.evaluations[0].request_id;
    await page.goto(`/app/feedback/evaluation/${id}`);
    await page.getByRole("link", { name: /Open service request/ }).waitFor();
    checks.push("Verified evaluation detail links to its authorized service request");
  }
  assert.deepEqual(errors, []);
  await writeFile(new URL("verification.json", output), JSON.stringify({ checked_at: new Date().toISOString(), url, month, summary: report.summary, checks, browser_errors: errors }, null, 2));
  console.log(JSON.stringify({ checks, summary: report.summary }, null, 2));
} finally { await browser.close(); }
