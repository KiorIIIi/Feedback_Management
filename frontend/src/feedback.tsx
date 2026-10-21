import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from "react";
import { Link, Navigate, useLocation, useParams, useSearchParams } from "react-router-dom";
import { useAuth } from "./App";
import { date, parseDate } from "./api";
import { Empty, ErrorNotice, Icon, Loading } from "./components";
import { useLoad } from "./useLoad";

type Kind = "general" | "evaluation";
type FeedbackRecord = {
  id?: number; request_id?: number; subject: string; message?: string; remarks?: string;
  category?: string; rating: number | null; created_at: string; job_number?: string;
  unverified_job_number?: string | null; contact_name?: string | null; contact_email?: string | null;
  service_type?: string | null; requesting_office?: string | null;
  final_assigned_personnel?: string | null; completed_at?: string | null; request_category?: string | null;
};
type Pagination = { current_page: number; last_page: number; total: number };
type FeedbackData = {
  general: FeedbackRecord[]; evaluations: FeedbackRecord[];
  counts: { general: number; evaluations: number };
  pagination: { general: Pagination; evaluations: Pagination };
};
const asset = (name: string) => `/assets/figma/feedback-review/${name}.svg`;
const categories = ["Complaint", "Suggestion", "Compliment", "Other"];
const filterKeys = ["search", "rating", "days", "category"] as const;

function Rating({ value, compact = false }: { value: number | null; compact?: boolean }) {
  return <div className={`feedback-score ${compact ? "is-compact" : ""}`}>
    <span className="feedback-score-value">{value === null ? "Not rated" : compact ? `${value}/5` : `${value} of 5`}</span>
    {value !== null && <img className="feedback-stars" src={asset(`rating-${value}`)} width="86" height="14" alt="" />}
  </div>;
}
function Meta({ label, value }: { label: string; value?: string | null }) {
  return <div className="feedback-meta"><dt>{label}</dt><dd>{value?.trim() || "Not supplied"}</dd></div>;
}
function received(value: string) {
  return `${date(value)}, ${parseDate(value).toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit", timeZone: "Asia/Manila" })} (PHT)`;
}
function scrollKey(userId: number | undefined, query: string) {
  return `feedback-position:${userId}:${query}`;
}
function savePosition(key: string) {
  try { sessionStorage.setItem(key, String(window.scrollY)); } catch { /* Storage may be disabled. */ }
}

