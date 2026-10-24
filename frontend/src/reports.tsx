import { useEffect, useState, type FormEvent } from "react";
import { api, date } from "./api";
import { useAuth } from "./App";
import { useLoad } from "./useLoad";
import { Empty, ErrorNotice, Field, Icon, Loading, Panel } from "./components";

type Summary = {
  total: number; completed: number; open: number; completion_rate: number;
  rating_count: number; average_rating: number | null;
  resolution_count: number; resolution_excluded: number; average_resolution_hours: number | null;
};
type Group = Summary & { id: string | number | null; name: string };
type Report = {
  period_start: string; period_end: string; generated_at: string; office: string;
  definition: string; rating_definition: string; resolution_definition: string; concern_definition: string;
  summary: Summary; categories: Group[]; priorities: Group[]; statuses: Group[]; personnel: Group[];
  recurring_concerns: { subject: string; count: number; job_numbers: string[] }[];
};
type Export = { id: number; status: string; format: string; download_url?: string | null; error?: string };

export function ReportControls({ days, setDays, month = "", setMonth }: {
  days: number; setDays: (n: number) => void; month?: string; setMonth?: (month: string) => void;
}) {
  const [format, setFormat] = useState(setMonth ? "pdf" : "csv");
  const [report, setReport] = useState<Export>();
  const [exportPeriod, setExportPeriod] = useState("");
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (!report || ["completed", "failed"].includes(report.status)) return;
    const controller = new AbortController();
    let timer: ReturnType<typeof setTimeout>;
    async function poll() {
      let finished = false;
      try {
        const next = await api<Export>("/reports/" + report!.id, { signal: controller.signal });
        if (controller.signal.aborted) return;
        setReport(next); setError(null);
        finished = ["completed", "failed"].includes(next.status);
      } catch (e) { if (!controller.signal.aborted) setError(e); }
      if (!finished && !controller.signal.aborted) timer = setTimeout(poll, 1500);
    }
    timer = setTimeout(poll, 1500);
    return () => { controller.abort(); clearTimeout(timer); };
  }, [report?.id, report?.status]);
  async function exportReport() {
    setBusy(true); setError(null);
    try {
      const next = await api<Export>("/reports", { method: "POST", body: JSON.stringify({ ...(month ? { month } : { days }), format }) });
      setExportPeriod(month ? month : `Last ${days} days`); setReport(next);
    } catch (e) { setError(e); } finally { setBusy(false); }
  }
  return <>
    <div className="insights-bar report-controls">
      <span><i />Service evaluation <em>Office scope</em></span>
      <div>
        <label className="period"><Icon name="calendar" />
          <select aria-label="Reporting period" value={month ? "month" : days} onChange={e => {
            if (e.target.value === "month") setMonth?.(new Date().toLocaleString("sv-SE", { timeZone: "Asia/Manila" }).slice(0, 7));
            else { setMonth?.(""); setDays(Number(e.target.value)); }
          }}>
            <option value={30}>Last 30 days</option><option value={7}>Last 7 days</option>
            <option value={90}>Last 90 days</option><option value={366}>Last year</option>
            {setMonth && <option value="month">Calendar month</option>}
          </select>
        </label>
        {month && <input type="month" aria-label="Report month" required value={month} onChange={e => setMonth?.(e.target.value)} />}
        <select aria-label="Export format" value={format} onChange={e => setFormat(e.target.value)}>
          <option value="pdf">PDF</option><option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV register</option>
        </select>
        <button className="button" onClick={exportReport} disabled={busy || (!!report && !["completed", "failed"].includes(report.status))}>
          <Icon name="download" />{busy ? "Requesting…" : "Export report"}
        </button>
      </div>
    </div>
    {report && <div className="notice" role="status">
      {exportPeriod} · {(report.format ?? "csv").toUpperCase()} · {report.status === "completed" && report.download_url ?
        <a href={report.download_url}>Download report {(report.format ?? "csv").toUpperCase()} <Icon name="download" /></a> :
        report.status === "failed" || report.status === "completed" ? report.error || "Export unavailable. Generate a new report." : "Your report is queued. The queue worker will prepare the export."}
    </div>}
    <ErrorNotice error={error} />
  </>;
}

function Breakdown({ title, subtitle, rows }: { title: string; subtitle: string; rows: Group[] }) {
  return <Panel title={title} subtitle={subtitle}>
    {rows.length ? <div className="report-table-scroll" tabIndex={0} role="region" aria-label={title}>
      <table className="report-table"><thead><tr><th scope="col">Group</th><th scope="col">Requests</th><th scope="col">Completed</th><th scope="col">Avg. rating</th><th scope="col">Avg. hours</th></tr></thead>
        <tbody>{rows.map(row => <tr key={row.id ?? "none"}><th scope="row">{row.name}</th><td>{row.total}</td><td>{row.completed}</td>
          <td>{row.average_rating ?? "—"}<small>{row.rating_count} ratings</small></td>
          <td>{row.average_resolution_hours ?? "—"}<small>{row.resolution_count} completions</small></td></tr>)}</tbody>
      </table>
    </div> : <p className="report-empty">No requests in this period.</p>}
  </Panel>;
}

