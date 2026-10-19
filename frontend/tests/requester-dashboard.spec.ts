import { test, expect } from "@playwright/test";
test("requester dashboard matches the selected Figma structure with live counts and local assets", async ({
  page,
}) => {
  await page.goto("/login?role=requester");
  await page
    .getByLabel("Email address", { exact: true })
    .fill("andrea@clsu.demo");
  await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).toHaveURL(/\/app\/requester$/);
  await expect(page.locator(".dashboard-metric")).toHaveCount(4);
  await expect(
    page.getByText("Awaiting my rating", { exact: true }),
  ).toBeVisible();
  const response = await page.request.get("/api/requester-dashboard");
  expect(response.ok()).toBe(true);
  const data = await response.json();
  await expect(
    page.locator(".dashboard-metric").first().locator("strong"),
  ).toHaveText(String(data.counts.total).padStart(2, "0"));
  await expect(
    page.locator(".dashboard-metric").last().locator("strong"),
  ).toHaveText(String(data.counts.awaiting_rating).padStart(2, "0"));
  await expect(page.locator(".dashboard-recent tbody tr")).toHaveCount(
    Math.min(data.recent.length, 4),
  );
  if (data.clarification) {
    await page.getByRole("link", { name: "Provide clarification" }).click();
    await expect(page).toHaveURL(
      new RegExp("/app/requests/" + data.clarification.id + "$"),
    );
    await page.goto("/app/requester");
    await expect(page.locator(".dashboard-metric")).toHaveCount(4);
  }
  await page.evaluate(() => document.fonts.ready);
  for (const width of [1440, 429]) {
    await page.setViewportSize({ width, height: 1447 });
    await expect
      .poll(() =>
        page
          .locator("img")
          .evaluateAll((images) =>
            images.every((i) => i.complete && i.naturalWidth > 0),
          ),
      )
      .toBe(true);
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true);
    if (width === 1440) {
      const a = await page.locator(".dashboard-recent").boundingBox();
      const b = await page.locator(".dashboard-side").boundingBox();
      expect(a!.x + a!.width).toBeLessThan(b!.x);
    }
    await page.screenshot({
      path: "../.impeccable/review/requester-match-" + width + ".png",
      fullPage: true,
      animations: "disabled",
    });
  }
  await page
    .getByRole("link", { name: "Rate completed service", exact: true })
    .click();
  await expect(page).toHaveURL(/\/app\/requests\/\d+\/rate$/);
});
