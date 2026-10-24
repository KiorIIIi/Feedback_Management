import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import {
  Link,
  Navigate,
  useNavigate,
  useParams,
  useSearchParams,
} from "react-router-dom";
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { api, ApiError, date, type RequestRecord, type Catalog } from "./api";
import { useAuth, useNotifications } from "./App";
import { ReportControls, ReportWorkspace } from "./reports";
import {
  Asset,
  Badge,
  Empty,
  ErrorNotice,
  Field,
  Help,
  Icon,
  Loading,
  Panel,
} from "./components";

import { useLoad } from "./useLoad";

function PageTitle({
  title,
  description,
  actions,
  back,
}: {
  title: string;
  description?: string;
  actions?: ReactNode;
  back?: string;
}) {
  const { user } = useAuth();
  return (
    <div className="page-title">
      <div>
        {user?.role === "admin" ? (
          <p className="eyebrow">CENTRAL LUZON STATE UNIVERSITY · MISO</p>
        ) : (
          <p className="page-path">My workspace / {title}</p>
        )}
        <h1>{title}</h1>
        {description && <p>{description}</p>}
        {back && (
          <Link className="back" to={back}>
            ← Back to{" "}
            {user?.role === "admin" ? "service requests" : "my requests"}
          </Link>
        )}
      </div>
      {actions}
    </div>
  );
}
type Metrics = {
  total: number;
  awaiting: number;
  completed: number;
  completion_rate: number;
  rating_count: number;
  satisfaction: number | null;
  priority: Record<string, number>;
  daily: { date: string; submitted: number; completed: number }[];
  days: number;
  definition: string;
};
function Stats({
  metrics,
  requester = false,
}: {
  metrics: Metrics;
  requester?: boolean;
}) {
  const items = requester
    ? [
        ["My requests", metrics.total, "All your submissions", "requests"],
        [
          "Open requests",
          metrics.total - metrics.completed,
          "Across active statuses",
          "calendar",
        ],
        ["Completed", metrics.completed, "Services delivered", "evaluation"],
        [
          "Awaiting action",
          metrics.awaiting,
          "Review your latest updates",
          "feedback",
        ],
      ]
    : [
        ["Total requests", metrics.total, "Submitted in this period", "total"],
        [
          "Awaiting action",
          metrics.awaiting,
          "Pending, clarification or on hold",
          "waiting",
        ],
        [
          "Completed requests",
          metrics.completed,
          `${metrics.completion_rate}% completion rate`,
          "completed",
        ],
        [
          "Client satisfaction",
          metrics.satisfaction ?? "—",
          `${metrics.rating_count} verified ratings · out of 5`,
          "satisfaction",
        ],
      ];
  return (
    <div className="stats-grid">
      {items.map(([label, value, sub, icon]) => (
        <div className="stat" key={label}>
          <div>
            <span>{label}</span>
            <span className="stat-icon">
              <Icon name={String(icon)} />
            </span>
          </div>
          <strong>{value}</strong>
          <small>{sub}</small>
        </div>
      ))}
    </div>
  );
}
function Charts({ metrics }: { metrics: Metrics }) {
  return (
    <div className="charts-grid">
      <Panel
        title="Requests over time"
        subtitle="Submitted requests and current completions by submission date"
      >
        <div className="chart-legend">
          <span>● Submitted</span>
          <span>● Completed</span>
        </div>
        <div className="chart">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart
              data={metrics.daily}
              margin={{ top: 10, right: 20, left: -10, bottom: 10 }}
            >
              <defs>
                <linearGradient id="submittedFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#008447" stopOpacity={0.12} />
                  <stop offset="100%" stopColor="#008447" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid
                strokeDasharray="2 3"
                vertical={false}
                stroke="#e6ece8"
              />
              <XAxis
                dataKey="date"
                tickFormatter={(d) => date(d).split(" ").slice(0, 2).join(" ")}
                tickLine={false}
                axisLine={false}
                tick={{ fontSize: 10, fill: "#617169" }}
              />
              <YAxis
                allowDecimals={false}
                tickLine={false}
                axisLine={false}
                tick={{ fontSize: 10, fill: "#617169" }}
              />
              <Tooltip />
              <Area
                type="monotone"
                dataKey="submitted"
                stroke="#00a653"
                fill="url(#submittedFill)"
                strokeWidth={2}
              />
              <Area
                type="monotone"
                dataKey="completed"
                stroke="#e4aa0a"
                fill="transparent"
                strokeDasharray="4 4"
                strokeWidth={2}
              />
            </AreaChart>
          </ResponsiveContainer>
        </div>
        <div className="panel-foot">
          Calendar days · Asia/Manila<span>Last {metrics.days} days</span>
        </div>
      </Panel>
      <Panel
        title="Requests by priority"
        subtitle="Classification by urgency and service impact"
      >
        <div className="priority-chart">
          <div className="priority-total">
            <strong>{metrics.total}</strong>
            <small>Total requests</small>
          </div>
          <div>
            {Object.entries(metrics.priority).map(([k, v]) => (
              <div className="priority-row" key={k}>
                <Badge value={k} />
                <strong>{v}</strong>
                <small>
                  {metrics.total ? Math.round((v / metrics.total) * 100) : 0}%
                </small>
              </div>
            ))}
          </div>
        </div>
        <div className="panel-foot">
          Priority is based on urgency & impact
          <Icon name="arrow" />
        </div>
      </Panel>
    </div>
  );
}
export function Dashboard() {
  const { user } = useAuth();
  const admin = user?.role === "admin";
  const [days, setDays] = useState(30);
  const metrics = useLoad<Metrics>("/metrics?days=" + days, { live: true }),
    requests = useLoad<{ data: RequestRecord[]; total: number }>("/requests", { live: true });
  const rows = requests.data?.data || [];
  const needsClarification = rows.find((r) => r.status === "For Clarification");
  const unrated = rows.find((r) => r.status === "Completed" && !r.evaluation);
  return (
    <>
      <PageTitle
        title={
          admin ? "Dashboard" : `Good morning, ${user?.name.split(" ")[0]}.`
        }
        description={
          admin
            ? "Monitor service requests, personnel assignments, and client feedback."
            : "Here’s where your service requests stand. We’ll keep you in the loop."
        }
        actions={
          <div className="actions">
            {!admin && user?.role !== "personnel" && (
              <Link className="button" to="/app/feedback/new">
                <Icon name="feedback" />
                Share feedback
              </Link>
            )}
            {user?.role !== "personnel" && (
              <Link className="button primary" to="/app/requests/new">
                <Icon name="plus" />
                New request
              </Link>
            )}
          </div>
        }
      />
      {admin && rows[0] && (
        <Link className="recent-highlight" to={"/app/requests/" + rows[0].id}>
          <Icon name="requests" />
          <div>
            <small>MOST RECENT REQUEST</small>
            <strong>{rows[0].subject}</strong>
            <span>
              {rows[0].job_number} · {rows[0].office} ·{" "}
              {date(rows[0].created_at)}
            </span>
          </div>
          <Badge value={rows[0].priority} />
          <Badge value={rows[0].status} />
          <span>
            View details <Icon name="arrow" />
          </span>
        </Link>
      )}
      {admin && <ReportControls days={days} setDays={setDays} />}
      {metrics.loading || requests.loading ? (
        <Loading />
      ) : metrics.error || requests.error ? (
        <ErrorNotice error={metrics.error || requests.error} />
      ) : metrics.data ? (
        <>
          <Stats metrics={metrics.data} requester={!admin} />
          {admin ? (
            <>
              <Charts metrics={metrics.data} />
              <RequestTable compact />
            </>
          ) : (
            <>
              {needsClarification && (
                <div className="clarification-banner">
                  <div>
                    <strong>
                      A few more details will help us move forward
                    </strong>
                    <p>
                      {needsClarification.job_number} · MISO needs additional
                      information.
                    </p>
                  </div>
                  <Link
                    className="button"
                    to={"/app/requests/" + needsClarification.id}
                  >
                    Provide clarification
                    <Icon name="arrow" />
                  </Link>
                </div>
              )}
              <div className="requester-dashboard-grid">
                <Panel
                  title="Recent requests"
                  action={
                    <Link to="/app/requests">
                      View all requests <Icon name="arrow" />
                    </Link>
                  }
                >
                  <SimpleRequests rows={rows.slice(0, 5)} />
                </Panel>
                <aside>
                  {unrated && (
                    <div className="rating-prompt">
                      <small>YOUR FEEDBACK MATTERS</small>
                      <h2>How was your service?</h2>
                      <p>
                        Your {unrated.subject.toLowerCase()} is complete. Tell
                        us how we did.
                      </p>
                      <small>
                        {unrated.job_number} · Completed{" "}
                        {date(unrated.completed_at!)}
                      </small>
                      <Link
                        className="button"
                        to={`/app/requests/${unrated.id}/rate`}
                      >
                        Rate completed service <Icon name="arrow" />
                      </Link>
                    </div>
                  )}
                  <Panel title="Latest updates">
                    <div className="panel-body">
                      {rows.slice(0, 3).map((r) => (
                        <Link
                          className="latest-update"
                          key={r.id}
                          to={"/app/requests/" + r.id}
                        >
                          <Icon name="requests" />
                          <span>
                            {r.status}
                            <small>{r.job_number}</small>
                            <small>{date(r.created_at)}</small>
                          </span>
                        </Link>
                      ))}
                      <Link to="/app/notifications">
                        All notifications <Icon name="arrow" />
                      </Link>
                    </div>
                  </Panel>
                </aside>
              </div>
              <p className="privacy-caption">
                This overview includes only records you are authorized to view.
              </p>
              <Help />
            </>
          )}
        </>
      ) : null}
    </>
  );
}
export function PersonnelDashboard() {
  const { user } = useAuth();
  const { data, error, loading } = useLoad<Metrics>("/metrics?days=30", { live: true });
  if (loading) return <Loading />;
  if (error) return <ErrorNotice error={error} />;
  return (
    <>
      <PageTitle
        title="My assigned work"
        description={`Welcome, ${user?.name.split(" ")[0]}. Review your assignments and record service progress.`}
      />
      {data && (
        <div className="stats-grid">
          {[
            [
              "Assigned to me",
              data.total,
              "Assigned records submitted in the last 30 days",
            ],
            [
              "Active work",
              data.total - data.completed,
              "Assigned services not yet completed",
            ],
            ["Completed", data.completed, "Services with a recorded outcome"],
            [
              "Needs attention",
              data.awaiting,
              "Pending clarification or on hold",
            ],
          ].map(([label, value, caption]) => (
            <div className="stat" key={label}>
              <div>{label}</div>
              <strong>{value}</strong>
              <small>{caption}</small>
            </div>
          ))}
        </div>
      )}
      <RequestTable compact />
      <div className="notice">
        <strong>Your personnel workspace</strong>
        <p>
          Open an assigned request to record progress, pause work with a reason,
          or document completion. Request review, priority and assignment remain
          with MISO.
        </p>
      </div>
    </>
  );
}
function SimpleRequests({ rows }: { rows: RequestRecord[] }) {
  if (!rows.length) return <Empty />;
  return (
    <div className="table-scroll">
      <table className="simple-table">
        <thead>
          <tr>
            <th>REQUEST / SERVICE</th>
            <th>STATUS</th>
            <th>SUBMITTED</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.id}>
              <td>
                <small className="job">{r.job_number}</small>
                <Link to={"/app/requests/" + r.id}>
                  <strong>{r.subject}</strong>
                </Link>
                <small>{r.service_type}</small>
              </td>
              <td>
                <Badge value={r.status} />
                <Link className="table-action" to={"/app/requests/" + r.id}>
                  {r.status === "For Clarification"
                    ? "Clarify request"
                    : "View request"}{" "}
                  →
                </Link>
              </td>
              <td>{date(r.created_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
export function RequestList() {
  const { user } = useAuth();
  return (
    <>
      <PageTitle
        title={
          user?.role === "admin"
            ? "Service requests"
            : user?.role === "personnel"
              ? "Assigned requests"
              : "My requests and history"
        }
        description="Review submissions, assignments, and resolution status."
        actions={
          user?.role !== "personnel" && (
            <Link className="button primary" to="/app/requests/new">
              <Icon name="plus" />
              New request
            </Link>
          )
        }
      />
      <RequestTable />
      {user?.role !== "admin" && <Help />}
    </>
  );
}
function RequestTable({ compact = false }: { compact?: boolean }) {
  const { user } = useAuth();
  const [params, setParams] = useSearchParams();
  const [search, setSearch] = useState(""),
    [status, setStatus] = useState(""),
    [priority, setPriority] = useState(""),
    [page, setPage] = useState(1),
    [query, setQuery] = useState("");
  useEffect(() => {
    const t = setTimeout(() => {
      setQuery(search);
      setPage(1);
    }, 250);
    return () => clearTimeout(t);
  }, [search]);
  const qs = new URLSearchParams({
    page: String(page),
    search: query,
    ...(status ? { status } : {}),
    ...(priority ? { priority } : {}),
    ...(params.get("personnel_id")
      ? { personnel_id: params.get("personnel_id")! }
      : {}),
  });
  const { data, error, loading, reload } = useLoad<{
    data: RequestRecord[];
    total: number;
    last_page: number;
  }>("/requests?" + qs.toString(), { live: true });
  return (
    <Panel
      title={
        user?.role === "personnel"
          ? "Assigned service requests"
          : compact
            ? "Recent service requests"
            : "All service requests"
      }
      subtitle="Review submissions, assignments, and resolution status."
      action={
        compact ? (
          <Link to="/app/requests">
            View all requests <Icon name="arrow" />
          </Link>
        ) : undefined
      }
    >
      {params.has("personnel_id") && (
        <div className="notice">
          Showing selected personnel assignments.{" "}
          <button className="text-button" onClick={() => setParams({})}>
            Clear filter
          </button>
        </div>
      )}
      <div className="table-toolbar">
        <div className="tabs">
          {["", "Pending", "In Progress", "Completed"].map((s) => (
            <button
              key={s}
              className={status === s ? "active" : ""}
              onClick={() => {
                setStatus(s);
                setPage(1);
              }}
            >
              {s || "All requests"}
              {!s && <small>{data?.total ?? 0}</small>}
            </button>
          ))}
        </div>
        <div className="filters">
          <label className="search">
            <Icon name="search" />
            <input
              aria-label="Search requests"
              placeholder="Search requests…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </label>
          <label className="priority-filter">
            <Icon name="filter" />
            <select
              aria-label="Filter priority"
              value={priority}
              onChange={(e) => {
                setPriority(e.target.value);
                setPage(1);
              }}
            >
              <option value="">All priorities</option>
              {["Critical", "High", "Medium", "Low"].map((p) => (
                <option key={p}>{p}</option>
              ))}
            </select>
          </label>
        </div>
      </div>
      <ErrorNotice error={error} />
      {loading ? (
        <Loading />
      ) : error ? null : data?.data.length ? (
        <div className="table-scroll">
          <table className="requests-table">
            <thead>
              <tr>
                <th>CONTROL NO.</th>
                <th>REQUEST DETAILS</th>
                <th>PRIORITY</th>
                <th>STATUS</th>
                {user?.role === "admin" && <th>ASSIGNED TO</th>}
                <th>DATE SUBMITTED</th>
                <th>
                  <span className="sr-only">Details</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((r) => (
                <tr key={r.id}>
                  <td className="control-number">
                    <Link to={"/app/requests/" + r.id}>{r.job_number}</Link>
                  </td>
                  <td>
                    <Link
                      className="request-subject"
                      to={"/app/requests/" + r.id}
                    >
                      {r.subject}
                    </Link>
                    <small>
                      {r.office} · {r.service_type}
                    </small>
                  </td>
                  <td>
                    <Badge value={r.priority} />
                  </td>
                  <td>
                    <Badge value={r.status} />
                  </td>
                  {user?.role === "admin" && (
                    <td>
                      {r.assignee ? (
                        <span className="assignee">
                          <span className="tiny-avatar">
                            {r.assignee
                              .split(" ")
                              .slice(0, 2)
                              .map((n) => n[0])
                              .join("")}
                          </span>
                          {r.assignee}
                        </span>
                      ) : (
                        <em>Unassigned</em>
                      )}
                    </td>
                  )}
                  <td>{date(r.created_at)}</td>
                  <td>
                    <Link
                      to={"/app/requests/" + r.id}
                      aria-label={"View " + r.subject}
                    >
                      <Icon name="arrow" />
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <Empty
          title={
            search || status || priority
              ? "No matching requests"
              : "No requests yet"
          }
        >
          {search || status || priority
            ? "Try another search or clear the filters."
            : "Submit a service request to start tracking its progress."}
        </Empty>
      )}
      <div className="panel-foot">
        <span>
          Showing {data?.data.length ?? 0} of {data?.total ?? 0} requests
        </span>
        <div className="pagination">
          {(data?.last_page ?? 1) > 1 && (
            <>
              <button disabled={page === 1} onClick={() => setPage(page - 1)}>
                Previous
              </button>
              <span>Page {page}</span>
              <button
                disabled={page === data?.last_page}
                onClick={() => setPage(page + 1)}
              >
                Next
              </button>
            </>
          )}
          <button className="text-button" onClick={reload}>
            Refresh
          </button>
        </div>
      </div>
    </Panel>
  );
}
export function NewRequest() {
  const { user, catalog } = useAuth();
  const nav = useNavigate();
  const [error, setError] = useState<unknown>(),
    [busy, setBusy] = useState(false);
  if (user?.role === "personnel")
    return <Navigate to="/app/requests" replace />;
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    const values = Object.fromEntries(new FormData(e.currentTarget));
    try {
      const r = await api<RequestRecord>("/requests", {
        method: "POST",
        body: JSON.stringify({
          ...values,
          service_type_id: Number(values.service_type_id),
          requesting_office_id: values.requesting_office_id
            ? Number(values.requesting_office_id)
            : null,
        }),
      });
      nav("/app/requests/" + r.id, { state: { submitted: true } });
    } catch (e) {
      setError(e);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <PageTitle
        title={
          user?.role === "admin" ? "New service request" : "How can we help?"
        }
        description={
          user?.role === "admin"
            ? "Log a concern or request assistance for a university office."
            : "Tell us what you need. Your submission goes to MISO for review and routing."
        }
        back={user?.role === "admin" ? "/app/requests" : undefined}
      />
      <div className="form-layout">
        <div>
          {user?.role !== "admin" && (
            <div className="submission-tabs">
              <span className="active">
                <Icon name="requests" />
                Service request
              </span>
              <Link to="/app/feedback/new">
                <Icon name="feedback" />
                Feedback
              </Link>
            </div>
          )}
          <p className="form-intro">
            Request assistance with a university service. Fields marked * are
            required.
          </p>
          <form onSubmit={submit}>
            <ErrorNotice error={error} />
            <Panel
              title={
                user?.role === "admin"
                  ? "Requesting office & contact"
                  : "Your information"
              }
              subtitle="We use your account details to identify and follow up on this request."
            >
              <div className="panel-body form-grid">
                <Field label="Requester">
                  <input value={user?.name} disabled />
                </Field>
                <Field label="Email address">
                  <input value={user?.email} disabled />
                </Field>
                <Field label="Requesting office / affiliation">
                  <select
                    name="requesting_office_id"
                    defaultValue={user?.office_id || ""}
                  >
                    <option value="">No office affiliation</option>
                    {catalog?.offices.map((o) => (
                      <option key={o.id} value={o.id}>
                        {o.name}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Role">
                  <input value={user?.classification} disabled />
                </Field>
              </div>
            </Panel>
            <Panel
              title="Service details"
              subtitle="Be specific so the right service team can understand your concern."
            >
              <div className="panel-body">
                <Field label="Service type *">
                  <select name="service_type_id" required defaultValue="">
                    <option value="" disabled>
                      Select a service type
                    </option>
                    {catalog?.service_types.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Short subject *">
                  <input
                    name="subject"
                    maxLength={200}
                    placeholder="e.g. Ceiling fan not working in Room 204"
                    required
                  />
                </Field>
                <Field
                  label="Location *"
                  hint="Include the building, floor, and room or a clear campus location."
                >
                  <input
                    name="location"
                    maxLength={255}
                    placeholder="Building, floor, room"
                    required
                  />
                </Field>
                <Field
                  label="Detailed concern *"
                  hint="Describe what happened, when it started, and any steps already taken."
                >
                  <textarea
                    name="description"
                    minLength={15}
                    maxLength={10000}
                    placeholder="Describe your concern…"
                    required
                    rows={5}
                  />
                </Field>
              </div>
            </Panel>
            <Panel
              title="Urgency & impact"
              subtitle="Describe the situation. You are not setting the official priority."
            >
              <div className="panel-body">
                <fieldset>
                  <legend>How urgent is the concern? *</legend>
                  <div className="radio-options">
                    {["Low", "Medium", "High"].map((v) => (
                      <label key={v}>
                        <input
                          type="radio"
                          name="urgency"
                          value={v}
                          defaultChecked={v === "Medium"}
                          required
                        />
                        {v}
                      </label>
                    ))}
                  </div>
                </fieldset>
                <Field label="Who or what is affected? *">
                  <select name="impact" defaultValue="One room">
                    {[
                      "Individual",
                      "One room",
                      "Entire office",
                      "Campus-wide",
                    ].map((v) => (
                      <option key={v}>{v}</option>
                    ))}
                  </select>
                </Field>
                <div className="notice">
                  <strong>MISO determines the official priority</strong>
                  <p>
                    MISO reviews validity, confirms the service category, and
                    sets priority using urgency and impact. Your input helps
                    with that review.
                  </p>
                </div>
              </div>
            </Panel>
            <p className="privacy-caption">
              Share only information needed to address your concern. Do not
              include passwords or unnecessary sensitive information.
            </p>
            <div className="form-actions">
              <div>
                <strong>You’ll get a job number after submission.</strong>
                <small>Use it to track updates in My requests.</small>
              </div>
              <Link className="button" to="/app/requests">
                Cancel
              </Link>
              <button className="button primary" disabled={busy}>
                {busy ? "Submitting…" : "Submit request"}
                <Icon name="arrow" />
              </button>
            </div>
          </form>
        </div>
        <aside>
          <Panel title="What happens next?">
            <ol className="steps">
              <li>
                <strong>Received & recorded</strong>
                <p>
                  A unique job number keeps your request in one service record.
                </p>
              </li>
              <li>
                <strong>Reviewed by MISO</strong>
                <p>
                  We confirm the details and route the request to the right
                  team.
                </p>
              </li>
              <li>
                <strong>Follow the progress</strong>
                <p>
                  See status updates, actions, and any request for
                  clarification.
                </p>
              </li>
            </ol>
          </Panel>
          <Panel title="University service types">
            <ul className="service-type-list">
              {catalog?.service_types.map((s) => (
                <li key={s.id}>{s.name}</li>
              ))}
            </ul>
          </Panel>
          <div className="notice gold">
            <h3>Want to share feedback?</h3>
            <p>
              Choose Feedback for a complaint, suggestion, compliment, or other
              feedback.
            </p>
            <p>
              You can optionally include a related job number. Feedback stays
              private.
            </p>
          </div>
        </aside>
      </div>
      <Help />
    </>
  );
}
export function RequestDetail() {
  const { id } = useParams();
  const { user, catalog, refresh } = useAuth();
  const [editing, setEditing] = useState(false);
  const [saveError, setSaveError] = useState<unknown>(),
    [busy, setBusy] = useState(false),
    [success, setSuccess] = useState("");
  const {
    data: r,
    error,
    loading,
    reload,
  } = useLoad<RequestRecord>("/requests/" + id, { live: true, paused: editing || busy });
  useEffect(() => {
    setSaveError(null);
    setSuccess("");
    setEditing(false);
  }, [id]);
  if (loading) return <Loading />;
  if (error)
    return (
      <>
        {success && (
          <div className="notice success" role="status">
            {success}
          </div>
        )}
        <ErrorNotice error={error} />
        <button className="button" onClick={reload}>
          Retry loading request
        </button>
      </>
    );
  if (!r) return null;
  const requester = user?.role === "requester";
  async function save(e: FormEvent<HTMLFormElement>, action: string) {
    e.preventDefault();
    setSaveError(null);
    setSuccess("");
    setBusy(true);
    const f = Object.fromEntries(new FormData(e.currentTarget));
    try {
      await api("/requests/" + id, {
        method: "PATCH",
        body: JSON.stringify({
          ...f,
          action,
          expected_version: r!.record_version,
          ...(f.personnel_id ? { personnel_id: Number(f.personnel_id) } : {}),
          ...("category_id" in f ? { category_id: f.category_id ? Number(f.category_id) : null } : {}),
        }),
      });
      setSuccess(
        action === "reply"
          ? "Your clarification has been sent for review."
          : "The request was updated.",
      );
      setEditing(false);
      reload();
      try {
        await refresh();
      } catch {
        setSaveError(
          new Error(
            "Your update was saved, but workspace information could not be refreshed. Reload the page to update workspace counts.",
          ),
        );
      }
    } catch (e) {
      setSaveError(e);
      // Keep the draft and the version it was based on until the user reviews current state.
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <PageTitle
        title={requester ? r.subject : "Service request details"}
        back="/app/requests"
        description={
          requester
            ? `${r.job_number} · Submitted by you on ${date(r.created_at)}`
            : undefined
        }
      />
      {success && (
        <div className="notice" role="status">
          {success}
        </div>
      )}
      <ErrorNotice error={saveError} />
      {saveError instanceof ApiError && saveError.status === 409 && <div className="notice">
        <p>Review the latest request before saving. Loading it will clear this draft.</p>
        <button className="button" onClick={() => { setEditing(false); reload(); }}>Load latest request</button>
      </div>}
      <Panel className="request-summary">
        <div className="summary-title">
          <div>
            <small className="job">{r.job_number}</small>
            <h2>{r.subject}</h2>
          </div>
          <div className="actions">
            <Badge value={r.priority} />
            <Badge value={r.status} />
          </div>
        </div>
        <div className="summary-meta">
          <div>
            <small>Requesting office</small>
            {r.office}
          </div>
          <div>
            <small>Service type</small>
            {r.service_type}
          </div>
          <div>
            <small>Date submitted</small>
            {date(r.created_at)}
          </div>
          <div>
            <small>Assigned to</small>
            {r.assignee || "Not currently assigned"}
          </div>
        </div>
      </Panel>
      <div className="detail-grid">
        <div>
          <Panel title={requester ? "Request information" : "Issue details"}>
            <div className="panel-body">
              <span className="demo-chip">Demo policies</span>
              <h3>Reported concern</h3>
              <p className="concern">{r.description}</p>
              <div className="info-grid">
                <div>
                  <small>Request category</small>
                  {r.category || "Uncategorized"}
                </div>
                <div>
                  <small>Location</small>
                  {r.location}
                </div>
                <div>
                  <small>Reported urgency</small>
                  {r.urgency}
                </div>
                <div>
                  <small>Reported impact</small>
                  {r.impact}
                </div>
                <div>
                  <small>Date completed</small>
                  {r.completed_at ? date(r.completed_at) : "Not yet completed"}
                </div>
              </div>
            </div>
          </Panel>
          {r.status === "For Clarification" && requester && (
            <Panel
              title="A few more details will help us move forward"
              subtitle="Respond below. Your original request and job number will be preserved."
            >
              <form className="panel-body" onChangeCapture={() => setEditing(true)} onSubmit={(e) => save(e, "reply")}>
                <div className="notice gold">
                  {
                    r.events?.find(
                      (ev) => ev.kind === "Clarification requested",
                    )?.message
                  }
                </div>
                <Field label="Clarification details *">
                  <textarea
                    name="message"
                    required
                    maxLength={10000}
                    rows={5}
                  />
                </Field>
                <button className="button primary" disabled={busy}>
                  Send clarification
                </button>
              </form>
            </Panel>
          )}
          <Panel
            title={requester ? "Actions taken & remarks" : "Activity history"}
            subtitle="Recorded updates for this service request"
          >
            <div className="timeline">
              {r.events?.map((ev) => (
                <div className="timeline-item" key={ev.id}>
                  <div className="timeline-heading">
                    <strong>{ev.kind}</strong>
                    <small>{date(ev.created_at)}</small>
                  </div>
                  <small className="actor">
                    {ev.actor}
                    {ev.visibility === "internal" ? " · Internal note" : ""}
                  </small>
                  <p>{ev.message}</p>
                </div>
              ))}
            </div>
          </Panel>
        </div>
        <aside>
          {requester ? (
            <>
              <Panel title="Status timeline">
                <div className="timeline">
                  {["Pending", "Assigned", "In Progress", "Completed"].map(
                    (s) => (
                      <div
                        className={
                          "timeline-item " + (r.status === s ? "current" : "")
                        }
                        key={s}
                      >
                        <strong>{s}</strong>
                        <p>
                          {r.status === s
                            ? "Current status"
                            : r.events?.some((e) => e.kind === s)
                              ? "Recorded in the activity history"
                              : s === "Completed"
                                ? "The completion date and outcome will appear here once recorded."
                                : "See the recorded activity for the service history."}
                        </p>
                      </div>
                    ),
                  )}
                </div>
              </Panel>
              {r.status === "Completed" &&
                (r.evaluation ? (
                  <div className="notice">
                    <h3>Thank you for your feedback</h3>
                    <p>Your rating: {r.evaluation.rating}/5</p>
                    <p>{r.evaluation.remarks}</p>
                  </div>
                ) : (
                  <div className="rating-prompt">
                    <h2>How was your service?</h2>
                    <p>Share your experience with the completed service.</p>
                    <Link className="button" to={`/app/requests/${r.id}/rate`}>
                      Rate completed service
                    </Link>
                  </div>
                ))}
              <div className="notice">
                <strong>Updates stay with your record</strong>
                <p>
                  Personnel actions and remarks are read-only. Any clarification
                  needed will appear as a request you can respond to.
                </p>
              </div>
            </>
          ) : r.status !== "Completed" ? (
            <>
              <Panel title="Manage request">
                <form
                  className="panel-body"
                  key={r.record_version}
                  onSubmit={(e) => save(e, "manage")}
                  onChangeCapture={() => setEditing(true)}
                >
                  <Field label="Status">
                    <select name="status" defaultValue={r.status}>
                      {[
                        "Pending",
                        "For Clarification",
                        "Assigned",
                        "In Progress",
                        "On Hold",
                        "Completed",
                      ].map((s) => (
                        <option key={s}>{s}</option>
                      ))}
                    </select>
                  </Field>
                  {user?.role === "admin" && (
                    <>
                      <Field label="Request category" hint="Manage office categories in Reports & analytics.">
                        <select name="category_id" defaultValue={r.category_id || ""}>
                          <option value="">Uncategorized</option>
                          {(catalog?.categories || []).map(c => <option value={c.id} key={c.id}>{c.name}</option>)}
                        </select>
                      </Field>
                      <Field label="Assigned personnel">
                        <select
                          name="personnel_id"
                          defaultValue={r.assignee_id || ""}
                        >
                          <option value="" disabled>
                            Choose eligible personnel
                          </option>
                          {catalog?.personnel
                            .filter((p) =>
                              p.service_type_ids.includes(r.service_type_id),
                            )
                            .map((p) => (
                              <option value={p.id} key={p.id}>
                                {p.name}
                              </option>
                            ))}
                        </select>
                      </Field>
                      <Field label="Official priority">
                        <select name="priority" defaultValue={r.priority || ""}>
                          <option value="" disabled>
                            Not yet reviewed
                          </option>
                          {["Low", "Medium", "High", "Critical"].map((p) => (
                            <option key={p}>{p}</option>
                          ))}
                        </select>
                      </Field>
                      <Field label="Priority override reason">
                        <input
                          name="reason"
                          maxLength={2000}
                          placeholder="Required when overriding the draft matrix"
                        />
                      </Field>
                    </>
                  )}
                  <Field label="Progress note / actions taken">
                    <textarea
                      name="message"
                      rows={4}
                      maxLength={10000}
                      placeholder="Describe work completed, findings, or the next action…"
                    />
                  </Field>
                  <Field label="Note visibility">
                    <select name="visibility" defaultValue="requester">
                      <option value="requester">Visible to requester</option>
                      <option value="internal">
                        Internal · MISO & personnel
                      </option>
                    </select>
                  </Field>
                  <Field label="Service outcome">
                    <textarea
                      name="outcome"
                      rows={2}
                      placeholder="Required to mark Completed"
                      maxLength={5000}
                    />
                  </Field>
                  <button className="button primary full" disabled={busy}>
                    {busy ? "Saving…" : "Save update"}
                  </button>
                  <p className="caption">
                    Changes are saved together with the progress note. Version{" "}
                    {r.record_version}.
                  </p>
                </form>
              </Panel>
              {user?.role === "admin" && (
                <Panel title="Request clarification">
                  <form
                    className="panel-body"
                    onSubmit={(e) => save(e, "clarify")}
                    onChangeCapture={() => setEditing(true)}
                  >
                    <Field label="What information is needed? *">
                      <textarea
                        name="message"
                        required
                        rows={3}
                        maxLength={10000}
                      />
                    </Field>
                    <button
                      className="button full"
                      disabled={busy || r.status === "For Clarification"}
                    >
                      Send clarification request
                    </button>
                  </form>
                </Panel>
              )}
            </>
          ) : (
            <div className="notice">
              <h3>Completed service</h3>
              <p>
                The outcome is recorded in the activity history. The requester
                may submit one optional evaluation.
              </p>
            </div>
          )}
          <div className="notice gold">
            <strong>Prototype workflow</strong>
            <p>
              Priority and transitions use configurable demo rules. Response
              targets have not been approved by MISO.
            </p>
          </div>
        </aside>
      </div>
      {requester && <Help />}
    </>
  );
}
export function RateService() {
  const { id } = useParams();
  const { user } = useAuth();
  const nav = useNavigate();
  const { data: r, error, loading } = useLoad<RequestRecord>("/requests/" + id);
  const [rating, setRating] = useState(0),
    [category, setCategory] = useState(""),
    [saveError, setSaveError] = useState<unknown>(),
    [busy, setBusy] = useState(false);
  const labels = ["Poor", "Fair", "Good", "Very good", "Excellent"];
  useEffect(() => {
    setRating(0);
    setCategory("");
    setSaveError(null);
  }, [id]);
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setSaveError(null);
    try {
      if (!rating) throw new Error("Choose your overall service rating.");
      await api("/requests/" + id + "/evaluation", {
        method: "POST",
        body: JSON.stringify({
          rating,
          category: category || null,
          remarks: new FormData(e.currentTarget).get("remarks"),
        }),
      });
      nav("/app/feedback");
    } catch (e) {
      setSaveError(e);
    } finally {
      setBusy(false);
    }
  }
  if (user?.role !== "requester") return <Navigate to="/app" replace />;
  if (loading) return <Loading />;
  if (error) return <ErrorNotice error={error} />;
  if (!r) return null;
  if (r.status !== "Completed" || r.evaluation)
    return <Navigate to={"/app/requests/" + id} replace />;
  return (
    <>
      <PageTitle
        title="How was your service experience?"
        description="Your feedback helps CLSU improve the support our community receives."
        back="/app/requests"
      />
      <div className="completed-banner">
        <Icon name="evaluation" />
        <div>
          <small>{r.job_number}</small>
          <h2>{r.subject}</h2>
          <small>
            {r.office} · Completed {date(r.completed_at!)}
          </small>
        </div>
        <Badge value="Completed" />
      </div>
      <div className="detail-grid">
        <div>
          <Panel
            title="Your overall service rating"
            subtitle="Choose one rating from 1 to 5. You can submit one optional rating for this job."
          >
            <form className="panel-body" onSubmit={submit}>
              <ErrorNotice error={saveError} />
              <fieldset>
                <legend>Overall experience *</legend>
                <div className="rating-options">
                  {labels.map((label, i) => (
                    <button
                      key={label}
                      className={rating === i + 1 ? "selected" : ""}
                      type="button"
                      aria-pressed={rating === i + 1}
                      onClick={() => setRating(i + 1)}
                    >
                      <Icon name="feedback" />
                      <strong>{i + 1}</strong>
                      <small>{label}</small>
                    </button>
                  ))}
                </div>
              </fieldset>
              {!!rating && (
                <p className="selected-rating">
                  Selected: {rating} — {labels[rating - 1]}
                </p>
              )}
              <fieldset>
                <legend>Feedback category (optional)</legend>
                <div className="category-options">
                  {["Complaint", "Suggestion", "Compliment", "Other"].map(
                    (c) => (
                      <button
                        type="button"
                        key={c}
                        aria-pressed={category === c}
                        className={category === c ? "selected" : ""}
                        onClick={() => setCategory(category === c ? "" : c)}
                      >
                        {c}
                      </button>
                    ),
                  )}
                </div>
              </fieldset>
              <Field
                label="Additional remarks (optional)"
                hint="Please focus on the service experience and avoid unnecessary personal information."
              >
                <textarea name="remarks" maxLength={5000} rows={4} />
              </Field>
              <div className="form-actions">
                <Link className="button" to={"/app/requests/" + id}>
                  Not now
                </Link>
                <button className="button primary" disabled={busy}>
                  {busy ? "Submitting…" : "Submit feedback"}
                </button>
              </div>
            </form>
          </Panel>
          <div className="notice">
            <strong>Private feedback, connected to the right service</strong>
            <p>
              Your feedback is linked to {r.job_number}. It is visible only to
              you and authorized CLSU staff.
            </p>
          </div>
        </div>
        <Panel title="Completed service record">
          <div className="panel-body info-list">
            <div>
              <small>Request category</small>
              {r.category || "Uncategorized"}
            </div>
            <div>
              <small>Service type</small>
              {r.service_type}
            </div>
            <div>
              <small>Requesting office</small>
              {r.office}
            </div>
            <div>
              <small>Location</small>
              {r.location}
            </div>
            <div>
              <small>Date requested</small>
              {date(r.created_at)}
            </div>
            <div>
              <small>Date completed</small>
              {date(r.completed_at!)}
            </div>
            <div>
              <small>Official priority</small>
              {r.priority}
            </div>
          </div>
        </Panel>
      </div>
      <Help />
    </>
  );
}
export function FeedbackPage({ publicPage = false }: { publicPage?: boolean }) {
  const [error, setError] = useState<unknown>(),
    [done, setDone] = useState(false),
    [busy, setBusy] = useState(false);
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    const f = Object.fromEntries(new FormData(e.currentTarget));
    try {
      await api("/feedback", {
        method: "POST",
        body: JSON.stringify({
          ...f,
          rating: f.rating ? Number(f.rating) : null,
        }),
      });
      setDone(true);
      window.scrollTo(0, 0);
    } catch (e) {
      setError(e);
    } finally {
      setBusy(false);
    }
  }
  if (done)
    return (
      <div className="feedback-success">
        <Icon name="evaluation" />
        <h1>Thank you for your feedback!</h1>
        <p>
          Your feedback has been received for private review by authorized CLSU
          staff.
        </p>
        <Link
          className="button primary"
          to={publicPage ? "/" : "/app/feedback"}
        >
          {publicPage ? "Back to options" : "View feedback history"}
        </Link>
      </div>
    );
  return (
    <>
      <Link className="back" to={publicPage ? "/" : "/app"}>
        ‹ Back to {publicPage ? "options" : "dashboard"}
      </Link>
      <p className="eyebrow">FEEDBACK</p>
      <h1>Send Us Your Feedback!</h1>
      <p>
        Tell us about your experience with a CLSU service. Fields marked * are
        required.
      </p>
      <form onSubmit={submit} className="feedback-form">
        <ErrorNotice error={error} />
        <Panel
          title="Feedback details"
          subtitle="Complaint · Suggestion · Compliment · Other"
        >
          <div className="panel-body">
            <Field label="Feedback category *">
              <select name="category" required defaultValue="">
                <option value="" disabled>
                  Choose a category
                </option>
                {["Complaint", "Suggestion", "Compliment", "Other"].map((c) => (
                  <option key={c}>{c}</option>
                ))}
              </select>
            </Field>
            <Field label="Short subject *">
              <input name="subject" required maxLength={200} />
            </Field>
            <Field label="Your feedback *">
              <textarea
                name="message"
                required
                minLength={10}
                maxLength={10000}
                rows={5}
              />
            </Field>
            <Field
              label="Related job number (optional)"
              hint="An entered job number is an unverified reference. It does not grant access to private service records."
            >
              <input name="unverified_job_number" maxLength={100} />
            </Field>
            <Field label="Overall experience (optional)">
              <select name="rating" defaultValue="">
                <option value="">Choose a rating</option>
                {["Poor", "Fair", "Good", "Very good", "Excellent"].map(
                  (r, i) => (
                    <option value={i + 1} key={r}>
                      {i + 1} of 5 · {r}
                    </option>
                  ),
                )}
              </select>
            </Field>
          </div>
        </Panel>
        <Panel
          title="Contact details (optional)"
          subtitle="Leave your details if you would like us to follow up."
        >
          <div className="panel-body">
            <Field label="Your name">
              <input name="contact_name" maxLength={100} autoComplete="name" />
            </Field>
            <Field label="Email address">
              <input
                name="contact_email"
                type="email"
                maxLength={200}
                autoComplete="email"
              />
            </Field>
          </div>
        </Panel>
        <div className="notice">
          Your feedback stays private and is reviewed by authorized CLSU staff.{" "}
          When signed in, feedback is linked to your account even if contact
          fields are blank. Please avoid sharing sensitive personal information.
        </div>
        <button className="button primary full" disabled={busy}>
          {busy ? "Sending…" : "Submit feedback"}
          <Asset screen="portal" name="imgArrowRight" />
        </button>
      </form>
    </>
  );
}
export { FeedbackHistory, FeedbackDetail } from "./feedback";

export function Personnel() {
  const { user, catalog: initialCatalog } = useAuth();
  const current = useLoad<Catalog>("/catalog", { live: user?.role === "admin" });
  const catalog = current.data || initialCatalog;
  if (user?.role !== "admin") return <Navigate to="/app" replace />;
  return (
    <>
      <PageTitle
        title="Personnel"
        description="University feedback management and service monitoring."
        actions={
          <Link className="button primary" to="/app/requests/new">
            <Icon name="plus" />
            New request
          </Link>
        }
      />
      <div className="insights-bar">
        <span>
          Workspace insights <em>Demo data</em>
        </span>
      </div>
      <Panel
        title="Service personnel"
        subtitle="Coordinate the people behind better campus services."
        className="personnel-panel"
      >
        <div className="personnel-grid">
          {catalog?.personnel.map((p) => (
            <article className="personnel-card" key={p.id}>
              <span className="avatar">
                {p.name
                  .split(" ")
                  .slice(0, 2)
                  .map((n) => n[0])
                  .join("")}
              </span>
              <h3>{p.name}</h3>
              <p>
                {catalog.service_types
                  .filter((s) => p.service_type_ids.includes(s.id))
                  .map((s) => s.name)
                  .join(" · ")}
              </p>
              <span className="assignment-count">
                {p.active_assignments} active assignments
              </span>
              <Link
                className="button"
                to={"/app/requests?personnel_id=" + p.id}
              >
                View assignments <Icon name="arrow" />
              </Link>
            </article>
          ))}
        </div>
      </Panel>
    </>
  );
}
export function Reports() {
  const { user } = useAuth();
  if (user?.role !== "admin") return <Navigate to="/app" replace />;
  return <><PageTitle title="Reports & analytics" description="Service evaluation, request activity and office performance." /><ReportWorkspace /></>;
}
export function Notifications() {
  const { data, error, loading, reload } = useNotifications();
  const [readError, setReadError] = useState<unknown>(),
    [readingId, setReadingId] = useState<number | null>(null);
  async function markRead(id: number) {
    setReadError(null);
    setReadingId(id);
    try {
      await api("/notifications/" + id + "/read", {
        method: "POST",
        body: "{}",
      });
      window.dispatchEvent(new Event("portal-notifications-changed"));
      reload();
    } catch (e) {
      setReadError(e);
    } finally {
      setReadingId(null);
    }
  }
  return (
    <>
      <PageTitle
        title="Your notifications"
        description="New submissions, service updates and next steps."
      />
      <ErrorNotice error={error} />
      <ErrorNotice error={readError} />
      <Panel title="Latest updates">
        {loading ? (
          <Loading />
        ) : error ? null : data?.length ? (
          data.map((n) => (
            <div
              className={"notification-row " + (!n.read_at ? "unread" : "")}
              key={n.id}
            >
              <Icon name="bell" />
              <div>
                <Link to={n.url || "/app/requests/" + n.service_request_id}>
                  {n.message}
                </Link>
                <small>{date(n.created_at)}</small>
              </div>
              {!n.read_at && (
                <button
                  className="button"
                  disabled={readingId !== null}
                  onClick={() => markRead(n.id)}
                >
                  {readingId === n.id ? "Marking…" : "Mark read"}
                </button>
              )}
            </div>
          ))
        ) : (
          <Empty title="You’re all caught up" />
        )}
      </Panel>
      <Help />
    </>
  );
}
export function Reserved({ kind }: { kind: string }) {
  const { user } = useAuth();
  if (user?.role !== "admin") return <Navigate to="/app" replace />;
  const isMcp = kind === "assistant";
  return (
    <>
      <PageTitle
        title={
          isMcp
            ? "MCP Assistant"
            : kind === "settings"
              ? "Service settings"
              : "System evaluation"
        }
        description="Reserved for a future version."
      />
      <Panel
        title={
          isMcp
            ? "Assistant integration is not connected"
            : "Configuration preview"
        }
      >
        <div className="reserved">
          <Icon name={isMcp ? "assistant" : "settings"} />
          <h2>
            {isMcp
              ? "Ready for a future connection"
              : "Prototype configuration"}
          </h2>
          <p>
            {isMcp
              ? "The core request, assignment, tracking, and feedback workflows work independently. A future MCP adapter will use the same authenticated application services."
              : "This rough version uses documented demo policies. Runtime policy administration and system evaluation are not implemented yet."}
          </p>
          <div className="notice">
            {isMcp
              ? "No model provider, API key, MCP server, or chat execution is enabled."
              : "Draft policy configuration is maintained by the development team. Contact MISO to approve production rules."}
          </div>
          <Link className="button primary" to="/app/requests">
            Go to service requests <Icon name="arrow" />
          </Link>
        </div>
      </Panel>
    </>
  );
}
export function HelpPage() {
  return (
    <>
      <PageTitle
        title="Help & support"
        description="A little guidance for using the MISO Service Portal."
      />
      <Panel title="Requests and access">
        <div className="panel-body">
          <h3>Submit and track a request</h3>
          <p>
            Sign in with your authorized account, choose New request, and
            describe the service you need. Open My requests to follow progress
            or respond to clarification.
          </p>
          <h3>Account assistance</h3>
          <p>
            Contact your university MISO office for account provisioning or
            password recovery. Self-service password recovery is reserved for a
            later version.
          </p>
          <h3>Privacy and data use</h3>
          <p>
            Your service records and verified ratings are available to you and
            authorized service staff. Internal operational notes are restricted.
            General feedback is reviewed privately. Entering a job number in a
            public form never grants record access.
          </p>
          <h3>Prototype notice</h3>
          <p>
            This local build contains synthetic people and illustrative service
            records. Workflow and priority policies are drafts pending MISO
            validation. Attachments, email delivery, and MCP execution are not
            enabled.
          </p>
        </div>
      </Panel>
    </>
  );
}
