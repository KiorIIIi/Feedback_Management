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
  await page.getByRole("tab", { name: "Quick label link" }).click();
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

test("event form accepts responses within its window and closes on demand", async ({ page }) => {
  const title = "Freshman Orientation 2026";
  const subject = "Orientation booth suggestions";
  await login(page);
  await page.goto("/app/feedback");
  await page.getByRole("button", { name: "Share feedback form" }).click();
  await page.getByLabel("Form title *").fill(title);
  await page.getByLabel("Closes (optional)").fill("2026-12-31T23:59");
  await page.getByRole("button", { name: "Create form", exact: true }).click();
  const row = page.locator(".feedback-form-row").filter({ hasText: title });
  await expect(row).toBeVisible();
  await expect(row).toContainText("Open");
  const link = await page.getByLabel("Generated feedback form link").inputValue();
  expect(link).toMatch(/\/feedback\/f\/[0-9a-f]{32}$/);
  await expect(page.locator(".feedback-share-qr-frame svg")).toBeVisible();

  // The public event form shows its collection window and accepts a response.
  await page.goto(link);
  await expect(page.locator(".feedback-source-note")).toContainText(title);
  await expect(page.locator(".feedback-source-note")).toContainText("Accepting responses until");
  await page.getByLabel("Feedback category *").selectOption("Suggestion");
  await page.getByLabel("Short subject *").fill(subject);
  await page
    .getByLabel("Your feedback *")
    .fill("The orientation booths were well organized. A synthetic response through an event form link.");
  await page.getByRole("button", { name: "Submit feedback" }).click();
  await expect(page.getByRole("heading", { name: "Thank you for your feedback!" })).toBeVisible();

  // Closing the form stops new responses at the same link.
  await page.goto("/app/feedback");
  await page.getByRole("button", { name: "Share feedback form" }).click();
  await row.getByRole("button", { name: "Close", exact: true }).click();
  await expect(row).toContainText("Closed");
  await page.goto(link);
  await expect(
    page.getByRole("heading", { name: "This form is no longer accepting responses" }),
  ).toBeVisible();
  await expect(page.locator(".feedback-form-closed")).toContainText(title);
  await expect(page.getByRole("link", { name: "‹ Back to options" })).toBeVisible();

  // The submitted response is attributed to the event form.
  await page.goto("/app/feedback");
  await page
    .locator(".feedback-row")
    .filter({ hasText: subject })
    .getByRole("link", { name: /View feedback/ })
    .click();
  await expect(page.getByText("Collection source · shared link")).toBeVisible();
  await expect(page.locator(".feedback-context-card")).toContainText(title);
});
