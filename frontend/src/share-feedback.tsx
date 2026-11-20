import { useEffect, useRef, useState } from "react";
import { QRCodeCanvas, QRCodeSVG } from "qrcode.react";
import { Asset, ErrorNotice } from "./components";

const BASE_KEY = "clsu.feedbackShareBase";
const LABEL_KEY = "clsu.feedbackShareEvent";

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

export default function ShareFeedbackPanel({ onClose }: { onClose: () => void }) {
  const [baseUrl, setBaseUrl] = useState(
    () => readStored(BASE_KEY) || window.location.origin,
  );
  const [label, setLabel] = useState(() => readStored(LABEL_KEY));
  const [copied, setCopied] = useState(false);
  const [copyError, setCopyError] = useState<unknown>();
  const qrWrap = useRef<HTMLDivElement>(null);
  const posterWrap = useRef<HTMLDivElement>(null);

  useEffect(() => writeStored(BASE_KEY, baseUrl), [baseUrl]);
  useEffect(() => writeStored(LABEL_KEY, label), [label]);

  let valid = false;
  let link = "";
  let origin = "";
  try {
    const parsed = new URL(baseUrl.trim().replace(/\/+$/, ""));
    valid = parsed.protocol === "http:" || parsed.protocol === "https:";
    origin = parsed.toString();
  } catch {
    /* Invalid input is reported through the field message below. */
  }
  const trimmedLabel = label.trim().slice(0, 120);
  if (valid)
    link = `${origin}feedback${trimmedLabel ? `?event=${encodeURIComponent(trimmedLabel)}` : ""}`;

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
    anchor.download = `clsu-feedback-form-${slug(trimmedLabel)}.png`;
    anchor.click();
  }

  function downloadSvg() {
    const svg = qrWrap.current?.querySelector("svg");
    if (!svg) return;
    const markup = new XMLSerializer().serializeToString(svg);
    const blob = new Blob([markup], { type: "image/svg+xml" });
    const anchor = document.createElement("a");
    anchor.href = URL.createObjectURL(blob);
    anchor.download = `clsu-feedback-form-${slug(trimmedLabel)}.svg`;
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(anchor.href), 5000);
  }

  return (
    <section
      id="feedback-share"
      className="feedback-share"
      aria-labelledby="feedback-share-title"
    >
      <header className="feedback-share-header">
        <div>
          <h2 id="feedback-share-title">Share the feedback form</h2>
          <p>
            Generate a public link and QR code for events, posters and messages —
            like sharing a Google Form.
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
          <label className="feedback-share-field">
            Event or collection label (optional)
            <input
              value={label}
              maxLength={120}
              placeholder="e.g. University Week 2026"
              onChange={(e) => setLabel(e.target.value)}
            />
            <small>
              Added to the link and recorded with every response so staff can
              trace where feedback came from.
            </small>
          </label>
          <div className="feedback-share-link">
            <input
              readOnly
              value={link}
              aria-label="Generated feedback form link"
              onFocus={(e) => e.currentTarget.select()}
              placeholder="The link appears once the address is valid."
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
                The QR code appears once the address is valid.
              </p>
            )}
          </div>
          <figcaption>Scan to open the feedback form</figcaption>
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
        <div className="feedback-share-poster" aria-hidden="true" ref={posterWrap}>
          <Asset name="imgClsuLogo" alt="" className="feedback-share-poster-logo" />
          <p className="feedback-share-poster-eyebrow">
            CENTRAL LUZON STATE UNIVERSITY
          </p>
          <h1 className="feedback-share-poster-title">Share your feedback</h1>
          {trimmedLabel && (
            <p className="feedback-share-poster-label">
              Feedback collection · {trimmedLabel}
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