export function FeedbackHistory() {
  const { user } = useAuth();
  const [params, setParams] = useSearchParams();
  const query = params.toString();
  const kind: Kind = params.get("tab") === "evaluation" ? "evaluation" : "general";
  const [draft, setDraft] = useState(() => Object.fromEntries(filterKeys.map(key => [key, params.get(key) || ""])));
  useEffect(() => setDraft(Object.fromEntries(filterKeys.map(key => [key, new URLSearchParams(query).get(key) || ""]))), [query]);
  const apiParams = new URLSearchParams(params);
  apiParams.delete("tab");
  // The category control belongs to independent feedback, not request categories.
  if (kind === "evaluation") apiParams.delete("category");
  const { data, error, loading, reload } = useLoad<FeedbackData>(`/feedback?${apiParams}`);
  const key = scrollKey(user?.id, query);
  const restored = useRef<string | null>(null);
  useEffect(() => {
    if (loading || error || restored.current === key) return;
    const frame = requestAnimationFrame(() => {
      let position = 0;
      try { position = Number(sessionStorage.getItem(key)) || 0; } catch { /* Use the top if storage is unavailable. */ }
      window.scrollTo(0, position);
      restored.current = key;
    });
    return () => cancelAnimationFrame(frame);
  }, [key, loading, error]);
  useEffect(() => {
    const store = () => { if (restored.current === key) savePosition(key); };
    window.addEventListener("scroll", store, { passive: true });
    return () => window.removeEventListener("scroll", store);
  }, [key]);
  function selectTab(nextKind: Kind) {
    savePosition(key);
    const next = new URLSearchParams(params);
    if (nextKind === "general") next.delete("tab"); else next.set("tab", nextKind);
    setParams(next);
  }
  function tabKey(event: KeyboardEvent<HTMLButtonElement>) {
    if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
    event.preventDefault();
    const nextKind = event.key === "Home" ? "general" : event.key === "End" ? "evaluation" : kind === "general" ? "evaluation" : "general";
    selectTab(nextKind);
    document.getElementById(`feedback-tab-${nextKind}`)?.focus();
  }
  function apply(event: FormEvent) {
    event.preventDefault();
    const next = new URLSearchParams(params);
    for (const field of filterKeys) {
      const value = draft[field].trim();
      if (value) next.set(field, value); else next.delete(field);
    }
    next.delete("general_page"); next.delete("evaluation_page");
    try { sessionStorage.removeItem(scrollKey(user?.id, next.toString())); } catch { /* Optional storage. */ }
    restored.current = null;
    setParams(next);
  }
  function clear() {
    setDraft(Object.fromEntries(filterKeys.map(key => [key, ""])));
    setParams(kind === "evaluation" ? { tab: "evaluation" } : {});
  }
  if (user?.role === "personnel") return <Navigate to="/app" replace />;
  const filtered = filterKeys.some(field => (field !== "category" || kind === "general") && params.get(field));
  const records = kind === "general" ? data?.general : data?.evaluations;
  const pagination = kind === "general" ? data?.pagination.general : data?.pagination.evaluations;
  return <div className="feedback-workspace">
    <header className="feedback-page-heading">
      <div><h1>{user?.role === "admin" ? "Feedback & ratings" : "Feedback history"}</h1>
        <p>Read independent feedback and verified service evaluations.</p></div>
      {user?.role === "requester" && <Link className="button primary" to="/app/feedback/new">Share feedback</Link>}
    </header>
    <div className="feedback-tabs" role="tablist" aria-label="Feedback type">
      {(["general", "evaluation"] as Kind[]).map(type => <button key={type} id={`feedback-tab-${type}`} type="button" role="tab"
        aria-selected={kind === type} aria-controls="feedback-results" tabIndex={kind === type ? 0 : -1}
        onKeyDown={tabKey} onClick={() => selectTab(type)}>
        {type === "general" ? "General feedback" : "Service evaluations"} <span>· {data ? data.counts[type === "general" ? "general" : "evaluations"] : "…"}</span>
      </button>)}
    </div>
    <form className="feedback-filters" onSubmit={apply} aria-label="Filter feedback">
      <div className="feedback-search-row">
        <label className="feedback-search"><img src={asset("search")} alt="" /><span className="feedback-sr-only">Search feedback</span>
          <input type="search" value={draft.search} maxLength={200} onChange={e => setDraft({ ...draft, search: e.target.value })} placeholder="Search subject, message or reference" /></label>
        <button className="button primary feedback-apply" type="submit">Apply filters</button>
      </div>
      <div className="feedback-filter-row">
        <label className="feedback-filter"><span>Rating</span><select aria-label="Rating" value={draft.rating} onChange={e => setDraft({ ...draft, rating: e.target.value })}>
          <option value="">All ratings</option><option value="low">Low rated · 1–2 of 5</option>
          {[1, 2, 3, 4, 5].map(rating => <option key={rating} value={rating}>{rating} of 5</option>)}
          <option value="unrated">Not rated</option>
        </select></label>
        <label className="feedback-filter"><span>Date received</span><select aria-label="Date received" value={draft.days} onChange={e => setDraft({ ...draft, days: e.target.value })}>
          <option value="">Any date</option><option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option>
        </select></label>
        {kind === "general" && <label className="feedback-filter feedback-category"><span>Feedback category</span><select aria-label="Feedback category" value={draft.category} onChange={e => setDraft({ ...draft, category: e.target.value })}>
          <option value="">All categories</option>{categories.map(category => <option key={category}>{category}</option>)}
        </select></label>}
        {filtered && <button type="button" className="button feedback-clear" onClick={clear}>Clear filters</button>}
      </div>
    </form>
    <div id="feedback-results" role="tabpanel" aria-labelledby={`feedback-tab-${kind}`} aria-busy={loading}>
      {loading ? <Loading /> : error ? <><ErrorNotice error={error} /><button className="button" onClick={reload}>Retry loading feedback</button></> : records?.length ? <>
        <div className="feedback-results">
          {records.map(record => {
            const path = `/app/feedback/${kind}/${kind === "general" ? record.id : record.request_id}${query ? `?${query}` : ""}`;
            const message = (record.message || record.remarks || "No additional remarks.").replace(/\s+/g, " ").trim();
            return <article className="feedback-row" key={record.id ?? record.request_id}>
              <div className="feedback-row-content">
                <h2><Link to={path} onClick={() => savePosition(key)}>{record.subject}</Link></h2>
                <p className="feedback-row-meta">{kind === "general" ? record.category : "Verified service evaluation"} · {date(record.created_at)}</p>
                <p className="feedback-preview">{message.slice(0, 180)}{message.length > 180 ? "…" : ""}</p>
              </div>
              <div className="feedback-row-action"><Rating value={record.rating} compact />
                <Link className="feedback-open" to={path} onClick={() => savePosition(key)}>View {kind === "general" ? "feedback" : "evaluation"}<Icon name="arrow" /></Link>
              </div>
            </article>;
          })}
        </div>
        {pagination && <nav className="feedback-pager" aria-label="Feedback pages">
          <p role="status">Showing {(pagination.current_page - 1) * 25 + 1}–{Math.min(pagination.current_page * 25, pagination.total)} of {pagination.total} · page {pagination.current_page} of {pagination.last_page}</p>
          {pagination.last_page > 1 && <div>{[[-1, "Previous"], [1, "Next"]].map(([delta, label]) => <button className="button" key={label} disabled={Number(delta) === -1 ? pagination.current_page <= 1 : pagination.current_page >= pagination.last_page}
            onClick={() => { const next = new URLSearchParams(params); next.set(kind === "general" ? "general_page" : "evaluation_page", String(pagination.current_page + Number(delta))); setParams(next); }}>{label}</button>)}</div>}
        </nav>}
      </> : <section className="feedback-empty"><Empty title={filtered ? "No matching feedback" : kind === "general" ? "No general feedback yet" : "No service evaluations yet"}>
        {filtered ? "Try a different search or clear the filters." : "Submissions for this feedback type will appear here."}
      </Empty>{filtered && <button className="button" onClick={clear}>Clear filters</button>}</section>}
    </div>
  </div>;
}

