import { createHash } from "node:crypto";
import { expect, test } from "@playwright/test";

const origin = "http://127.0.0.1:5174";
const headers = { Origin: origin };

test("admin can configure MCP, authorize scoped ChatGPT access, and revoke it", async ({ page }, info) => {
  test.setTimeout(120000);
  expect((await page.request.post("/api/login", { headers, data: {email:"admin@clsu.demo", password:"DemoPass!2026", role:"staff"} })).ok()).toBeTruthy();
  await page.goto("/app/assistant");
  await expect(page.getByRole("heading", {name:"Set up your assistant"})).toBeVisible();
  await page.getByRole("link", {name:"Open connection settings"}).click();
  await page.getByLabel("Enable the MCP server", {exact:true}).check();
  await page.getByLabel("Current password", {exact:true}).fill("DemoPass!2026");
  const saved = page.waitForResponse(r => r.url().endsWith("/api/assistant/settings") && r.request().method() === "PUT");
  await page.getByRole("button", {name:"Save connection settings"}).click();
  expect((await saved).status()).toBe(200);
  await expect(page.locator(".connection")).toHaveText("MCP ready", {timeout:15000});
  await expect(page.getByRole("button", {name:"Register ChatGPT client",exact:true})).toBeEnabled();
  await page.getByLabel("ChatGPT callback URL", {exact:true}).fill("https://chatgpt.com/connector/oauth/synthetic_browser_callback");
  await page.getByLabel("Current password for client setup", {exact:true}).fill("DemoPass!2026");
  await page.getByRole("button", {name:"Register ChatGPT client", exact:true}).click();
  const id = await page.getByLabel("OAuth Client ID", {exact:true}).inputValue();
  await expect(page.getByRole("heading", {name:"ChatGPT is registered"})).toBeVisible();
  await page.getByRole("button", {name:"Done", exact:true}).click();
  await expect(page.getByRole("heading", {name:"Your registered clients"})).toBeVisible();
  await page.setViewportSize({width:1440,height:1100});
  await page.screenshot({path:info.outputPath("assistant-settings-desktop.png"),fullPage:true,animations:"disabled"});
  await page.setViewportSize({width:390,height:844});
  await expect.poll(async()=>{const b=await page.locator(".sidebar").boundingBox();return b!.x+b!.width;}).toBeLessThanOrEqual(1);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:info.outputPath("assistant-settings-mobile.png"),fullPage:true,animations:"disabled"});
  const verifier = "a".repeat(64);
  const challenge = createHash("sha256").update(verifier).digest("base64url");
  const callback = "https://chatgpt.com/connector/oauth/synthetic_browser_callback";
  const resource = origin+"/api/mcp";
  // The callback is intercepted locally. This test never contacts ChatGPT.
  await page.route("https://chatgpt.com/**", route => route.fulfill({status:200,contentType:"text/html",body:"Synthetic OAuth callback"}));
  await page.goto("/oauth/authorize?"+new URLSearchParams({client_id:id,redirect_uri:callback,response_type:"code",scope:"mcp:use",state:"synthetic-state",resource,code_challenge_method:"S256",code_challenge:challenge}));
  await expect(page.getByRole("heading", {name:/Allow ChatGPT/})).toBeVisible();
  await page.getByRole("button", {name:"Allow read access"}).click();
  await page.waitForURL("https://chatgpt.com/**");
  const code = new URL(page.url()).searchParams.get("code");
  expect(code).toBeTruthy();
  const tokenReply = await page.request.post(origin+"/oauth/token", {data:{grant_type:"authorization_code",client_id:id,redirect_uri:callback,resource,code,code_verifier:verifier}});
  expect(tokenReply.status()).toBe(200);
  const token = (await tokenReply.json()).access_token;
  const rpcHeaders = {Authorization:`Bearer ${token}`,"MCP-Protocol-Version":"2025-11-25"};
  const tools = await page.request.post(origin+"/api/mcp",{headers:rpcHeaders,data:{jsonrpc:"2.0",id:1,method:"tools/list"}});
  expect(tools.status()).toBe(200);
  expect((await tools.json()).result.tools.map((t:{name:string})=>t.name)).toEqual(["search_requests","get_feedback_metrics"]);
  await page.goto(origin+"/app/assistant/settings");
  await page.getByRole("button", {name:"Revoke access",exact:true}).click();
  await page.getByLabel("Current password for client setup",{exact:true}).fill("DemoPass!2026");
  await page.getByRole("button",{name:"Confirm revoke access"}).click();
  await expect(page.getByText("Revoked",{exact:true})).toBeVisible();
  expect((await page.request.post(origin+"/api/mcp",{headers:rpcHeaders,data:{jsonrpc:"2.0",id:2,method:"tools/list"}})).status()).toBe(401);
});

test("requesters and personnel cannot access assistant setup", async ({ page }) => {
  for(const [email,role,home] of [["andrea@clsu.demo","requester","/app/requester"],["mark@clsu.demo","staff","/app/work"]]) {
    expect((await page.request.post("/api/login",{headers,data:{email,role,password:"DemoPass!2026"}})).ok()).toBeTruthy();
    expect((await page.request.get("/api/assistant/settings")).status()).toBe(403);
    await page.goto("/app/assistant/settings");
    await expect(page).toHaveURL(new RegExp(home+"$"));
    await expect(page.getByRole("link",{name:"Assistant settings",exact:true})).toHaveCount(0);
    await page.request.post("/api/logout",{headers});
  }
});
