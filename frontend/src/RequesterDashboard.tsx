import { Link } from "react-router-dom";
import { useAuth } from "./App";
import { date, parseDate, type RequestRecord } from "./api";
import { useLoad } from "./useLoad";
import { Asset, Empty, ErrorNotice, Loading, Panel } from "./components";

export function RequesterIcon({ name }: { name: string }) {
  return (
    <span className="icon">
      <Asset screen="requester" name={name} />
    </span>
  );
}
type DashboardData = {
  counts: {
    total: number;
    open: number;
    completed: number;
    awaiting_rating: number;
  };
  recent: RequestRecord[];
  clarification: {
    id: number;
    job_number: string;
    message: string | null;
  } | null;
  unrated: RequestRecord | null;
  updates: {
    id: number;
    service_request_id: number;
    message: string;
    created_at: string;
    job_number: string;
  }[];
};
const statusIcons: Record<string, string> = {
  Pending: "imgClock4",
  "For Clarification": "imgMessageSquareText1",
  "In Progress": "imgLoaderCircle",
  Completed: "imgCircleCheck1",
};
function Status({ value }: { value: string }) {
  return (
    <span
      className={"dashboard-status " + value.toLowerCase().replaceAll(" ", "-")}
    >
      <RequesterIcon name={statusIcons[value] || "imgClock4"} />
      {value}
    </span>
  );
}

export default function RequesterDashboard() {
  const { user } = useAuth();
  const { data, error, reload } = useLoad<DashboardData>("/requester-dashboard", { live: true });
  if (error)
    return (
      <>
        <ErrorNotice error={error} />
        <button className="button" onClick={reload}>
          Retry dashboard
        </button>
      </>
    );
  if (!data) return <Loading />;
  const cards = [
    ["My requests", data.counts.total, "All your submissions", "imgFiles1"],
    ["Open requests", data.counts.open, "Across active statuses", "imgClock3"],
    [
      "Completed",
      data.counts.completed,
      "Services delivered",
      "imgCircleCheck",
    ],
    [
      "Awaiting my rating",
      data.counts.awaiting_rating,
      "Share your experience",
      "imgStar",
    ],
  ] as const;
  return (
    <div className="requester-dashboard">
      <section className="dashboard-intro">
        <p>My workspace / Dashboard</p>
        <div className="dashboard-title-row">
          <h1>Good morning, {user?.name.split(" ")[0]}.</h1>
          <div className="actions">
            <Link className="button" to="/app/feedback/new">
              <RequesterIcon name="imgMessageSquare1" />
              Share feedback
            </Link>
            <Link className="button primary" to="/app/requests/new">
              <RequesterIcon name="imgPlus" />
              New request
            </Link>
          </div>
        </div>
        <p>
          Here’s where your service requests stand. We’ll keep you in the loop.
        </p>
      </section>
      <section className="dashboard-metrics" aria-label="Your request summary">
        {cards.map(([title, value, caption, icon]) => (
          <div className="dashboard-metric" key={title}>
            <div>
              <span>{title}</span>
              <RequesterIcon name={icon} />
            </div>
            <strong>{String(value).padStart(2, "0")}</strong>
            <p>{caption}</p>
          </div>
        ))}
      </section>
      {data.clarification && (
        <aside className="dashboard-clarification">
          <RequesterIcon name="imgMessageSquareText" />
          <div>
            <strong>A few more details will help us move forward</strong>
            <p>
              {data.clarification.job_number} ·{" "}
              {data.clarification.message ||
                "MISO needs more information about your request."}
            </p>
          </div>
          <Link
            className="button"
            to={"/app/requests/" + data.clarification.id}
          >
            <RequesterIcon name="imgArrowRight" />
            Provide clarification
          </Link>
        </aside>
      )}
      <div className="dashboard-columns">
        <div>
          <Panel
            title="Recent requests"
            className="dashboard-recent"
            action={
              <Link to="/app/requests">
                <RequesterIcon name="imgArrowRight" />
                View all requests
              </Link>
            }
          >
            {data.recent.length ? (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>REQUEST / SERVICE</th>
                      <th>STATUS</th>
                      <th>SUBMITTED</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.recent.map((r) => (
                      <tr key={r.id}>
                        <td>
                          <small className="job">{r.job_number}</small>
                          <Link to={"/app/requests/" + r.id}>{r.subject}</Link>
                          <small>{r.service_type}</small>
                        </td>
                        <td>
                          <Status value={r.status} />
                          <Link
                            className="dashboard-row-action"
                            to={
                              r.status === "Completed" && !r.evaluation
                                ? `/app/requests/${r.id}/rate`
                                : "/app/requests/" + r.id
                            }
                          >
                            {r.status === "For Clarification"
                              ? "Clarify request"
                              : r.status === "Completed" && !r.evaluation
                                ? "Rate service"
                                : r.status === "In Progress"
                                  ? "Track request"
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
            ) : (
              <Empty title="No requests yet">
                Submit your first service request to follow its progress here.
              </Empty>
            )}
          </Panel>
          <p className="dashboard-privacy">
            <RequesterIcon name="imgLockKeyhole" />
            This overview includes only requests submitted by you.
          </p>
        </div>
        <aside className="dashboard-side">
          {data.unrated && (
            <section className="dashboard-rating">
              <small>
                <RequesterIcon name="imgStar1" />
                YOUR FEEDBACK MATTERS
              </small>
              <h2>How was your service?</h2>
              <p>
                Your {data.unrated.subject.toLowerCase()} is complete. Tell us
                how we did.
              </p>
              <p>
                {data.unrated.job_number} · Completed{" "}
                {date(data.unrated.completed_at!)}
              </p>
              <Link
                className="button"
                to={`/app/requests/${data.unrated.id}/rate`}
              >
                <RequesterIcon name="imgArrowRight" />
                Rate completed service
              </Link>
            </section>
          )}
          <Panel title="Latest updates" className="dashboard-updates">
            <div className="panel-body">
              {data.updates.length ? (
                data.updates.map((n) => (
                  <Link
                    className="dashboard-update"
                    to={"/app/requests/" + n.service_request_id}
                    key={n.id}
                  >
                    <RequesterIcon
                      name={
                        n.message.startsWith("A few more") ||
                        n.message.toLowerCase().includes("clarification")
                          ? "imgMessageSquareText2"
                          : n.message.toLowerCase().includes("received")
                            ? "imgCheck"
                            : "imgWrench"
                      }
                    />
                    <span>
                      {n.message.startsWith("A few more")
                        ? "Clarification requested"
                        : n.message.startsWith("Service update:")
                          ? "Service update"
                          : n.message}
                      <small>{n.job_number}</small>
                      <time dateTime={n.created_at}>
                        {date(n.created_at)},{" "}
                        {parseDate(n.created_at).toLocaleTimeString("en-PH", {
                          hour: "numeric",
                          minute: "2-digit",
                          timeZone: "Asia/Manila",
                        })}
                      </time>
                    </span>
                  </Link>
                ))
              ) : (
                <p>No updates yet.</p>
              )}
              <Link className="dashboard-all-updates" to="/app/notifications">
                <RequesterIcon name="imgArrowRight" />
                All notifications
              </Link>
            </div>
          </Panel>
        </aside>
      </div>
      <aside className="dashboard-help">
        <RequesterIcon name="imgCircleHelp" />
        <h3>A little guidance?</h3>
        <p>Get help with requests, access, or understanding an update.</p>
        <Link to="/app/help">
          <RequesterIcon name="imgArrowUpRight" />
          Help & support
        </Link>
      </aside>
    </div>
  );
}
