import { useEffect, useRef, useState, type FormEvent } from "react";
import { Link, Navigate, useLocation } from "react-router-dom";
import { useAuth } from "./App";
import { api, date } from "./api";
import { ErrorNotice, Field, Loading, Panel } from "./components";
import { useLoad } from "./useLoad";
import "./assistant.css";

type Client = { id: string; name: string; redirect_uri: string; tools: string[]; revoked: boolean };
type Setup = {
  record_version: number; editable: boolean; mcp_enabled: boolean; endpoint: string; remote_ready: boolean;
  assistant_available: boolean; assistant_enabled: boolean; key_configured: boolean; model: string; provider_checked_at: string | null;
  last_external_call_at: string | null; tools: string[]; clients: Client[];
};
const toolLabels: Record<string, string> = {
  search_requests: "Search service requests", get_request_details: "Read request details and history",
  get_personnel_workload: "View personnel workload", get_feedback_metrics: "Read feedback metrics", get_report_job: "Check existing report jobs",
};

export function AssistantConnection() {
  const { data } = useLoad<Setup>("/assistant/settings", { live: true });
  return <Link className="connection" to="/app/assistant/settings"><i />{data ? data.mcp_enabled ? "MCP ready" : "MCP disabled" : "MCP setup"}</Link>;
}

export default function AssistantWorkspace() {
  const { user } = useAuth();
  const location = useLocation();
  if (user?.role !== "admin") return <Navigate to="/app" replace />;
  return <AssistantContent settings={location.pathname.endsWith("/settings")} />;
}

function AssistantContent({ settings }: { settings: boolean }) {
  const loaded = useLoad<Setup>("/assistant/settings");
  return <div className="assistant-workspace">
    <div className="page-title"><div><h1>MCP Assistant</h1><p>Ask about your MISO records and manage AI connections.</p></div></div>
    <nav className="assistant-tabs" aria-label="Assistant navigation">
      <Link to="/app/assistant" aria-current={!settings ? "page" : undefined}>Assistant</Link>
      <Link to="/app/assistant/settings" aria-current={settings ? "page" : undefined}>Connection settings</Link>
    </nav>
    <ErrorNotice error={loaded.error} />
    {!!loaded.error && <button className="button" onClick={loaded.reload}>Retry loading settings</button>}
    {loaded.loading && <Loading />}
    {!loaded.loading && loaded.data && (settings ? <ConnectionSettings key={loaded.data.record_version} setup={loaded.data} reload={loaded.reload} /> : <AssistantChat setup={loaded.data} />)}
  </div>;
}

