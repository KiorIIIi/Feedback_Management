import { expect, test, type Page, type Route } from "@playwright/test";

const requestRecord = {
  id: 101,
  job_number: "FMS-2026-UI-AUDIT",
  subject: "Audit equipment request",
  description:
    "Synthetic equipment concern used for frontend regression checks.",
  location: "Engineering Room 204",
  status: "Assigned",
  priority: "Medium",
  urgency: "Medium",
  impact: "One room",
  record_version: 1,
  requester_id: 2,
  requesting_office_id: 1,
  handling_office_id: 1,
  service_type_id: 1,
  created_at: "2026-10-20T02:00:00Z",
  completed_at: null,
  office: "Engineering",
  service_type: "Equipment maintenance",
  assignee: "Mark Demo",
  assignee_id: 3,
  evaluation: null,
  events: [],
};
const metrics = {
  total: 1,
  awaiting: 0,
  completed: 0,
  completion_rate: 0,
  rating_count: 0,
  satisfaction: null,
  priority: { Medium: 1 },
  daily: [],
  days: 30,
  definition: "Synthetic frontend audit metrics.",
};
const dashboard = {
  counts: { total: 1, open: 1, completed: 0, awaiting_rating: 0 },
  recent: [requestRecord],
  clarification: null,
  unrated: null,
  updates: [],
};
async function json(route: Route, body: unknown, status = 200) {
  await route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify(body),
  });
}
async function mockSession(
  page: Page,
  role: "admin" | "personnel" | "requester",
  handle?: (route: Route, url: URL) => Promise<boolean>,
) {
  await page.route("**/api/**", async (route) => {
    const url = new URL(route.request().url());
    if (handle && (await handle(route, url))) return;
    if (url.pathname === "/api/me") {
      await json(route, {
        id: role === "requester" ? 2 : role === "personnel" ? 3 : 1,
        name: role === "requester" ? "Andrea Demo" : "Mark Demo",
        email: `${role}@clsu.demo`,
        role,
        classification: "Faculty",
        office_id: 1,
      });
    } else if (url.pathname === "/api/catalog") {
      await json(route, {
        offices: [{ id: 1, name: "Engineering" }],
        service_types: [{ id: 1, name: "Equipment maintenance" }],
        personnel: [
          {
            id: 3,
            name: "Mark Demo",
            office_id: 1,
            active_assignments: 1,
            service_type_ids: [1],
          },
        ],
        policy_mode: "demo",
      });
    } else if (url.pathname === "/api/notifications") await json(route, []);
    else if (url.pathname === "/api/requester-dashboard")
      await json(route, dashboard);
    else if (url.pathname === "/api/metrics") await json(route, metrics);
    else if (url.pathname === "/api/requests") {
      await json(route, {
        data: [requestRecord],
        total: 1,
        current_page: 1,
        last_page: 1,
      });
    } else if (url.pathname === "/api/requests/101")
      await json(route, requestRecord);
    else
      await json(
        route,
        { message: "Unexpected audit API path: " + url.pathname },
        404,
      );
  });
}

test("failed request filters hide unrelated rows and permit a refresh", async ({
  page,
}) => {
  let failed = true;
  await mockSession(page, "requester", async (route, url) => {
    if (url.pathname !== "/api/requests" || !url.searchParams.has("priority"))
      return false;
    if (failed)
      await json(
        route,
        { message: "Priority filter temporarily unavailable." },
        503,
      );
    else
      await json(route, { data: [], total: 0, current_page: 1, last_page: 1 });
    return true;
  });
  await page.goto("/app/requests");
  await expect(
    page.getByRole("link", { name: requestRecord.subject, exact: true }),
  ).toBeVisible();
  await page.getByLabel("Filter priority").selectOption("Critical");
  await expect(page.getByRole("alert")).toContainText(
    "Priority filter temporarily unavailable.",
  );
  await expect(
    page.getByRole("link", { name: requestRecord.subject, exact: true }),
  ).toHaveCount(0);
  await expect(
    page.getByRole("heading", { name: "No matching requests" }),
  ).toHaveCount(0);
  failed = false;
  await page.getByRole("button", { name: "Refresh", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "No matching requests" }),
  ).toBeVisible();
  await expect(page.getByRole("alert")).toHaveCount(0);
});

