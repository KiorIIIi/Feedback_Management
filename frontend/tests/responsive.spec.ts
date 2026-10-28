import { test, expect, type Page } from "@playwright/test";

// Browser zoom changes the effective CSS viewport. These profiles cover a
// 1920x1080 display at 80–250%, smaller laptops, tablets and narrow phones.
const sizes = [
  { name: "desktop-80", width: 2400, height: 1350 },
  { name: "desktop-100", width: 1920, height: 1080 },
  { name: "desktop-125", width: 1536, height: 864 },
  { name: "desktop-150", width: 1280, height: 720 },
  { name: "desktop-200", width: 960, height: 540 },
  { name: "desktop-250", width: 768, height: 432 },
  { name: "laptop-200", width: 683, height: 384 },
  { name: "tablet", width: 820, height: 1180 },
  { name: "phone", width: 390, height: 844 },
  { name: "narrow", width: 320, height: 740 },
];
async function settled(page: Page) {
  await expect(page.locator(".loading")).toHaveCount(0);
  await page.evaluate(() => document.fonts.ready);
  await expect
    .poll(() =>
      page
        .locator("img")
        .evaluateAll((images) =>
          images.every((i) => i.complete && i.naturalWidth > 0),
        ),
    )
    .toBe(true);
}
async function fits(page: Page, route: string, width: number) {
  await settled(page);
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth + 1,
    ),
    `${route} at ${width}px overflows`,
  ).toBe(true);
}

test("public welcome, login and feedback reflow across zoom-equivalent viewport sizes", async ({
  page,
}, testInfo) => {
  for (const route of ["/", "/login?role=requester", "/feedback"]) {
    await page.goto(route);
    await expect(page.getByRole("heading", { level: 1 }).last()).toBeVisible();
    for (const size of sizes) {
      await page.setViewportSize(size);
      await fits(page, route, size.width);
      if (route === "/" && size.width > 760) {
        const bounds = await page.locator(".welcome-content").boundingBox();
        const box = await page.locator(".welcome-layout").boundingBox();
        const colStart = box!.x + box!.width * 0.45;
        const colCenter = (colStart + box!.x + box!.width) / 2;
        expect(
          Math.abs(bounds!.x + bounds!.width / 2 - colCenter),
        ).toBeLessThan(2);
        const art = await page.locator(".campus-illustration").boundingBox();
        const scene = await page.locator(".campus-scene").boundingBox();
        expect(scene!.width).toBeLessThanOrEqual(art!.width + 1);
      }
      if (
        route === "/" &&
        ["desktop-80", "desktop-200", "phone"].includes(size.name)
      )
        await page.screenshot({
          path: testInfo.outputPath(`reflow-welcome-${size.name}.png`),
          fullPage: true,
          animations: "disabled",
        });
    }
  }
});

for (const [role, email, routes] of [
  [
    "requester",
    "andrea@clsu.demo",
    [
      "/app/requester",
      "/app/requests",
      "/app/requests/new",
      "/app/feedback",
      "/app/notifications",
    ],
  ],
  [
    "personnel",
    "mark@clsu.demo",
    ["/app/work", "/app/requests", "/app/notifications"],
  ],
  [
    "admin",
    "admin@clsu.demo",
    [
      "/app/admin",
      "/app/requests",
      "/app/requests/new",
      "/app/personnel",
      "/app/reports",
    ],
  ],
] as const)
  test(`${role} pages keep content and navigation usable across viewport changes`, async ({
    page,
  }, testInfo) => {
    test.setTimeout(120000);
    await page.goto("/login?role=" + role);
    await page.getByLabel("Email address", { exact: true }).fill(email);
    await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/app\/(requester|work|admin)$/);
    for (const route of routes) {
      await page.goto(route);
      await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
      for (const size of sizes) {
        await page.setViewportSize(size);
        await fits(page, route, size.width);
      }
    }
    // Return through the real mobile navigation instead of restarting session restoration.
    await page.getByRole("button", { name: "Toggle navigation" }).click();
    await page.getByRole("link", { name: "Dashboard", exact: true }).click();
    await expect(page).toHaveURL(new RegExp(routes[0] + "$"));
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await settled(page);
    for (const size of [sizes[4], sizes[8]]) {
      await page.setViewportSize(size);
      await fits(page, routes[0], size.width);
      const toggle = page.getByRole("button", { name: "Toggle navigation" });
      await expect(toggle).toBeVisible();
      await toggle.click();
      const nav =
        role === "admin"
          ? page.locator(".sidebar.open")
          : page.locator(".mobile-requester-nav");
      await expect(nav).toBeVisible();
      await toggle.click();
      await page.screenshot({
        path: testInfo.outputPath(`reflow-${role}-${size.name}.png`),
        fullPage: true,
        animations: "disabled",
      });
    }
  });