function ConnectionSettings({ setup, reload }: { setup: Setup; reload: () => void }) {
  const [mcpEnabled, setMcpEnabled] = useState(setup.mcp_enabled);
  const [assistantEnabled, setAssistantEnabled] = useState(setup.assistant_enabled);
  const [model, setModel] = useState(setup.model);
  const [key, setKey] = useState("");
  const [removeKey, setRemoveKey] = useState(false);
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  const [notice, setNotice] = useState("");
  async function save(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError(undefined); setNotice("");
    try {
      await api("/assistant/settings", { method: "PUT", body: JSON.stringify({ expected_version: setup.record_version, password, mcp_enabled: mcpEnabled, assistant_enabled: assistantEnabled, model, ...(key ? { api_key: key } : {}), remove_key: removeKey }) });
      setKey(""); setPassword(""); reload();
    } catch (e) { setError(e); } finally { setBusy(false); }
  }
  async function testModel() {
    setBusy(true); setError(undefined); setNotice("");
    try { const result = await api<{message: string}>("/assistant/test", { method: "POST", body: JSON.stringify({password}) }); setPassword(""); setNotice(result.message); }
    catch (e) { setError(e); } finally { setBusy(false); }
  }
  return <>
    {!setup.editable && <div className="notice" role="status">Integration changes are locked by the hosting operator for this environment. Ask the operator to allow admin integration setup.</div>}
    <form className="assistant-settings-form" onSubmit={save}>
      <Panel title="Portal assistant" subtitle="Use an OpenAI model inside your admin workspace.">
        <div className="panel-body assistant-form-body">
          {!setup.assistant_available && <p className="notice">Embedded model execution is awaiting approval for provider data sharing. You can save a key and model now; no messages or records will be sent.</p>}
          <label className="assistant-check"><input type="checkbox" checked={assistantEnabled} onChange={e => setAssistantEnabled(e.target.checked)} disabled={!setup.editable || !setup.assistant_available || busy} />Enable the portal assistant</label>
          <p>Chat and authorized record results are sent to the model provider when you ask a question. Use synthetic records until your organization approves sharing campus data.</p>
          <div className="assistant-form-grid">
            <Field label="OpenAI API key" hint={setup.key_configured ? "A key is saved. Leave blank to keep it; saved keys are never displayed." : "Stored encrypted on the server. This is an API key, not your ChatGPT password."}>
              <input type="password" value={key} onChange={e => {setKey(e.target.value); setRemoveKey(false);}} autoComplete="new-password" maxLength={1024} disabled={!setup.editable || busy} />
            </Field>
            <Field label="Model ID" hint="Enter a Responses API model available to your OpenAI API project."><input value={model} onChange={e => setModel(e.target.value)} maxLength={100} placeholder="Your enabled model ID" disabled={!setup.editable || busy} /></Field>
          </div>
          {setup.key_configured && <label className="assistant-check"><input type="checkbox" checked={removeKey} onChange={e => {setRemoveKey(e.target.checked); if(e.target.checked) {setKey(""); setAssistantEnabled(false);}}} disabled={!setup.editable || busy} />Remove the saved API key</label>}
          <small>{setup.provider_checked_at ? `Last successful model check: ${date(setup.provider_checked_at)}` : "The saved model connection has not been verified."}</small>
        </div>
      </Panel>
      <Panel title="External ChatGPT connection" subtitle="Let ChatGPT read records using this system’s scoped MCP tools.">
        <div className="panel-body assistant-form-body">
          <label className="assistant-check"><input type="checkbox" checked={mcpEnabled} onChange={e => setMcpEnabled(e.target.checked)} disabled={!setup.editable || busy} />Enable the MCP server</label>
          <p>Each registered client has its own read permissions. Turning off MCP blocks external tool calls. The portal assistant is configured separately.</p>
          <Field label="MCP server URL" hint="Use this address when adding your custom MCP server in ChatGPT."><input readOnly value={setup.endpoint} /></Field>
          <p className="assistant-url-note">{setup.remote_ready ? "An HTTPS address is configured. Verify that ChatGPT can reach it before connecting." : "This local address is for this PC. ChatGPT web needs a reachable HTTPS deployment or a supported secure tunnel."}</p>
          <small>{setup.last_external_call_at ? `Last successful external tool call: ${date(setup.last_external_call_at)}` : "No successful external tool calls have been recorded for your account."}</small>
        </div>
      </Panel>
      <div className="assistant-save-area">
        <Field label="Current password" hint="Confirm your admin password to save changes or test the saved model."><input type="password" required value={password} onChange={e => setPassword(e.target.value)} autoComplete="current-password" disabled={!setup.editable || busy} /></Field>
        <ErrorNotice error={error} />
        {notice && <p className="notice" role="status">{notice}</p>}
        <div className="actions"><button className="button primary" disabled={!setup.editable || busy}>{busy ? "Working…" : "Save connection settings"}</button><button type="button" className="button" disabled={!setup.editable || !setup.assistant_available || !setup.key_configured || !password || busy} onClick={testModel}>Test saved model</button><button type="button" className="button" onClick={reload} disabled={busy}>Reload saved settings</button></div>
        <small>Model testing sends a short test message without campus records and may use API credits.</small>
      </div>
    </form>
    <ChatGPTClients setup={setup} reload={reload} />
  </>;
}

