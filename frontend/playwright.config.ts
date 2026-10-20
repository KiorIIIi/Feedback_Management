import { defineConfig } from "@playwright/test";
export default defineConfig({
  testDir: "./tests",
  timeout: 60000,
  workers: 1,
  webServer: {
    command: "node scripts/e2e-server.mjs",
    url: "http://127.0.0.1:5174",
    timeout: 120000,
    reuseExistingServer: false,
  },
  use: {
    baseURL: "http://127.0.0.1:5174",
    channel: "chrome",
    headless: true,
    screenshot: "only-on-failure",
  },
  reporter: "list",
});
