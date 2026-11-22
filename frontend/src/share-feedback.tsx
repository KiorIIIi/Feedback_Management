import { useEffect, useRef, useState, type FormEvent } from "react";
import { QRCodeCanvas, QRCodeSVG } from "qrcode.react";
import { Asset, ErrorNotice } from "./components";
import { api, date, parseDate } from "./api";
import { useLoad } from "./useLoad";

const BASE_KEY = "clsu.feedbackShareBase";
const LABEL_KEY = "clsu.feedbackShareEvent";

type FeedbackForm = {
  id: number;
  token: string;
  title: string;
  description: string | null;
  opens_at: string | null;
  closes_at: string | null;
  is_active: boolean;
  status: "open" | "scheduled" | "expired" | "closed";
  responses: number;
  created_at: string;
};

function readStored(key: string): string {
  try {
    return localStorage.getItem(key) ?? "";
  } catch {
    return "";
  }
}

function writeStored(key: string, value: string) {
  try {
    localStorage.setItem(key, value);
  } catch {
    /* Preferences are optional when browser storage is unavailable. */
  }
}

function slug(value: string): string {
  return (
    value
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "")
      .slice(0, 40) || "event"
  );
}

const stamp = (value: string) =>
  `${date(value)} ${parseDate(value).toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit", timeZone: "Asia/Manila" })}`;

function windowText(form: FeedbackForm): string {
  if (form.opens_at && form.closes_at) return `${stamp(form.opens_at)} → ${stamp(form.closes_at)}`;
  if (form.opens_at) return `from ${stamp(form.opens_at)}`;
  if (form.closes_at) return `until ${stamp(form.closes_at)}`;
  return "no expiration";
}

const statusLabel: Record<FeedbackForm["status"], string> = {
  open: "Open",
  scheduled: "Not open yet",
  expired: "Expired",
  closed: "Closed",
};

