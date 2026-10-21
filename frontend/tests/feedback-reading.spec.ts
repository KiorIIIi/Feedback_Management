import { test, expect } from "@playwright/test";

test("admin opens the complete feedback message and supplied contact details", async ({ page }) => {
  const message = "Synthetic multiline feedback.\n".repeat(100) + "Final feedback sentence.";
  const submitted = await page.request.post("/api/feedback", {
    headers: { Origin: "http://127.0.0.1:5174" },
    data: { category: "Suggestion", subject: "Synthetic full reading check", message,
      contact_name: "Synthetic Tester", contact_email: "synthetic@example.invalid", unverified_job_number: "UNVERIFIED-READING" },
  });
  expect(submitted.status()).toBe(201);
  await page.goto("/login?role=staff");
  await page.getByLabel("Email address", { exact: true }).fill("admin@clsu.demo");
  await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).toHaveURL(/\/app\/admin$/);
  await page.goto("/app/feedback");
  const row = page.locator(".feedback-row").filter({ has: page.getByRole("heading", { name: "Synthetic full reading check", exact: true }) });
  await expect(row).not.toContainText("Final feedback sentence.");
  await row.getByRole("link", { name: "View feedback", exact: true }).click();
  await expect(page.locator(".feedback-full-message")).toHaveText(message);
  await expect(page.getByText("synthetic@example.invalid", { exact: true })).toBeVisible();
  await expect(page.getByText("UNVERIFIED-READING", { exact: true })).toBeVisible();
  await expect(page.getByRole("link", { name: "UNVERIFIED-READING", exact: true })).toHaveCount(0);
  await page.reload();
  await expect(page.locator(".feedback-full-message")).toHaveText(message);
  await page.setViewportSize({ width: 390, height: 844 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
});
