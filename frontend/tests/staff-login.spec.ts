import { test, expect } from "@playwright/test";

for (const [email, home] of [["admin@clsu.demo", "/app/admin"], ["mark@clsu.demo", "/app/work"]]) {
  test(`shared staff login routes ${email} by account role`, async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("link", { name: "Personnel sign in", exact: true })).toHaveCount(0);
    await expect(page.getByRole("link", { name: "Admin login", exact: true })).toHaveCount(0);
    await page.getByRole("link", { name: "Staff login", exact: true }).click();
    await expect(page).toHaveURL(/\/login\?role=staff$/);
    await page.getByLabel("Email address", { exact: true }).fill(email);
    await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(new RegExp(home + "$"));
    await page.goto("/login?role=staff");
    await expect(page).toHaveURL(new RegExp(home + "$"));
  });
}

test("staff entry rejects requester credentials without creating a session", async ({ page }) => {
  await page.goto("/login?role=staff");
  await page.getByLabel("Email address", { exact: true }).fill("andrea@clsu.demo");
  await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page.getByRole("alert")).toContainText("valid staff account");
  const response = await page.request.get("/api/me", { headers: { Accept: "application/json" } });
  expect(response.status()).toBe(401);
});
