import { test, expect } from "@playwright/test";

test("staff categories feed monthly reports and PDF/Excel downloads", async ({ page }) => {
  test.setTimeout(90000);
  await page.goto("/login?role=staff");
  await page.getByLabel("Email address", { exact: true }).fill("admin@clsu.demo");
  await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).toHaveURL(/\/app\/admin$/);
  await page.goto("/app/reports");
  const category = `Synthetic reporting ${Date.now()}`;
  await page.getByLabel("New category", { exact: true }).fill(category);
  await page.getByRole("button", { name: "Add category", exact: true }).click();
  await expect(page.getByRole("status")).toContainText("added. Assign it");
  const pending = await page.evaluate(async () => {
    const res = await fetch("/api/requests?status=Pending", { headers: { Accept: "application/json" } });
    return (await res.json()).data[0];
  });
  await page.goto(`/app/requests/${pending.id}`);
  await page.getByLabel("Request category", { exact: true }).selectOption({ label: category });
  await page.getByRole("button", { name: "Save update", exact: true }).click();
  await expect(page.getByRole("status")).toContainText("The request was updated.");
  await page.goto("/app/reports");
  await expect(page.getByRole("rowheader", { name: category, exact: true })).toBeVisible();
  await page.getByLabel("Reporting period", { exact: true }).selectOption("month");
  await expect(page.getByLabel("Report month", { exact: true })).toHaveValue(/\d{4}-\d{2}/);
  for (const format of ["pdf", "xlsx"]) {
    await page.getByLabel("Export format", { exact: true }).selectOption(format);
    await page.getByRole("button", { name: "Export report", exact: true }).click();
    const link = page.getByRole("link", { name: `Download report ${format.toUpperCase()}` });
    await expect(link).toBeVisible({ timeout: 30000 });
    const event = page.waitForEvent("download"); await link.click();
    expect((await event).suggestedFilename()).toMatch(new RegExp(`\\.${format}$`));
  }
  await page.getByLabel("Report month", { exact: true }).fill("2000-01");
  await expect(page.getByRole("heading", { name: "No requests in this period", exact: true })).toBeVisible();
  await expect(page.locator(".stat").nth(2).locator("strong")).toHaveText("—");
  await expect(page.locator(".stat").nth(3).locator("strong")).toHaveText("—");
});