function ChatGPTClients({ setup, reload }: { setup: Setup; reload: () => void }) {
  const [tools, setTools] = useState<string[]>(["search_requests", "get_feedback_metrics"]);
  const [name, setName] = useState("ChatGPT");
  const [redirect, setRedirect] = useState("");
  const [password, setPassword] = useState("");
  const [revoke, setRevoke] = useState<string>();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  const [created, setCreated] = useState<Client>();
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError(undefined);
    try {
      if (revoke) { await api(`/assistant/clients/${revoke}`, { method: "DELETE", body: JSON.stringify({password}) }); setRevoke(undefined); reload(); }
      else { const client = await api<Client>("/assistant/clients", {method: "POST", body: JSON.stringify({password, name, redirect_uri: redirect, tools})}); setCreated(client); setPassword(""); }
    } catch (e) { setError(e); } finally { setBusy(false); }
  }
  return <Panel title="ChatGPT clients" subtitle="Register and revoke access for your own administrator account.">
    <div className="panel-body assistant-form-body">
      <ol className="assistant-steps"><li>Save MCP as enabled, then open <a href="https://chatgpt.com" target="_blank" rel="noreferrer">ChatGPT</a> and add a custom MCP server using the server URL above.</li><li>Choose OAuth. Copy the exact callback URL displayed by ChatGPT into the form below.</li><li>Register the client, copy its Client ID into ChatGPT and leave the client secret empty. Complete the CLSU sign-in and read-access consent.</li></ol>
      {!setup.mcp_enabled && <p className="notice">Enable and save the MCP server before registering a client.</p>}
      {created ? <div className="assistant-created" role="status"><h3>{created.name} is registered</h3><Field label="OAuth Client ID" hint="Copy this ID to ChatGPT’s OAuth Client ID field. No client secret is required."><input value={created.id} readOnly onFocus={e => e.currentTarget.select()} /></Field><p>Registration does not confirm a ChatGPT connection. Finish sign-in and try an authorized read tool in ChatGPT.</p><button type="button" className="button" onClick={reload}>Done</button></div> : <form onSubmit={submit} className="assistant-client-form">
        {!revoke ? <><div className="assistant-form-grid"><Field label="Client name"><input required value={name} maxLength={100} onChange={e => setName(e.target.value)} disabled={busy} /></Field><Field label="ChatGPT callback URL" hint="Paste the exact https://chatgpt.com/connector/… callback shown for your connection."><input required type="url" value={redirect} maxLength={1024} onChange={e => setRedirect(e.target.value)} disabled={busy} /></Field></div>
          <fieldset><legend>Allowed read tools</legend><div className="assistant-tool-options">{setup.tools.map(tool => <label className="assistant-check" key={tool}><input type="checkbox" checked={tools.includes(tool)} onChange={e => setTools(previous => e.target.checked ? [...previous, tool] : previous.filter(t => t !== tool))} disabled={busy} />{toolLabels[tool]}</label>)}</div></fieldset></> : <p>Revoke this client and its access and refresh tokens? It will need a new registration to connect again.</p>}
        <Field label="Current password for client setup"><input required type="password" value={password} onChange={e => setPassword(e.target.value)} autoComplete="current-password" disabled={busy} /></Field>
        <ErrorNotice error={error} />
        <div className="actions"><button className="button primary" disabled={!setup.editable || (!revoke && (!setup.mcp_enabled || !tools.length)) || busy}>{busy ? "Working…" : revoke ? "Confirm revoke access" : "Register ChatGPT client"}</button>{revoke && <button type="button" className="button" onClick={() => {setRevoke(undefined); setPassword("");}}>Cancel</button>}</div>
      </form>}
      {setup.clients.length > 0 && <div className="assistant-clients"><h3>Your registered clients</h3>{setup.clients.map(client => <div key={client.id} className="assistant-client-row"><div><strong>{client.name}</strong><small>{client.revoked ? "Revoked" : "Registered · awaiting or ready for use"}</small><code>{client.id}</code><p>{client.tools.map(tool => toolLabels[tool]).join(" · ")}</p></div><button type="button" className="button" disabled={client.revoked || busy || !setup.editable} onClick={() => {setRevoke(client.id); setPassword(""); setError(undefined);}}>Revoke access</button></div>)}</div>}
    </div>
  </Panel>;
}

