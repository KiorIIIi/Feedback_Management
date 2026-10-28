import { test, expect, type Page } from "@playwright/test";

async function signIn(page: Page, remember = false) {
  await page.goto("/login");
  await page
    .getByLabel("Email address", { exact: true })
    .fill("andrea@clsu.demo");
  await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
  await expect(page.getByLabel("Remember me")).not.toBeChecked();
  if (remember) await page.getByLabel("Remember me").check();
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).toHaveURL(/\/app\/requester$/);
  await expect(
    page.getByRole("heading", { name: "Good morning, Andrea." }),
  ).toBeVisible();
  if (!remember)
    expect(
      (await page.context().cookies()).some((cookie) =>
        cookie.name.startsWith("remember_"),
      ),
    ).toBe(false);
}

test("session survives refresh, leaving and returning, and browser history", async ({
  page,
}) => {
  await signIn(page);
  await page.goto("/app/requests");
  await expect(
    page.getByRole("heading", { name: "My requests and history" }),
  ).toBeVisible();
  await page.reload();
  await expect(
    page.getByRole("heading", { name: "My requests and history" }),
  ).toBeVisible();
  await page.goto("/");
  await page.goBack();
  await expect(page).toHaveURL(/\/app\/requests$/);
  await expect(
    page.getByRole("heading", { name: "My requests and history" }),
  ).toBeVisible();
  await page.goto("/login");
  await expect(page).toHaveURL(/\/app\/requester$/);
});

test("temporary restoration failures offer retry without redirecting to login", async ({
  page,
}) => {
  await signIn(page);
  await page.route("**/api/catalog", (route) =>
    route.fulfill({
      status: 503,
      contentType: "application/json",
      body: JSON.stringify({ message: "Temporarily unavailable" }),
    }),
  );
  await page.reload();
  await expect(
    page.getByRole("heading", { name: "Unable to restore your session" }),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/app\/requester$/);
  await expect(
    page.getByRole("button", { name: "Sign in", exact: true }),
  ).toHaveCount(0);
  await page.unroute("**/api/catalog");
  await page.getByRole("button", { name: "Retry connection" }).click();
  await expect(
    page.getByRole("heading", { name: "Good morning, Andrea." }),
  ).toBeVisible();
});

test("remembered login restores a new browser context after session cookie loss; logout revokes it", async ({
  page,
  browser,
  baseURL,
}) => {
  await signIn(page, true);
  const state = await page.context().storageState();
  state.cookies = state.cookies.filter(
    (cookie) => cookie.name !== "clsu_portal_e2e_session",
  );
  expect(
    state.cookies.some((cookie) => cookie.name.startsWith("remember_")),
  ).toBe(true);
  const restored = await browser.newContext({
    storageState: state,
    baseURL,
  });
  try {
    const tab = await restored.newPage();
    await tab.goto("/app/requests");
    await expect(
      tab.getByRole("heading", { name: "My requests and history" }),
    ).toBeVisible();
    tab.once("dialog", (dialog) => dialog.accept());
    await tab.getByRole("button", { name: "Sign out" }).click();
    await expect(tab).toHaveURL("/");
    await tab.goto("/app/requests");
    await expect(tab).toHaveURL(/\/login$/);
    await expect(
      tab.getByRole("heading", { name: "Sign in to continue" }),
    ).toBeVisible();
  } finally {
    await restored.close();
  }
});
test("sign-out confirmation can be cancelled without ending the session", async ({
  page,
  baseURL,
}) => {
  await signIn(page);
  page.once("dialog", async (dialog) => {
    expect(dialog.type()).toBe("confirm");
    expect(dialog.message()).toBe("Are you sure you want to sign out?");
    await dialog.dismiss();
  });
  await page.getByRole("button", { name: "Sign out" }).click();
  await expect(page).toHaveURL(/\/app\/requester$/);
  expect((await page.request.get("/api/me")).status()).toBe(200);
  page.once("dialog", (dialog) => dialog.accept());
  await page.getByRole("button", { name: "Sign out" }).click();
  await expect(page).toHaveURL(baseURL + "/");
  expect(
    (
      await page.request.get("/api/me", {
        headers: { Accept: "application/json" },
      })
    ).status(),
  ).toBe(401);
});
