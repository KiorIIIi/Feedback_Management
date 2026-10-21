import { test, expect, type Page } from "@playwright/test";
import path from "node:path";

const origin = "http://127.0.0.1:5174";
const headers = { Origin: origin };
const subject = "Wi-Fi drops during online examinations";
const reference = "FMS-2026-UNVERIFIED-SYNTHETIC-0142-LONG-REFERENCE";
const message = "The Wi-Fi connection in the Engineering laboratory drops every few minutes. During our morning online examinations, students lost their connection and had to reconnect several times.\n\n" +
  "We first noticed the problem near the rear workstations. The signal icon still appears connected, but pages stop loading. Moving closer to the entrance sometimes helps, although the connection remains unreliable.\n\n" +
  "Please inspect the access point and let the office know whether there is a temporary workaround before the next scheduled examination. A clear update would help us plan an alternative room.\n\nSynthetic evaluation content only.";
async function login(page: Page, email = "admin@clsu.demo", role = "staff") {
  const response = await page.request.post("/api/login", { headers, data: { email, role, password: "DemoPass!2026" } });
  expect(response.ok()).toBeTruthy();
}
async function capture(page: Page, name: string) {
  await expect(page.locator(".feedback-workspace .loading")).toHaveCount(0);
  await page.screenshot({ path: path.resolve("../.local/feedback-review", `${name}.png`), fullPage: true, animations: "disabled" });
}
async function noOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
}

test("real filters, query state, compact badges and full-width mobile reading", async ({ page }) => {
  for (const feedback of [
    { category: "Complaint", subject, message, rating: 2, contact_name: "Alex Rivera", contact_email: "alex.rivera@example.invalid", unverified_job_number: reference },
    { category: "Suggestion", subject: "Clearer guidance for service requests", message: "A synthetic suggestion with no supplied contact information." },
    { category: "Compliment", subject: "Helpful synthetic support", message: "Thank you for your clear assistance. This is synthetic feedback.", rating: 5 },
  ]) expect((await page.request.post("/api/feedback", { headers, data: feedback })).status()).toBe(201);
  await login(page);
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.goto("/app/feedback");
  await expect(page.getByRole("tab", { name: /General feedback/ })).toHaveAttribute("aria-selected", "true");
  await expect(page.locator(".feedback-row").filter({ hasText: subject })).toBeVisible();
  await capture(page, "desktop-list");
  await page.getByRole("searchbox", { name: "Search feedback" }).fill("Wi-Fi");
  await page.getByLabel("Rating", { exact: true }).selectOption("low");
  await page.getByLabel("Date received").selectOption("30");
  await page.getByLabel("Feedback category", { exact: true }).selectOption("Complaint");
  await page.getByRole("button", { name: "Apply filters", exact: true }).click();
  await expect(page.locator(".feedback-row")).toHaveCount(1);
  const filteredURL = page.url();
  await page.getByRole("link", { name: "View feedback", exact: true }).click();
  await expect(page.locator(".feedback-full-message")).toHaveText(message);
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(subject);
  await expect(page.getByText(reference, { exact: true })).toBeVisible();
  await expect(page.getByRole("link", { name: reference, exact: true })).toHaveCount(0);
  const geometry = await page.locator(".feedback-message-header").evaluate(node => {
    const rating = node.querySelector(".feedback-score")!.getBoundingClientRect();
    const pill = node.querySelector(".feedback-source-pill")!.getBoundingClientRect();
    return { right: pill.x > rating.right, alignment: Math.abs(rating.top - pill.top), size: getComputedStyle(node.querySelector(".feedback-source-pill")!).fontSize };
  });
  expect(geometry.right).toBeTruthy(); expect(geometry.alignment).toBeLessThan(2); expect(geometry.size).toBe("12px");
  await capture(page, "desktop-general");
  await page.reload();
  await expect(page.locator(".feedback-full-message")).toHaveText(message);
  await page.getByRole("link", { name: "Back to feedback", exact: true }).click();
  await expect(page).toHaveURL(filteredURL);
  await expect(page.getByRole("searchbox")).toHaveValue("Wi-Fi");
  await expect(page.getByLabel("Rating", { exact: true })).toHaveValue("low");
  await page.getByRole("button", { name: "Clear filters", exact: true }).click();
  await page.getByRole("link", { name: "Clearer guidance for service requests", exact: true }).click();
  await expect(page.getByText("Not rated", { exact: true })).toBeVisible();
  await expect(page.getByText("Not supplied", { exact: true })).toHaveCount(3);
  await page.getByRole("link", { name: "Back to feedback", exact: true }).click();
  await page.setViewportSize({ width: 390, height: 844 });
  await capture(page, "mobile-list");
  await page.getByRole("link", { name: subject, exact: true }).scrollIntoViewIfNeeded();
  const browsePosition = await page.evaluate(() => scrollY);
  await page.getByRole("link", { name: subject, exact: true }).click();
  await expect(page.locator(".feedback-full-message")).toHaveText(message);
  await noOverflow(page);
  await expect(page.getByRole("heading", { level: 1 })).toHaveCSS("font-size", "20px");
  await expect(page.locator(".feedback-full-message")).toHaveCSS("font-size", "16px");
  await capture(page, "mobile-general");
  await page.getByRole("link", { name: "Back to feedback", exact: true }).click();
  await expect(page.locator(".feedback-row").filter({ hasText: subject })).toBeVisible();
  await expect.poll(() => page.evaluate(() => scrollY)).toBeGreaterThan(Math.max(0, browsePosition - 5));
  await page.getByRole("link", { name: subject, exact: true }).click();
  await expect(page.locator(".feedback-full-message")).toHaveText(message);
  await page.getByRole("link", { name: "Jump to submission details" }).click();
  await expect(page.locator("#feedback-context")).toBeInViewport();
  await page.setViewportSize({ width: 320, height: 740 });
  await noOverflow(page);
  for (const img of await page.locator(".feedback-workspace img").all()) {
    expect(await img.evaluate(n => (n as HTMLImageElement).complete && (n as HTMLImageElement).naturalWidth > 0)).toBeTruthy();
    const dimensions = await img.evaluate(n => ({ rendered: n.getBoundingClientRect().width, natural: (n as HTMLImageElement).naturalWidth }));
    expect(dimensions.rendered).toBe(dimensions.natural);
  }
});