export function FeedbackDetail() {
  const { user } = useAuth();
  const { kind, id } = useParams();
  const location = useLocation();
  const { data, error, loading, reload } = useLoad<{ kind: Kind; record: FeedbackRecord }>(`/feedback/${kind}/${id}`);
  useEffect(() => { window.scrollTo(0, 0); }, [kind, id]);
  if (user?.role === "personnel") return <Navigate to="/app" replace />;
  const record = data?.record;
  const general = data?.kind === "general";
  return <div className="feedback-workspace">
    <Link className="feedback-back" to={`/app/feedback${location.search}`}><img src={asset("back")} alt="" />Back to feedback</Link>
    {loading ? <Loading /> : error ? <><ErrorNotice error={error} /><button className="button" onClick={reload}>Retry loading feedback</button></> : record && <>
      <header className="feedback-page-heading"><div><h1>{record.subject}</h1><p>{general ? `General feedback · ${record.category}` : "Verified service evaluation"} · {date(record.created_at)}</p></div></header>
      <a className="button feedback-jump" href="#feedback-context">Jump to {general ? "submission details" : "related service"}</a>
      <div className="feedback-reading-grid">
        <section className="feedback-message-card" aria-labelledby="feedback-message-title">
          <div className="feedback-message-header"><Rating value={record.rating} /><span className="feedback-source-pill">{general ? "Independent feedback" : "Verified evaluation"}</span></div>
          <h2 id="feedback-message-title">{general ? "Feedback message" : "Evaluation remarks"}</h2>
          <p className="feedback-full-message">{record.message || record.remarks || "No additional remarks."}</p>
        </section>
        <aside id="feedback-context" className="feedback-context-card" tabIndex={-1} aria-labelledby="feedback-context-title">
          <h2 id="feedback-context-title">{general ? "Submission details" : "Related service"}</h2>
          {!general && <span className="feedback-completed">Completed service</span>}
          <dl>{general ? <>
            <Meta label="Received" value={received(record.created_at)} /><Meta label="Feedback category" value={record.category} />
            <Meta label="Contact name · as supplied" value={record.contact_name} /><Meta label="Contact email · as supplied" value={record.contact_email} />
            <div className="feedback-reference"><Meta label="Related job number · unverified" value={record.unverified_job_number} />
              <p>An entered reference does not verify a service request or grant access to it.</p></div>
          </> : <>
            <Meta label="Job number" value={record.job_number} /><Meta label="Service type" value={record.service_type} />
            <Meta label="Requesting office" value={record.requesting_office || "No office affiliation"} /><Meta label="Final assigned personnel" value={record.final_assigned_personnel || "Unassigned"} />
            <Meta label="Completed" value={record.completed_at ? received(record.completed_at) : null} /><Meta label="Request category" value={record.request_category || "Uncategorized"} />
          </>}</dl>
          {!general && <Link className="button primary feedback-service-link" to={`/app/requests/${record.request_id}`}>Open service request</Link>}
        </aside>
      </div>
    </>}
  </div>;
}
