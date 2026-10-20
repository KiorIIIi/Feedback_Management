import { test, expect, type Page } from "@playwright/test";

const user = {
  id: 1,
  name: "Andrea Santos",
  email: "andrea@clsu.demo",
  role: "requester",
  classification: "Faculty",
  office_id: 1,
};
const catalog = {
  offices: [],
  service_types: [],
  personnel: [],
  policy_mode: "demo",
};
const dashboard = {
  counts: { total: 0, open: 0, completed: 0, awaiting_rating: 0 },
  recent: [],
  clarification: null,
  unrated: null,
  updates: [],
};

async function mockSession(page: Page) {
  await page.route("**/api/**", async (route) => {
    const path = new URL(route.request().url()).pathname;
    const body =
      path === "/api/me"
        ? user
        : path === "/api/catalog"
          ? catalog
          : path === "/api/requester-dashboard"
            ? dashboard
            : [];
    await route.fulfill({ json: body });
  });
}

test("an expired session during navigation returns to sign-in", async ({
  page,
}) => {
  await mockSession(page);
  await page.route("**/api/requests?**", (route) =>
    route.fulfill({ status: 401, json: { message: "Unauthenticated." } }),
  );
  await page.goto("/app/requester");
  await page.getByRole("link", { name: "My requests", exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  await expect(
    page.getByRole("button", { name: "Sign in", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Sign out", exact: true }),
  ).toHaveCount(0);
});

test("failed logout shows a retryable error and preserves the current workspace", async ({
  page,
  baseURL,
}) => {
  const unhandled: string[] = [];
  page.on("pageerror", (error) => unhandled.push(error.message));
  await mockSession(page);
  let tries = 0;
  await page.route("**/api/logout", (route) =>
    route.fulfill(
      ++tries === 1
        ? {
            status: 503,
            json: {
              message: "Sign-out is temporarily unavailable. Please retry.",
            },
          }
        : { json: { message: "Signed out." } },
    ),
  );
  await page.goto("/app/requester");
  page.on("dialog", (dialog) => dialog.accept());
  await page.getByRole("button", { name: "Sign out", exact: true }).click();
  await expect(page.getByRole("alert")).toContainText(
    "Sign-out is temporarily unavailable",
  );
  await expect(page).toHaveURL(/\/app\/requester$/);
  await expect(
    page.getByRole("button", { name: "Sign out", exact: true }),
  ).toBeEnabled();
  await page.getByRole("button", { name: "Sign out", exact: true }).click();
  await expect(page).toHaveURL(baseURL + "/");
  expect(tries).toBe(2);
  expect(unhandled).toEqual([]);
});

test("an invalid successful API response offers recovery instead of crashing", async ({
  page,
}) => {
  const unhandled: string[] = [];
  page.on("pageerror", (error) => unhandled.push(error.message));
  await mockSession(page);
  let invalid = true;
  await page.route("**/api/me", (route) =>
    invalid
      ? route.fulfill({
          status: 200,
          contentType: "text/html",
          body: "<html>Proxy error</html>",
        })
      : route.fulfill({ json: user }),
  );
  await page.goto("/app/requester");
  await expect(
    page.getByRole("heading", { name: "Unable to restore your session" }),
  ).toBeVisible();
  await expect(page.getByRole("alert")).toContainText("unexpected response");
  invalid = false;
  await page.getByRole("button", { name: "Retry connection" }).click();
  await expect(
    page.getByRole("heading", { name: "Good morning, Andrea." }),
  ).toBeVisible();
  expect(unhandled).toEqual([]);
});

test("notification timestamps stay in Manila time for an overseas browser", async ({
  browser,
  baseURL,
}) => {
  const context = await browser.newContext({
    timezoneId: "America/Los_Angeles",
    baseURL,
  });
  try {
    const page = await context.newPage();
    await mockSession(page);
    await page.route("**/api/requester-dashboard", (route) =>
      route.fulfill({
        json: {
          ...dashboard,
          updates: [
            {
              id: 1,
              service_request_id: 1,
              message: "Your service was updated",
              created_at: "2026-10-20 01:30:00",
              job_number: "MISO-DEMO-1",
            },
          ],
        },
      }),
    );
    await page.goto("/app/requester");
    await expect(page.locator("time")).toHaveText(/Oct 20, 2026, 1:30\s*AM/i);
  } finally {
    await context.close();
  }
});
