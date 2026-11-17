import { test, expect } from "@playwright/test";

for (const [email, home] of [["admin@clsu.demo", "/app/admin"], ["mark@clsu.demo", "/app/work"], ["andrea@clsu.demo", "/app/requester"]]) {
  test(`shared sign-in routes ${email} by account role`, async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("link", { name: "Sign in", exact: true })).toHaveCount(1);
    await expect(page.getByRole("link", { name: "Staff login", exact: true })).toHaveCount(0);
    await page.getByRole("link", { name: "Sign in", exact: true }).click();
    await page.getByLabel("Email address", { exact: true }).fill(email);
    await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(new RegExp(home + "$"));
  });
}

test("legacy requester link accepts staff credentials", async ({ page }) => {
  await page.goto("/login?role=requester");
  await page.getByLabel("Email address", { exact: true }).fill("admin@clsu.demo");
  await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).toHaveURL(/\/app\/admin$/);
});
