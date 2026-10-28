import { expect, test, type APIRequestContext } from "@playwright/test";

const origin = "http://127.0.0.1:5174";
const headers = { Origin: origin };
async function login(client: APIRequestContext, email: string, role: string) {
  expect((await client.post("/api/login", { headers, data: { email, role, password: "DemoPass!2026" } })).ok()).toBeTruthy();
}
async function requestData(client: APIRequestContext, subject: string) {
  const catalog = await (await client.get("/api/catalog")).json();
  return { service_type_id: catalog.service_types[0].id, subject, description: "Synthetic live-update request for cross-session regression verification.", location: "Synthetic test laboratory", urgency: "Low", impact: "Individual" };
}

test("new feedback and requests appear with notifications without reloading", async ({ page, request }, testInfo) => {
  await login(page.request, "admin@clsu.demo", "staff");
  await login(request, "andrea@clsu.demo", "requester");
  const initial = await (await page.request.get("/api/notifications?feed=1")).json();
  const firstFeed = page.waitForResponse(response => new URL(response.url()).pathname === "/api/notifications");
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.goto("/app/feedback");
  await firstFeed;
  await expect(page.locator(".feedback-workspace .loading")).toHaveCount(0);
  const marker = await page.evaluate(() => performance.timeOrigin);
  const toast = page.getByRole("complementary", { name: "New notification" });
  await expect(toast).toHaveCount(0); // Existing unread notices do not replay on sign-in.
  const subject = `Synthetic live feedback ${Date.now()}`;
  expect((await request.post("/api/feedback", { headers, data: { category: "Suggestion", subject, message: "Synthetic cross-session feedback received without a manual refresh." } })).status()).toBe(201);
  await expect(page.getByRole("link", { name: subject, exact: true })).toBeVisible({ timeout: 15000 });
  await expect(toast).toContainText("New feedback received.");
  await expect(page.locator(".notification-count")).toHaveText(String(initial.unread_count + 1));
  expect(await page.evaluate(() => performance.timeOrigin)).toBe(marker);
  await page.screenshot({ path: testInfo.outputPath("live-feedback-desktop.png"), fullPage: true, animations: "disabled" });
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(toast.getByRole("button", { name: "Dismiss notification" })).toBeVisible();
  await expect.poll(async () => { const box = await page.locator(".sidebar").boundingBox(); return box!.x + box!.width; }).toBeLessThanOrEqual(1);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath("live-feedback-mobile.png"), fullPage: true, animations: "disabled" });
  const target = await toast.getByRole("link", { name: "View update" }).getAttribute("href");
  expect(target).toMatch(/^\/app\/feedback\/general\/\d+$/);
  await toast.getByRole("link", { name: "View update" }).click();
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(subject);
  await page.getByRole("link", { name: "Notifications", exact: true }).click();
  const row = page.locator(".notification-row").filter({ has: page.locator(`a[href="${target}"]`) });
  await row.getByRole("button", { name: "Mark read", exact: true }).click();
  await expect(row).not.toHaveClass(/unread/);
  if (initial.unread_count) await expect(page.locator(".notification-count")).toHaveText(String(initial.unread_count));
  else await expect(page.locator(".notification-count")).toHaveCount(0);

  const requestSubject = `Synthetic live request ${Date.now()}`;
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.goto("/app/requests");
  await page.getByLabel("Search requests", { exact: true }).fill(requestSubject);
  await expect(page.getByRole("heading", { name: "No matching requests" })).toBeVisible();
  const requestMarker = await page.evaluate(() => performance.timeOrigin);
  expect((await request.post("/api/requests", { headers, data: await requestData(request, requestSubject) })).status()).toBe(201);
  await expect(page.getByRole("link", { name: requestSubject, exact: true })).toBeVisible({ timeout: 15000 });
  await expect(toast).toContainText("New service request received.");
  await expect(page.getByLabel("Search requests", { exact: true })).toHaveValue(requestSubject);
  expect(await page.evaluate(() => performance.timeOrigin)).toBe(requestMarker);
});

test("background updates preserve staff drafts and stale-version protection", async ({ page, request }) => {
  await login(request, "andrea@clsu.demo", "requester");
  const created = await (await request.post("/api/requests", { headers, data: await requestData(request, `Synthetic draft protection ${Date.now()}`) })).json();
  await login(request, "admin@clsu.demo", "staff");
  const catalog = await (await request.get("/api/catalog")).json();
  const personnel = catalog.personnel.find((person: { service_type_ids: number[] }) => person.service_type_ids.includes(created.service_type_id));
  const assigned = await (await request.patch(`/api/requests/${created.id}`, { headers, data: { action: "manage", expected_version: created.record_version, personnel_id: personnel.id, status: "Assigned" } })).json();
  await login(page.request, "admin@clsu.demo", "staff");
  await page.clock.install();
  let detailReads = 0;
  await page.route(`**/api/requests/${created.id}`, async route => {
    if (route.request().method() === "GET") detailReads += 1;
    await route.continue();
  });
  await page.goto(`/app/requests/${created.id}`);
  const form = page.locator("form").filter({ has: page.getByRole("button", { name: "Save update", exact: true }) });
  const draft = form.locator('textarea[name="message"]');
  await draft.fill("Synthetic unsaved staff note that must survive background refreshes.");
  const readsBefore = detailReads;
  expect((await request.patch(`/api/requests/${created.id}`, { headers, data: { action: "manage", expected_version: assigned.record_version, status: "In Progress", message: "Synthetic update committed in another staff session." } })).ok()).toBeTruthy();
  await page.clock.runFor(5500);
  expect(detailReads).toBe(readsBefore);
  await expect(draft).toHaveValue("Synthetic unsaved staff note that must survive background refreshes.");
  await expect(form.locator('select[name="status"]')).toHaveValue("Assigned");
  await form.getByRole("button", { name: "Save update", exact: true }).click();
  await expect(page.getByRole("alert")).toContainText("changed in another session");
  await expect(draft).toHaveValue("Synthetic unsaved staff note that must survive background refreshes.");
  await page.getByRole("button", { name: "Load latest request", exact: true }).click();
  await expect(form.locator('select[name="status"]')).toHaveValue("In Progress");
  await expect(draft).toHaveValue("");

  // The owner also receives status/history updates without a reload; internal notes remain private.
  await login(page.request, "andrea@clsu.demo", "requester");
  await page.goto(`/app/requests/${created.id}`);
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(created.subject);
  const current = await (await request.get(`/api/requests/${created.id}`)).json();
  const marker = await page.evaluate(() => performance.timeOrigin);
  expect((await request.patch(`/api/requests/${created.id}`, { headers, data: { action: "manage", expected_version: current.record_version, status: "On Hold", message: "Synthetic private internal diagnostic note.", visibility: "internal" } })).ok()).toBeTruthy();
  await page.clock.runFor(5500);
  await expect(page.locator(".request-summary .badge.on-hold")).toBeVisible();
  await expect(page.getByText("Synthetic private internal diagnostic note.", { exact: true })).toHaveCount(0);
  expect(await page.evaluate(() => performance.timeOrigin)).toBe(marker);
});