test("notification read failures are shown, retry succeeds and unread count updates", async ({
  page,
}) => {
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  let reads = 0;
  let read = false;
  await mockSession(page, "requester", async (route, url) => {
    if (url.pathname === "/api/notifications") {
      await json(route, [
        {
          id: 1,
          service_request_id: 101,
          message: "Your equipment request was assigned.",
          read_at: read ? "2026-10-20T03:00:00Z" : null,
          created_at: "2026-10-20T02:00:00Z",
        },
      ]);
      return true;
    }
    if (url.pathname !== "/api/notifications/1/read") return false;
    reads += 1;
    if (reads === 1)
      await json(
        route,
        { message: "Unable to mark this notification. Please retry." },
        503,
      );
    else {
      read = true;
      await json(route, { message: "Marked as read." });
    }
    return true;
  });
  await page.goto("/app/notifications");
  await expect(page.locator(".requester-notification-count")).toHaveText("1");
  await page.getByRole("button", { name: "Mark read", exact: true }).click();
  await expect(page.getByRole("alert")).toContainText(
    "Unable to mark this notification.",
  );
  await expect(page.locator(".notification-row.unread")).toHaveCount(1);
  await page.getByRole("button", { name: "Mark read", exact: true }).click();
  await expect(page.locator(".notification-row.unread")).toHaveCount(0);
  await expect(page.locator(".requester-notification-count")).toHaveCount(0);
  await expect(page.getByRole("alert")).toHaveCount(0);
  expect(errors).toEqual([]);
  expect(reads).toBe(2);
});

test("a committed request update stays successful if workspace refresh fails", async ({
  page,
}) => {
  let record = { ...requestRecord };
  let saves = 0;
  await mockSession(page, "admin", async (route, url) => {
    if (url.pathname === "/api/catalog" && saves > 0) {
      await json(route, { message: "Catalog temporarily unavailable." }, 503);
      return true;
    }
    if (url.pathname !== "/api/requests/101") return false;
    if (route.request().method() === "PATCH") {
      const body = route.request().postDataJSON();
      expect(body.expected_version).toBe(1);
      saves += 1;
      record = { ...record, status: body.status, record_version: 2 };
    }
    await json(route, record);
    return true;
  });
  await page.goto("/app/requests/101");
  await page.getByLabel("Status", { exact: true }).selectOption("In Progress");
  await page
    .getByLabel("Progress note / actions taken")
    .fill("Inspecting the reported equipment.");
  await page.getByRole("button", { name: "Save update", exact: true }).click();
  await expect(page.getByRole("status")).toContainText(
    "The request was updated.",
  );
  await expect(page.getByRole("alert")).toContainText(
    "Your update was saved, but workspace information could not be refreshed.",
  );
  await expect(page.getByText("Version 2.", { exact: false })).toBeVisible();
  await expect(page.getByLabel("Status", { exact: true })).toHaveValue(
    "In Progress",
  );
  expect(saves).toBe(1);
});