function AssistantChat({ setup }: { setup: Setup }) {
  const [messages, setMessages] = useState<{role: "user" | "assistant"; content: string; tools?: string[]}[]>([]);
  const [question, setQuestion] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  const controller = useRef<AbortController | undefined>(undefined);
  const end = useRef<HTMLDivElement>(null);
  useEffect(() => () => controller.current?.abort(), []);
  useEffect(() => { if(messages.length) end.current?.scrollIntoView({block:"nearest"}); }, [messages]);
  const ready = setup.assistant_available && setup.editable && setup.assistant_enabled && setup.key_configured;
  async function send(event: FormEvent) {
    event.preventDefault(); if(!question.trim() || busy) return;
    setBusy(true); setError(undefined); controller.current = new AbortController();
    const input = [...messages.map(({role, content}) => ({role, content})), {role: "user" as const, content: question.trim()}].slice(-11);
    try {
      const reply = await api<{reply:string; tools_used:string[]}>("/assistant/chat", {method:"POST", body:JSON.stringify({messages:input}), signal:controller.current.signal});
      setMessages([...input, {role:"assistant", content:reply.reply, tools:reply.tools_used}]); setQuestion("");
    } catch (e) { if(!controller.current.signal.aborted) setError(e); } finally {setBusy(false);}
  }
  return <Panel title="Your MISO assistant" action={<Link className="button" to="/app/assistant/settings">Connection settings</Link>}>
    <div className="panel-body assistant-chat">
      {!ready ? <div className="assistant-setup-prompt"><h2>Set up your assistant</h2><p>Add a server-side API key and model, then enable the portal assistant. You can also register an external ChatGPT connection in settings.</p><Link className="button primary" to="/app/assistant/settings">Open connection settings</Link></div> : <p className="notice">Reads your authorized MISO records. Review AI answers before acting. This assistant cannot change requests or complete work.</p>}
      {ready && messages.length === 0 && <div className="assistant-suggestions"><h3>Start with a question</h3>{["Show my office’s pending service requests.", "Summarize feedback metrics for the last 30 days.", "What is the current personnel workload?"].map(prompt => <button type="button" className="button" key={prompt} onClick={() => setQuestion(prompt)}>{prompt}</button>)}</div>}
      <div className="assistant-transcript" role="log" aria-label="Assistant conversation" aria-live="polite">{messages.map((message, i) => <div className={`assistant-message ${message.role}`} key={i}><strong>{message.role === "user" ? "You" : "MISO assistant"}</strong><p>{message.content}</p>{message.tools?.length ? <small>Read tools used: {message.tools.map(tool => toolLabels[tool]).join(" · ")}</small> : null}</div>)}<div ref={end} /></div>
      <ErrorNotice error={error} />
      {busy && <p role="status">Checking your question and authorized records…</p>}
      <form onSubmit={send}><Field label="Ask the assistant" hint="This conversation stays in this tab and is cleared when you leave the page."><textarea rows={3} maxLength={4000} value={question} onChange={e => setQuestion(e.target.value)} disabled={!ready || busy} required /></Field><div className="actions"><button className="button primary" disabled={!ready || busy || !question.trim()}>{busy ? "Waiting for answer…" : "Send question"}</button><button type="button" className="button" disabled={busy || !messages.length} onClick={() => {setMessages([]); setError(undefined);}}>Clear conversation</button></div></form>
    </div>
  </Panel>;
}