test("live checks recover from failures, retain content and pause while hidden or offline", async ({ page }) => {
  await page.clock.install();
  let feedCalls = 0;
  let failing = false;
  let hasNotice = false;
  let pendingFeed: Promise<void> | undefined;
  await page.route("**/api/**", async route => {
    const path = new URL(route.request().url()).pathname;
    const json = (body: unknown, status = 200) => route.fulfill({ status, json: body });
    if (path === "/api/me") return json({ id: 1, name: "Synthetic Administrator", role: "admin", email: "synthetic@example.invalid", office_id: 1 });
    if (path === "/api/catalog") return json({ offices: [], service_types: [], personnel: [], categories: [] });
    if (path === "/api/notifications") {
      feedCalls += 1;
      if (pendingFeed) await pendingFeed;
      if (failing) return json({ message: "Synthetic temporary outage." }, 503);
      return json({ unread_count: hasNotice ? 1 : 0, notifications: hasNotice ? [{ id: 1, kind: "request", service_request_id: 101, message: "Synthetic new request received.", url: "/app/requests/101", read_at: null, created_at: "2026-10-24T03:00:00Z" }] : [] });
    }
    if (path === "/api/requests") return json({ data: [{ id: 101, job_number: "FMS-SYNTHETIC-LIVE", subject: "Synthetic retained request", status: "Pending", priority: null, requester_id: 2, service_type: "Synthetic service", office: "Synthetic office", created_at: "2026-10-24T03:00:00Z" }], total: 1, current_page: 1, last_page: 1 });
    return json({ message: "Unexpected synthetic path." }, 404);
  });
  await page.goto("/app/requests");
  await expect(page.getByRole("link", { name: "Synthetic retained request", exact: true })).toBeVisible();
  failing = true;
  await page.clock.runFor(5000);
  await expect(page.getByText("Live updates interrupted. Reconnecting…", { exact: true })).toBeVisible();
  await expect(page.getByRole("link", { name: "Synthetic retained request", exact: true })).toBeVisible();
  failing = false; hasNotice = true;
  await page.clock.runFor(10000);
  await expect(page.getByRole("complementary", { name: "New notification" })).toContainText("Synthetic new request received.");
  await expect(page.getByText("Live updates interrupted. Reconnecting…", { exact: true })).toHaveCount(0);
  await page.getByRole("button", { name: "Dismiss notification" }).click();
  await page.evaluate(() => { Object.defineProperty(document, "visibilityState", { configurable: true, value: "hidden" }); document.dispatchEvent(new Event("visibilitychange")); });
  const beforeHidden = feedCalls;
  await page.clock.runFor(15000);
  expect(feedCalls).toBe(beforeHidden);
  await page.evaluate(() => { Object.defineProperty(document, "visibilityState", { configurable: true, value: "visible" }); document.dispatchEvent(new Event("visibilitychange")); });
  await expect.poll(() => feedCalls).toBeGreaterThan(beforeHidden);
  await expect(page.getByRole("complementary", { name: "New notification" })).toHaveCount(0);
  await page.evaluate(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value: false }); });
  const beforeOffline = feedCalls;
  await page.clock.runFor(15000);
  expect(feedCalls).toBe(beforeOffline);
  const reconnected = page.waitForResponse(response => new URL(response.url()).pathname === "/api/notifications");
  await page.evaluate(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value: true }); window.dispatchEvent(new Event("online")); });
  await (await reconnected).finished();
  await expect.poll(() => feedCalls).toBeGreaterThan(beforeOffline);
  let release = () => {};
  pendingFeed = new Promise<void>(resolve => { release = resolve; });
  const slowStarted = feedCalls;
  await page.evaluate(() => window.dispatchEvent(new Event("focus")));
  await expect.poll(() => feedCalls).toBeGreaterThan(slowStarted);
  const duringSlowRead = feedCalls;
  await page.clock.runFor(10000);
  expect(feedCalls).toBe(duringSlowRead); // Slow reads never overlap another poll of this resource.
  const finished = page.waitForResponse(response => new URL(response.url()).pathname === "/api/notifications");
  pendingFeed = undefined;
  release();
  await finished;
});
