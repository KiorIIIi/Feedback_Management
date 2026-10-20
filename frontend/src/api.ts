export type User = {
  id: number;
  name: string;
  email: string;
  role: "admin" | "requester" | "personnel";
  classification: string;
  office_id: number;
};
export type Event = {
  id: number;
  kind: string;
  message: string;
  visibility: string;
  created_at: string;
  actor?: string;
};
export type RequestRecord = {
  id: number;
  job_number: string;
  subject: string;
  description: string;
  location: string;
  status: string;
  priority: string | null;
  urgency: string;
  impact: string;
  record_version: number;
  requester_id: number;
  requesting_office_id: number | null;
  handling_office_id: number | null;
  service_type_id: number;
  created_at: string;
  completed_at: string | null;
  office: string;
  service_type: string;
  assignee: string | null;
  assignee_id: number | null;
  events?: Event[];
  evaluation?: { rating: number; remarks: string } | null;
};
export type Catalog = {
  offices: { id: number; name: string }[];
  service_types: { id: number; name: string }[];
  personnel: {
    id: number;
    name: string;
    office_id: number;
    active_assignments: number;
    service_type_ids: number[];
  }[];
  policy_mode: string;
};
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors?: Record<string, string[]>,
  ) {
    super(message);
  }
}
export const SESSION_EXPIRED_EVENT = "portal:session-expired";
let authGeneration = 0;
export async function api<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const requestGeneration = authGeneration;
  const res = await fetch("/api" + path, {
    ...options,
    credentials: "same-origin",
    cache: "no-store",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...options.headers,
    },
  });
  if (res.status === 401 && requestGeneration === authGeneration) {
    authGeneration += 1;
    window.dispatchEvent(new Event(SESSION_EXPIRED_EVENT));
  }
  if (res.ok && (path === "/login" || path === "/logout")) authGeneration += 1;
  let data;
  try {
    data = await res.json();
  } catch {
    throw new ApiError(
      "The server returned an unexpected response. Please retry.",
      res.status,
    );
  }
  if (!res.ok)
    throw new ApiError(
      data.message || "The request failed.",
      res.status,
      data.errors,
    );
  return data;
}
// Query-builder timestamps have no offset and are stored in the application's
// Asia/Manila timezone. Eloquent's ISO timestamps already include their offset.
export function parseDate(value: string) {
  const local = value.replace(" ", "T");
  if (/^\d{4}-\d{2}-\d{2}$/.test(local))
    return new Date(local + "T00:00:00+08:00");
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?$/.test(local))
    return new Date(local + "+08:00");
  return new Date(value);
}
export const date = (value: string) =>
  parseDate(value).toLocaleDateString("en-PH", {
    day: "numeric",
    month: "short",
    year: "numeric",
    timeZone: "Asia/Manila",
  });
