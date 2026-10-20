import { test, expect } from "@playwright/test";

test("an old pending unauthorized response cannot clear a new login", async ({
  page,
}) => {
  const account = {
    id: 101,
    name: "Andrea Santos",
    email: "andrea@clsu.demo",
    role: "requester",
    classification: "Faculty",
    office_id: 1,
  };
  let authenticated = true;
  let pendingStarted = false;
  let releaseOldResponse: () => void = () => {};
  const oldResponse = new Promise<void>((resolve) => {
    releaseOldResponse = resolve;
  });
  await page.route("**/api/**", async (route) => {
    const path = new URL(route.request().url()).pathname;
    const json = (body: unknown, status = 200) =>
      route.fulfill({ status, json: body });
    if (path === "/api/notifications/999/read") {
      pendingStarted = true;
      await oldResponse;
      return json({ message: "Unauthenticated." }, 401);
    }
    if (path === "/api/notifications/998/read") {
      authenticated = false;
      return json({ message: "Unauthenticated." }, 401);
    }
    if (path === "/api/login") {
      authenticated = true;
      return json(account);
    }
    if (path === "/api/me")
      return json(
        authenticated ? account : { message: "Unauthenticated." },
        authenticated ? 200 : 401,
      );
    if (path === "/api/catalog")
      return json({
        offices: [],
        service_types: [],
        personnel: [],
        policy_mode: "demo",
      });
    if (path === "/api/notifications") return json([]);
    if (path === "/api/requester-dashboard")
      return json({
        counts: { total: 0, open: 0, completed: 0, awaiting_rating: 0 },
        recent: [],
        clarification: null,
        unrated: null,
        updates: [],
      });
    return json({ message: "Unexpected mock request" }, 404);
  });
  await page.goto("/app/requester");
  await expect(
    page.getByRole("heading", { name: "Good morning, Andrea." }),
  ).toBeVisible();
  await page.evaluate(async () => {
    // Model a pending write; writes cannot be canceled by route/component unmounts.
    const { api } = await import("/src/api.ts");
    void api("/notifications/999/read", { method: "POST", body: "{}" }).catch(
      () => {},
    );
  });
  await expect.poll(() => pendingStarted).toBe(true);
  await page.evaluate(async () => {
    const { api } = await import("/src/api.ts");
    await api("/notifications/998/read", { method: "POST", body: "{}" }).catch(
      () => {},
    );
  });
  await expect(page).toHaveURL(/\/login$/);
  await page.getByLabel("Email address", { exact: true }).fill(account.email);
  await page.getByLabel("Password", { exact: true }).fill("DemoPass!2026");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).toHaveURL(/\/app\/requester$/);
  await expect(
    page.getByRole("heading", { name: "Good morning, Andrea." }),
  ).toBeVisible();
  const responseFinished = page.waitForResponse((response) =>
    response.url().endsWith("/api/notifications/999/read"),
  );
  releaseOldResponse();
  await responseFinished;
  // Let the response's dispatch/render finish before checking the logged-in UI.
  await page.evaluate(
    () =>
      new Promise((resolve) =>
        requestAnimationFrame(() => requestAnimationFrame(resolve)),
      ),
  );
  await expect(page).toHaveURL(/\/app\/requester$/);
  await expect(
    page.getByRole("heading", { name: "Good morning, Andrea." }),
  ).toBeVisible();
});