export default function ShareFeedbackPanel({ onClose }: { onClose: () => void }) {
  const [baseUrl, setBaseUrl] = useState(
    () => readStored(BASE_KEY) || window.location.origin,
  );
  const [mode, setMode] = useState<"form" | "label">("form");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [label, setLabel] = useState(() => readStored(LABEL_KEY));
  const [copied, setCopied] = useState(false);
  const [copyError, setCopyError] = useState<unknown>();
  const [draft, setDraft] = useState({ title: "", description: "", opens_at: "", closes_at: "" });
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState<unknown>();
  const [toggleError, setToggleError] = useState<unknown>();
  const [busyId, setBusyId] = useState<number | null>(null);
  const qrWrap = useRef<HTMLDivElement>(null);
  const forms = useLoad<{ forms: FeedbackForm[] }>("/feedback-forms", { live: true });

  useEffect(() => writeStored(BASE_KEY, baseUrl), [baseUrl]);
  useEffect(() => writeStored(LABEL_KEY, label), [label]);

  let valid = false;
  let origin = "";
  try {
    const parsed = new URL(baseUrl.trim().replace(/\/+$/, ""));
    valid = parsed.protocol === "http:" || parsed.protocol === "https:";
    origin = parsed.toString();
  } catch {
    /* Invalid input is reported through the field message below. */
  }
  const selected = forms.data?.forms.find((form) => form.id === selectedId) ?? null;
  const trimmedLabel = mode === "label" ? label.trim().slice(0, 120) : "";
  const formLink = valid && selected ? `${origin}feedback/f/${selected.token}` : "";
  const labelLink = valid
    ? `${origin}feedback${trimmedLabel ? `?event=${encodeURIComponent(trimmedLabel)}` : ""}`
    : "";
  const link = mode === "form" ? formLink : labelLink;
  const posterTitle = mode === "form" ? selected?.title : trimmedLabel;

  useEffect(() => {
    if (!copied) return;
    const reset = setTimeout(() => setCopied(false), 2500);
    return () => clearTimeout(reset);
  }, [copied]);

  async function copyLink() {
    setCopyError(undefined);
    try {
      await navigator.clipboard.writeText(link);
      setCopied(true);
    } catch {
      try {
        const helper = document.createElement("textarea");
        helper.value = link;
        helper.setAttribute("readonly", "");
        helper.style.position = "fixed";
        helper.style.opacity = "0";
        document.body.appendChild(helper);
        helper.select();
        const fallback = document.execCommand("copy");
        document.body.removeChild(helper);
        if (!fallback) throw new Error("Copy was blocked by the browser.");
        setCopied(true);
      } catch (e) {
        setCopyError(e);
      }
    }
  }

  function downloadPng() {
    const canvas = qrWrap.current?.querySelector("canvas");
    if (!canvas) return;
    const anchor = document.createElement("a");
    anchor.href = (canvas as HTMLCanvasElement).toDataURL("image/png");
    anchor.download = `clsu-feedback-form-${slug(posterTitle || "form")}.png`;
    anchor.click();
  }

  function downloadSvg() {
    const svg = qrWrap.current?.querySelector("svg");
    if (!svg) return;
    const markup = new XMLSerializer().serializeToString(svg);
    const blob = new Blob([markup], { type: "image/svg+xml" });
    const anchor = document.createElement("a");
    anchor.href = URL.createObjectURL(blob);
    anchor.download = `clsu-feedback-form-${slug(posterTitle || "form")}.svg`;
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(anchor.href), 5000);
  }

  async function createForm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!draft.title.trim()) {
      setCreateError(new Error("Enter a form title."));
      return;
    }
    if (draft.opens_at && draft.closes_at && draft.closes_at < draft.opens_at) {
      setCreateError(new Error("The close time must not be before the open time."));
      return;
    }
    setCreating(true);
    setCreateError(undefined);
    try {
      const body: Record<string, string> = { title: draft.title.trim() };
      if (draft.description.trim()) body.description = draft.description.trim();
      if (draft.opens_at) body.opens_at = draft.opens_at;
      if (draft.closes_at) body.closes_at = draft.closes_at;
      const created = await api<{ id: number }>("/feedback-forms", {
        method: "POST",
        body: JSON.stringify(body),
      });
      setSelectedId(created.id);
      setDraft({ title: "", description: "", opens_at: "", closes_at: "" });
      forms.reload();
    } catch (e) {
      setCreateError(e);
    } finally {
      setCreating(false);
    }
  }

  async function toggleActive(form: FeedbackForm) {
    setToggleError(undefined);
    setBusyId(form.id);
    try {
      await api(`/feedback-forms/${form.id}`, {
        method: "PATCH",
        body: JSON.stringify({ is_active: !form.is_active }),
      });
      forms.reload();
    } catch (e) {
      setToggleError(e);
    } finally {
      setBusyId(null);
    }
  }

  return (
    <section
      id="feedback-share"
      className="feedback-share"
      aria-labelledby="feedback-share-title"
    >
      <header className="feedback-share-header">
        <div>
          <h2 id="feedback-share-title">Share feedback forms</h2>
          <p>
            Create an event form with its own link, QR code and collection
            window — or share a quick labelled link to the general form.
          </p>
        </div>
        <button
          type="button"
          className="icon-button"
          onClick={onClose}
          aria-label="Close share panel"
        >
          ✕
        </button>
      </header>
      <div className="feedback-share-grid">
        <div className="feedback-share-fields">
          <label className="feedback-share-field">
            Public form address
            <input
              value={baseUrl}
              onChange={(e) => setBaseUrl(e.target.value)}
              spellCheck={false}
              autoComplete="off"
            />
            <small>
              Prefilled with this portal. When sharing outside this machine,
              paste the hosted demo address instead.
            </small>
          </label>
          {!valid && (
            <p className="feedback-share-error" role="alert">
              Enter a full address starting with http:// or https://.
            </p>
          )}
          <div className="feedback-share-mode" role="tablist" aria-label="Link type">
            <button
              type="button"
              role="tab"
              aria-selected={mode === "form"}
              className={mode === "form" ? "active" : ""}
              onClick={() => setMode("form")}
            >
              Event form
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={mode === "label"}
              className={mode === "label" ? "active" : ""}
              onClick={() => setMode("label")}
            >
              Quick label link
            </button>
          </div>
          {mode === "label" ? (
            <label className="feedback-share-field">
              Event or collection label (optional)
              <input
                value={label}
                maxLength={120}
                placeholder="e.g. University Week 2026"
                onChange={(e) => setLabel(e.target.value)}
              />
              <small>
                Added to the general form link and recorded with every response.
              </small>
            </label>
          ) : (
            <>
              <ErrorNotice error={forms.error} />
              {!!forms.error && (
                <button className="button" onClick={forms.reload}>
                  Retry loading forms
                </button>
              )}
              {!!toggleError && <ErrorNotice error={toggleError} />}
              {forms.data && (
                <ul className="feedback-form-list">
                  {forms.data.forms.length ? (
                    forms.data.forms.map((form) => (
                      <li
                        key={form.id}
                        className={`feedback-form-row${selectedId === form.id ? " selected" : ""}`}
                      >
                        <div className="feedback-form-row-main">
                          <strong>{form.title}</strong>
                          <span className={`feedback-form-status is-${form.status}`}>
                            {statusLabel[form.status]}
                          </span>
                          <small>
                            {windowText(form)} · {form.responses}{" "}
                            {form.responses === 1 ? "response" : "responses"}
                          </small>
                        </div>
                        <div className="feedback-form-row-actions">
                          <button
                            type="button"
                            className="button"
                            onClick={() => setSelectedId(form.id)}
                          >
                            Share
                          </button>
                          <button
                            type="button"
                            className="button"
                            disabled={busyId === form.id}
                            onClick={() => void toggleActive(form)}
                          >
                            {form.is_active ? "Close" : "Reopen"}
                          </button>
                        </div>
                      </li>
                    ))
                  ) : (
                    <li className="feedback-form-empty">
                      No event forms yet. Create the first one below.
                    </li>
                  )}
                </ul>
              )}
              <form className="feedback-form-create" onSubmit={createForm}>
                <h3>New event form</h3>
                <ErrorNotice error={createError} />
                <label className="feedback-share-field">
                  Form title *
                  <input
                    value={draft.title}
                    maxLength={200}
                    placeholder="e.g. University Week 2026"
                    onChange={(e) => setDraft({ ...draft, title: e.target.value })}
                  />
                </label>
                <label className="feedback-share-field">
                  Description (optional)
                  <input
                    value={draft.description}
                    maxLength={1000}
                    placeholder="Shown at the top of the form"
                    onChange={(e) => setDraft({ ...draft, description: e.target.value })}
                  />
                </label>
                <div className="feedback-form-window-inputs">
                  <label className="feedback-share-field">
                    Opens (optional)
                    <input
                      type="datetime-local"
                      value={draft.opens_at}
                      onChange={(e) => setDraft({ ...draft, opens_at: e.target.value })}
                    />
                  </label>
                  <label className="feedback-share-field">
                    Closes (optional)
                    <input
                      type="datetime-local"
                      value={draft.closes_at}
                      onChange={(e) => setDraft({ ...draft, closes_at: e.target.value })}
                    />
                  </label>
                </div>
                <small>
                  Manila time. Leave both blank for a form that never expires;
                  the form automatically stops accepting responses after the
                  close time.
                </small>
                <button className="button primary" disabled={creating}>
                  {creating ? "Creating…" : "Create form"}
                </button>
              </form>
            </>
          )}
          <div className="feedback-share-link">
            <input
              readOnly
              value={link}
              aria-label="Generated feedback form link"
              onFocus={(e) => e.currentTarget.select()}
              placeholder={
                mode === "form"
                  ? "Select or create an event form to generate its link."
                  : "The link appears once the address is valid."
              }
            />
            <button
              type="button"
              className="button primary"
              disabled={!valid || !link}
              onClick={copyLink}
            >
              {copied ? "Copied ✓" : "Copy link"}
            </button>
          </div>
          {!!copyError && <ErrorNotice error={copyError} />}
          <p className="feedback-share-hint">
            The public form opens without an account. Responses stay private to
            authorized CLSU staff, and an entered job number still grants no
            private access.
          </p>
        </div>
        <figure className="feedback-share-qr">
          <div ref={qrWrap} className="feedback-share-qr-frame">
            {valid && link ? (
              <>
                <QRCodeSVG value={link} level="M" marginSize={2} />
                <QRCodeCanvas
                  className="feedback-share-qr-source"
                  value={link}
                  size={512}
                  level="M"
                  marginSize={2}
                />
              </>
            ) : (
              <p className="feedback-share-qr-waiting">
                {mode === "form"
                  ? "Select or create an event form to see its QR code."
                  : "The QR code appears once the address is valid."}
              </p>
            )}
          </div>
          <figcaption>
            Scan to open {mode === "form" ? selected?.title ?? "the form" : "the feedback form"}
          </figcaption>
          <div className="feedback-share-qr-actions">
            <button
              type="button"
              className="button"
              disabled={!valid || !link}
              onClick={downloadPng}
            >
              Download PNG
            </button>
            <button
              type="button"
              className="button"
              disabled={!valid || !link}
              onClick={downloadSvg}
            >
              Download SVG
            </button>
            <button
              type="button"
              className="button"
              disabled={!valid || !link}
              onClick={() => window.print()}
            >
              Print poster
            </button>
          </div>
        </figure>
      </div>
      {valid && link && (
        <div className="feedback-share-poster" aria-hidden="true">
          <Asset name="imgClsuLogo" alt="" className="feedback-share-poster-logo" />
          <p className="feedback-share-poster-eyebrow">
            CENTRAL LUZON STATE UNIVERSITY
          </p>
          <h1 className="feedback-share-poster-title">Share your feedback</h1>
          {posterTitle && (
            <p className="feedback-share-poster-label">
              Feedback collection · {posterTitle}
            </p>
          )}
          <div className="feedback-share-poster-qr">
            <QRCodeSVG value={link} level="M" marginSize={2} />
          </div>
          <p className="feedback-share-poster-url">{link}</p>
          <p className="feedback-share-poster-note">
            Scan the code or open the link to send a complaint, suggestion or
            compliment. No account needed.
          </p>
        </div>
      )}
    </section>
  );
}
