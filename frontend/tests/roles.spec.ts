import { test, expect } from "@playwright/test";

for (const [role, email, home, heading] of [
  ["admin", "admin@clsu.demo", "/app/admin", "Dashboard"],
  ["personnel", "mark@clsu.demo", "/app/work", "My assigned work"],
  ["requester", "andrea@clsu.demo", "/app/requester", "Good morning, Andrea."],
])
  test(`${role} login opens the correct workspace and survives refresh`, async ({
    page,
  }) => {
    await page.goto("/login?role=" + role);
    if (role === "admin") {
      await page
        .getByLabel("Email address", { exact: true })
        .fill("mark@clsu.demo");
      await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
      await page.getByRole("button", { name: "Sign in", exact: true }).click();
      await expect(page.getByRole("alert")).toContainText(
        "valid admin account",
      );
    }
    await page.getByLabel("Email address", { exact: true }).fill(email);
    await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(new RegExp(home + "$"));
    await expect(
      page.getByRole("heading", { name: heading, exact: true, level: 1 }),
    ).toBeVisible();
    await page.reload();
    await expect(
      page.getByRole("heading", { name: heading, exact: true, level: 1 }),
    ).toBeVisible();
    if (role === "personnel") {
      await expect(
        page.getByRole("link", { name: "New request", exact: true }),
      ).toHaveCount(0);
      await expect(
        page.getByRole("link", { name: "Feedback history", exact: true }),
      ).toHaveCount(0);
      await page.goto("/app/admin");
      await expect(page).toHaveURL(/\/app\/work$/);
      await page.goto("/app/requests/new");
      await expect(page).toHaveURL(/\/app\/requests$/);
    }
    if (role === "requester") {
      await page.goto("/login?role=admin");
      await expect(
        page.getByRole("heading", { name: "Switch account to continue" }),
      ).toBeVisible();
      page.once("dialog", (dialog) => dialog.accept());
      await page
        .getByRole("button", { name: "Sign out and switch account" })
        .click();
      await expect(
        page.getByRole("heading", { name: "Sign in to continue" }),
      ).toBeVisible();
      await expect(
        page.getByLabel("Email address", { exact: true }),
      ).toBeVisible();
    }
    if (role === "admin") {
      await page.goto("/app/requests/new");
      await expect(
        page.getByRole("heading", {
          name: "New service request",
          exact: true,
          level: 1,
        }),
      ).toBeVisible();
      await expect(page.locator(".submission-tabs")).toHaveCount(0);
    }
    await page.setViewportSize({ width: 1440, height: 1124 });
    if (role !== "requester") {
      await page.goto(home);
      await expect(page.locator(".loading")).toHaveCount(0);
      await page.screenshot({
        path: "../.impeccable/review/role-" + role + "-desktop.png",
        fullPage: true,
        animations: "disabled",
      });
      await page.setViewportSize({ width: 429, height: 1074 });
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true);
      await page.screenshot({
        path: "../.impeccable/review/role-" + role + "-mobile.png",
        fullPage: true,
        animations: "disabled",
      });
    }
  });
