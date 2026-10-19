import { test, expect, type Page } from "@playwright/test";
import path from "node:path";
const out = path.resolve("../.impeccable/review");
async function login(page: Page, email: string) {
  await page.goto("/login");
  await page.getByLabel("Email address", { exact: true }).fill(email);
  await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).toHaveURL(
    email.startsWith("admin") ? /\/app\/admin$/ : /\/app\/requester$/,
  );
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
}
async function assetsAndOverflow(page: Page) {
  await expect(page.locator(".loading")).toHaveCount(0);
  await page.evaluate(() => document.fonts.ready);
  await expect
    .poll(async () =>
      page
        .locator("img")
        .evaluateAll((imgs) =>
          imgs.every((i) => i.complete && i.naturalWidth > 0),
        ),
    )
    .toBe(true);
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > innerWidth,
  );
  expect(overflow, "Page should not overflow horizontally").toBe(false);
}
test("desktop and mobile visual evidence uses original local Figma assets", async ({
  page,
}) => {
  await page.setViewportSize({ width: 1440, height: 1124 });
  await page.goto("/");
  await expect(
    page.getByRole("heading", { name: "How can we help?", exact: true }),
  ).toBeVisible();
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/portal-desktop.png",
    fullPage: true,
    animations: "disabled",
  });
  await page.setViewportSize({ width: 429, height: 1074 });
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/portal-mobile.png",
    fullPage: true,
    animations: "disabled",
  });
  await page.setViewportSize({ width: 1440, height: 1124 });
  await page.goto("/login?role=admin");
  await expect(
    page.getByRole("heading", { name: "Sign in to continue" }),
  ).toBeVisible();
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/login-desktop.png",
    fullPage: true,
    animations: "disabled",
  });
  await login(page, "admin@clsu.demo");
  await page.setViewportSize({ width: 1276, height: 1684 });
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/desktop.png",
    fullPage: true,
    animations: "disabled",
  });
  await page.goto("/app/requests");
  await page.getByRole("heading", { name: "All service requests" }).waitFor();
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/services-desktop.png",
    fullPage: true,
    animations: "disabled",
  });
  await page
    .getByRole("link", {
      name: "Internet connectivity issue in laboratory",
      exact: true,
    })
    .click();
  await page
    .getByRole("heading", { name: "Issue details", exact: true })
    .waitFor();
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/detail-desktop.png",
    fullPage: true,
    animations: "disabled",
  });
  await page.setViewportSize({ width: 429, height: 1074 });
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/mobile.png",
    fullPage: true,
    animations: "disabled",
  });
  page.once("dialog", (dialog) => dialog.accept());
  await page.getByRole("button", { name: "Sign out" }).click();
  await login(page, "andrea@clsu.demo");
  await page.setViewportSize({ width: 1440, height: 1447 });
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/requester-desktop.png",
    fullPage: true,
    animations: "disabled",
  });
  await page.goto("/app/requests/new");
  await page.getByRole("heading", { name: "Your information" }).waitFor();
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/submit-desktop.png",
    fullPage: true,
    animations: "disabled",
  });
  await page.setViewportSize({ width: 429, height: 1074 });
  await assetsAndOverflow(page);
  await page.screenshot({
    path: out + "/submit-mobile.png",
    fullPage: true,
    animations: "disabled",
  });
});
test("request submission persists, appears in history and filters work", async ({
  page,
}) => {
  await login(page, "andrea@clsu.demo");
  await page.getByRole("link", { name: "New request", exact: true }).click();
  await page
    .getByLabel("Service type *", { exact: true })
    .selectOption({ label: "Equipment maintenance" });
  const subject = "Browser test fan request " + Date.now();
  await page.getByLabel("Short subject *").fill(subject);
  await page
    .getByLabel("Location *", { exact: true })
    .fill("Engineering Room 204");
  await page
    .getByLabel("Detailed concern *")
    .fill(
      "The ceiling fan nearest the door no longer turns on. Please inspect the unit.",
    );
  await page
    .getByRole("button", { name: "Submit request", exact: true })
    .click();
  await expect(page).toHaveURL(/\/app\/requests\/\d+$/);
  await expect(
    page.getByRole("heading", { name: subject, exact: true, level: 1 }),
  ).toBeVisible();
  await page.reload();
  await expect(
    page.getByRole("heading", { name: subject, exact: true, level: 1 }),
  ).toBeVisible();
  await page.goto("/app/requests");
  await page.getByLabel("Search requests", { exact: true }).fill(subject);
  await expect(
    page.getByRole("link", { name: subject, exact: true }),
  ).toBeVisible();
  await page.getByLabel("Filter priority").selectOption("Critical");
  await expect(
    page.getByRole("heading", { name: "No matching requests" }),
  ).toBeVisible();
});
test("admin can export a private report through the queue worker", async ({
  page,
}) => {
  await login(page, "admin@clsu.demo");
  await page
    .getByRole("button", { name: "Export report", exact: true })
    .click();
  const download = page.getByRole("link", { name: "Download report CSV" });
  await expect(download).toBeVisible({ timeout: 30000 });
  const event = page.waitForEvent("download");
  await download.click();
  expect((await event).suggestedFilename()).toMatch(
    /clsu-service-report-\d+\.csv/,
  );
});