test("a saved request stays confirmed when its detail refresh fails", async ({
  page,
}) => {
  let saves = 0;
  let failed = true;
  await mockSession(page, "admin", async (route, url) => {
    if (url.pathname !== "/api/requests/101") return false;
    if (route.request().method() === "PATCH") saves += 1;
    else if (saves && failed) {
      await json(
        route,
        { message: "Request detail temporarily unavailable." },
        503,
      );
      return true;
    }
    await json(route, { ...requestRecord, record_version: saves ? 2 : 1 });
    return true;
  });
  await page.goto("/app/requests/101");
  await page
    .getByLabel("Progress note / actions taken")
    .fill("Inspecting the equipment.");
  await page.getByRole("button", { name: "Save update", exact: true }).click();
  await expect(page.getByRole("status")).toContainText(
    "The request was updated.",
  );
  await expect(page.getByRole("alert")).toContainText(
    "Request detail temporarily unavailable.",
  );
  failed = false;
  await page.getByRole("button", { name: "Retry loading request" }).click();
  await expect(page.getByText("Version 2.", { exact: false })).toBeVisible();
  expect(saves).toBe(1);
});

test("report polling survives period changes and clears transient failures", async ({
  page,
}) => {
  let polls = 0;
  let activePolls = 0;
  let maxActivePolls = 0;
  await mockSession(page, "admin", async (route, url) => {
    if (
      url.pathname === "/api/reports" &&
      route.request().method() === "POST"
    ) {
      expect(route.request().postDataJSON()).toEqual({ days: 30, format: "csv" });
      await json(route, { id: 12, status: "queued" }, 202);
      return true;
    }
    if (url.pathname !== "/api/reports/12") return false;
    polls += 1;
    activePolls += 1;
    maxActivePolls = Math.max(maxActivePolls, activePolls);
    if (polls === 1) {
      await new Promise((resolve) => setTimeout(resolve, 1800));
      await json(
        route,
        { message: "Temporary report connection failure." },
        503,
      );
    } else
      await json(route, {
        id: 12,
        status: "completed",
        download_url: "/api/reports/12/download",
      });
    activePolls -= 1;
    return true;
  });
  await page.goto("/app/admin");
  await page
    .getByRole("button", { name: "Export report", exact: true })
    .click();
  await expect(
    page.getByText("Your report is queued.", { exact: false }),
  ).toBeVisible();
  await page.getByLabel("Reporting period").selectOption("7");
  await expect(
    page.getByRole("button", { name: "Export report", exact: true }),
  ).toBeDisabled();
  await expect(page.getByRole("alert")).toContainText(
    "Temporary report connection failure.",
    { timeout: 10000 },
  );
  await expect(
    page.getByRole("link", { name: "Download report CSV" }),
  ).toBeVisible({ timeout: 10000 });
  await expect(page.getByRole("alert")).toHaveCount(0);
  expect(maxActivePolls).toBe(1);
});

test("requester dashboard can retry a temporary loading failure", async ({
  page,
}) => {
  let fail = true;
  await mockSession(page, "requester", async (route, url) => {
    if (url.pathname !== "/api/requester-dashboard" || !fail) return false;
    await json(route, { message: "Dashboard temporarily unavailable." }, 503);
    return true;
  });
  await page.goto("/app/requester");
  await expect(page.getByRole("alert")).toContainText(
    "Dashboard temporarily unavailable.",
  );
  fail = false;
  await page
    .getByRole("button", { name: "Retry dashboard", exact: true })
    .click();
  await expect(
    page.getByRole("heading", { name: "Good morning, Andrea." }),
  ).toBeVisible();
  await expect(page.getByRole("alert")).toHaveCount(0);
});

for (const role of ["admin", "personnel"] as const) {
  test(`${role} cannot open requester-only rating or feedback forms directly`, async ({
    page,
  }) => {
    await mockSession(page, role);
    await page.goto("/app/requests/101/rate");
    await expect(page).toHaveURL(
      role === "admin" ? /\/app\/admin$/ : /\/app\/work$/,
    );
    await expect(
      page.getByRole("heading", { name: "How was your service experience?" }),
    ).toHaveCount(0);
    if (role === "personnel") {
      await page.goto("/app/feedback");
      await expect(page).toHaveURL(/\/app\/work$/);
      await expect(
        page.getByRole("heading", { name: "Feedback history", exact: true }),
      ).toHaveCount(0);
    }
  });
}