function Categories() {
  const { catalog, refresh } = useAuth();
  const [name, setName] = useState(""); const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>(); const [saved, setSaved] = useState("");
  async function add(e: FormEvent) {
    e.preventDefault(); setError(null); setSaved(""); setBusy(true);
    try {
      const category = await api<{ name: string }>("/request-categories", { method: "POST", body: JSON.stringify({ name }) });
      setName(""); setSaved(`Category “${category.name}” added. Assign it when reviewing a request.`);
      try { await refresh(); } catch { setError(new Error("The category was saved. Reload to refresh your category list.")); }
    } catch (e) { setError(e); } finally { setBusy(false); }
  }
  return <Panel title="Office request categories" subtitle="Create categories here, then assign them in Service request details. Existing requests remain Uncategorized until reviewed.">
    <div className="report-category-body">
      <p>{catalog?.categories?.length ? catalog.categories.map(c => c.name).join(" · ") : "No categories defined yet."}</p>
      <form onSubmit={add} className="report-category-form"><Field label="New category"><input value={name} onChange={e => setName(e.target.value)} required maxLength={80} /></Field>
        <button className="button primary" disabled={busy}>{busy ? "Adding…" : "Add category"}</button>
      </form>
      {saved && <p className="notice success" role="status">{saved}</p>}<ErrorNotice error={error} />
    </div>
  </Panel>;
}

export function ReportWorkspace() {
  const [days, setDays] = useState(30); const [month, setMonth] = useState("");
  const query = month ? "month=" + month : "days=" + days;
  const { data, error, reload } = useLoad<Report>("/report-summary?" + query, { live: true });
  const s = data?.summary;
  return <div className="report-workspace">
    <ReportControls days={days} setDays={setDays} month={month} setMonth={setMonth} />
    <ErrorNotice error={error} />
    {error ? <button className="button" onClick={reload}>Retry loading reports</button> : !data ? <Loading /> : <>
      <p className="report-period">{data.office} · {date(data.period_start)} – {date(data.period_end)}<span>Generated {data.generated_at} · Asia/Manila</span></p>
      <div className="stats-grid">
        {[
          ["Requests received", s!.total, `${s!.open} open requests`, "total"],
          ["Completed requests", s!.completed, `${s!.completion_rate}% completion rate`, "completed"],
          ["Average verified rating", s!.average_rating ?? "—", `${s!.rating_count} verified ratings · out of 5`, "satisfaction"],
          ["Average resolution", s!.average_resolution_hours === null ? "—" : `${s!.average_resolution_hours} h`, `${s!.resolution_count} completions · calendar hours`, "calendar"],
        ].map(([label, value, hint, icon]) => <div className="stat" key={label}><div><span>{label}</span><span className="stat-icon"><Icon name={String(icon)} /></span></div><strong>{value}</strong><small>{hint}</small></div>)}
      </div>
      {!s!.total && <Empty title="No requests in this period">Choose another period to view service activity. Exports will still include the report definitions.</Empty>}
      <div className="report-breakdowns">
        <Breakdown title="Requests by category" subtitle="Staff-assigned categories, independent of service type" rows={data.categories} />
        <Breakdown title="Requests by priority" subtitle="Official priority recorded during review" rows={data.priorities} />
        <Breakdown title="Requests by status" subtitle="Current status of requests submitted in this period" rows={data.statuses} />
        <Breakdown title="Requests by personnel" subtitle="Current assignee for open work; final assignee for completed work" rows={data.personnel} />
      </div>
      <Panel title="Recurring concerns" subtitle="Repeated subjects after matching case and whitespace; at least two requests.">
        {data.recurring_concerns.length ? <div className="report-table-scroll" tabIndex={0} role="region" aria-label="Recurring concerns"><table className="report-table"><thead><tr><th scope="col">Subject</th><th scope="col">Requests</th><th scope="col">Example job numbers</th></tr></thead><tbody>{data.recurring_concerns.map(c => <tr key={c.subject}><th scope="row">{c.subject}</th><td>{c.count}</td><td>{c.job_numbers.join(", ")}</td></tr>)}</tbody></table></div> : <p className="report-empty">No repeated request subjects in this period.</p>}
      </Panel>
      <details className="report-definitions"><summary>How these figures are calculated</summary><p>{data.definition}</p><p>{data.rating_definition}</p><p>{data.resolution_definition}</p><p>{s!.resolution_excluded} completed requests excluded from the resolution average.</p><p>{data.concern_definition}</p></details>
    </>}
    <Categories />
  </div>;
}
