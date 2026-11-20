import { test, expect } from "@playwright/test";

const subject = "Signage at the alumni homecoming booth";
const label = "University Week 2026";

async function login(page: import("@playwright/test").Page, email = "admin@clsu.demo", role = "staff") {
  const response = await page.request.post("/api/login", {
    headers: { Origin: "http://127.0.0.1:5174" },
    data: { email, role, password: "DemoPass!2026" },
  });
  expect(response.ok()).toBeTruthy();
}

test.use({ permissions: ["clipboard-read", "clipboard-write"] });

test("share panel builds a labeled link, QR and downloads", async ({ page }) => {
  await login(page);
  await page.goto("/app/feedback");
  await page.getByRole("button", { name: "Share feedback form" }).click();
  const link = page.getByLabel("Generated feedback form link");
  await expect(link).toHaveValue("http://127.0.0.1:5174/feedback");
  await expect(page.locator(".feedback-share-qr-frame svg")).toBeVisible();
  await expect(page.locator(".feedback-share-qr canvas")).toHaveCount(1);
  await page.getByLabel("Event or collection label (optional)").fill(label);
  await expect(link).toHaveValue(`http://127.0.0.1:5174/feedback?event=${encodeURIComponent(label)}`);
  await page.getByRole("button", { name: "Copy link" }).click();
  await expect(page.getByRole("button", { name: "Copied ✓" })).toBeVisible();
  expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(
    `http://127.0.0.1:5174/feedback?event=${encodeURIComponent(label)}`,
  );
  const [download] = await Promise.all([
    page.waitForEvent("download"),
    page.getByRole("button", { name: "Download PNG" }).click(),
  ]);
  expect(download.suggestedFilename()).toBe("clsu-feedback-form-university-week-2026.png");
});

test("public labeled link records the collection source on the response", async ({ page }) => {
  await page.goto(`/feedback?event=${encodeURIComponent(label)}`);
  await expect(page.locator(".feedback-source-note")).toContainText(label);
  await page.getByLabel("Feedback category *").selectOption("Suggestion");
  await page.getByLabel("Short subject *").fill(subject);
  await page
    .getByLabel("Your feedback *")
    .fill("The booth signage was hard to read from the pathway. A synthetic feedback message for the shared-link test.");
  await page.getByRole("button", { name: "Submit feedback" }).click();
  await expect(page.getByRole("heading", { name: "Thank you for your feedback!" })).toBeVisible();
  await login(page);
  await page.goto("/app/feedback");
  await page
    .locator(".feedback-row")
    .filter({ hasText: subject })
    .getByRole("link", { name: /View feedback/ })
    .click();
  await expect(page.getByText("Collection source · shared link")).toBeVisible();
  await expect(page.locator(".feedback-context-card")).toContainText(label);
});