test("verified evaluation has authorized service context and a working service link", async ({ page }) => {
  await login(page, "andrea@clsu.demo", "requester");
  const response = await page.request.get("/api/requests?status=Completed");
  const requests = await response.json();
  const request = requests.data.find((record: { evaluation: unknown }) => !record.evaluation);
  expect(request).toBeTruthy();
  expect((await page.request.post(`/api/requests/${request.id}/evaluation`, { headers, data: { rating: 4, remarks: "The display is clear again. Synthetic verified service evaluation." } })).status()).toBe(201);
  await page.request.post("/api/logout", { headers, data: {} });
  await login(page);
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.goto("/app/feedback?tab=evaluation");
  const row = page.locator(".feedback-row").filter({ hasText: request.subject });
  await row.getByRole("link", { name: "View evaluation" }).click();
  await expect(page.getByText("Verified evaluation", { exact: true })).toBeVisible();
  await expect(page.locator("#feedback-context")).toContainText(request.job_number);
  await expect(page.locator("#feedback-context")).toContainText(request.assignee || "Unassigned");
  await expect(page.locator("#feedback-context")).toContainText(request.office);
  await expect(page.locator("#feedback-context")).toContainText(request.service_type);
  await capture(page, "desktop-evaluation");
  await page.setViewportSize({ width: 390, height: 844 });
  await noOverflow(page);
  await capture(page, "mobile-evaluation");
  await page.getByRole("link", { name: "Open service request", exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`/app/requests/${request.id}$`));
});

test("empty and failed results recover without removing filters", async ({ page }) => {
  await login(page);
  await page.goto("/app/feedback?search=definitely-no-synthetic-match");
  await expect(page.getByRole("heading", { name: "No matching feedback" })).toBeVisible();
  await expect(page.getByRole("searchbox")).toHaveValue("definitely-no-synthetic-match");
  await page.route("**/api/feedback?*", route => route.fulfill({ status: 503, contentType: "application/json", body: JSON.stringify({ message: "Synthetic feedback service unavailable." }) }));
  await page.reload();
  await expect(page.getByRole("alert")).toContainText("Synthetic feedback service unavailable.");
  await expect(page.getByRole("searchbox")).toHaveValue("definitely-no-synthetic-match");
  await page.unroute("**/api/feedback?*");
  await page.getByRole("button", { name: "Retry loading feedback" }).click();
  await expect(page.getByRole("heading", { name: "No matching feedback" })).toBeVisible();
});
