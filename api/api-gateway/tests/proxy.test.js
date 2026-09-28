const http = require("http");
const express = require("express");
const request = require("supertest");
const { createProxyRouter } = require("../src/routes/proxy");

const startStub = (name, calls) => {
  return new Promise((resolve) => {
    const server = http.createServer((req, res) => {
      let body = "";
      req.on("data", (chunk) => {
        body += chunk;
      });
      req.on("end", () => {
        calls.push({
          service: name,
          method: req.method,
          url: req.url,
          headers: req.headers,
          body,
        });
        res.setHeader("Content-Type", "application/json");
        res.end(JSON.stringify({ service: name, url: req.url }));
      });
    });
    server.listen(0, "127.0.0.1", () => resolve(server));
  });
};

const serviceUrl = (server) => `http://127.0.0.1:${server.address().port}`;

describe("API Gateway proxy routing", () => {
  let authServer;
  let billingServer;
  let analyticsServer;
  let authCalls;
  let billingCalls;
  let analyticsCalls;
  let app;

  beforeAll(async () => {
    authCalls = [];
    billingCalls = [];
    analyticsCalls = [];

    authServer = await startStub("auth", authCalls);
    billingServer = await startStub("billing", billingCalls);
    analyticsServer = await startStub("analytics", analyticsCalls);

    const router = createProxyRouter({
      auth: serviceUrl(authServer),
      billing: serviceUrl(billingServer),
      analytics: serviceUrl(analyticsServer),
    });

    app = express();
    app.use("/api", router);
  });

  afterAll(() => {
    authServer.close();
    billingServer.close();
    analyticsServer.close();
  });

  it("forwards /api/auth/* to the auth service", async () => {
    const res = await request(app).post("/api/auth/login").send({ email: "a@b.c" });
    expect(res.status).toBe(200);
    expect(res.body).toEqual({ service: "auth", url: "/login" });
    expect(authCalls[0].method).toBe("POST");
    expect(authCalls[0].body).toContain("a@b.c");
  });

  it("forwards /api/analytics/* to the analytics service", async () => {
    const res = await request(app).get("/api/analytics/dashboard");
    expect(res.status).toBe(200);
    expect(res.body).toEqual({ service: "analytics", url: "/dashboard" });
  });

  it("forwards /api/billing/* to Laravel with the /api prefix restored", async () => {
    const res = await request(app).get("/api/billing/wallet/balance");
    expect(res.status).toBe(200);
    expect(res.body).toEqual({
      service: "billing",
      url: "/api/wallet/balance",
    });
  });

  it.each([
    ["/api/wallet/balance", "GET"],
    ["/api/services/data?provider=vtpass", "GET"],
    ["/api/transactions", "GET"],
    ["/api/service-requests", "GET"],
    ["/api/settings/public", "GET"],
    ["/api/admin/users", "GET"],
    ["/api/purchase/data", "POST"],
  ])("forwards %s to Laravel unchanged", async (path, method) => {
    const res =
      method === "POST"
        ? await request(app).post(path).send({ amount: 100, phone: "08123456789" })
        : await request(app).get(path);
    expect(res.status).toBe(200);
    expect(res.body).toMatchObject({ service: "billing" });
    expect(res.body.url).toBe(path);
  });

  it("forwards /api/webhook/* to Laravel without authentication", async () => {
    const res = await request(app).post("/api/webhook/paystack").send({ event: "charge.success" });
    expect(res.status).toBe(200);
    expect(res.body).toEqual({
      service: "billing",
      url: "/api/webhook/paystack",
    });
    expect(billingCalls[billingCalls.length - 1].body).toContain("charge.success");
  });

  it("forwards x-user headers once an optional token is validated", async () => {
    const jsonwebtoken = require("jsonwebtoken");
    const config = require("../src/config");
    const token = jsonwebtoken.sign(
      { id: 42, email: "agent@viva.vtu", role: "AGENT" },
      config.jwt.secret,
    );
    const res = await request(app)
      .get("/api/wallet/balance")
      .set("Authorization", `Bearer ${token}`)
      .send();
    expect(res.status).toBe(200);
    const forwardedBillingCall = billingCalls[billingCalls.length - 1];
    expect(forwardedBillingCall.headers["x-user-id"]).toBe("42");
    expect(forwardedBillingCall.headers["x-user-email"]).toBe("agent@viva.vtu");
    expect(forwardedBillingCall.headers["x-user-role"]).toBe("AGENT");
  });

  it("returns 502 when the target service is unreachable", async () => {
    const deadRouter = createProxyRouter({
      auth: serviceUrl(authServer),
      billing: "http://127.0.0.1:1",
      analytics: serviceUrl(analyticsServer),
    });
    const deadApp = express();
    deadApp.use("/api", deadRouter);
    const res = await request(deadApp).get("/api/wallet/balance");
    expect(res.status).toBe(502);
    expect(res.body.status).toBe("error");
  });
});
