const express = require("express");
const { createProxyMiddleware } = require("http-proxy-middleware");
const config = require("../config");
const { optionalAuth } = require("../middleware/authenticate");
const logger = require("../config/logger");

const createServiceProxy = (target, pathRewrite) => {
  return createProxyMiddleware({
    target,
    changeOrigin: true,
    pathRewrite,
    timeout: 30000,
    proxyTimeout: 30000,
    on: {
      proxyReq: (proxyReq, req) => {
        logger.info(`Proxying ${req.method} ${req.originalUrl} -> ${target}${proxyReq.path}`);
        if (req.user) {
          proxyReq.setHeader("X-User-Id", req.user.id || req.user.sub || "");
          proxyReq.setHeader("X-User-Email", req.user.email || "");
          proxyReq.setHeader("X-User-Role", req.user.role || "");
          proxyReq.setHeader("X-Forwarded-User", JSON.stringify(req.user));
        }
      },
      proxyRes: (proxyRes, req) => {
        logger.debug(`Response from ${target}${req.originalUrl}: ${proxyRes.statusCode}`);
      },
      error: (err, req, res) => {
        logger.error("Proxy error:", {
          message: err.message,
          target,
          url: req.originalUrl,
        });
        if (!res.headersSent) {
          res.status(502).json({
            status: "error",
            message: "Bad Gateway: Service temporarily unavailable",
          });
        }
      },
    },
  });
};

// The routing mount strips the leading path segment from req.url, but the
// Laravel billing service is mounted at /api and expects the FULL original
// path. Restore it so requests pass through untouched (e.g.
// /api/wallet/balance -> http://billing/api/wallet/balance).
const forwardOriginalPath = (path, req) => req.originalUrl;

// Legacy /api/billing/* clients: Laravel is mounted at /api, so the forwarded
// path must be /api + the stripped remainder (e.g. /api/billing/wallet/balance
// -> http://billing/api/wallet/balance).
const legacyBillingRewrite = (path) => `/api${path}`;

// Prefixes forwarded straight through to Laravel (the billing service).
const LARAVEL_PREFIXES = [
  "wallet",
  "purchase",
  "services",
  "transactions",
  "service-requests",
  "settings",
  "admin",
  "webhook",
];

// npm test / unit tests: build a router with custom service targets.
const createProxyRouter = (services = config.services) => {
  const router = express.Router();

  const authProxy = createServiceProxy(services.auth, { "^/api/auth": "" });
  const analyticsProxy = createServiceProxy(services.analytics, {
    "^/api/analytics": "",
  });
  const legacyBillingProxy = createServiceProxy(services.billing, legacyBillingRewrite);
  const laravelProxy = createServiceProxy(services.billing, forwardOriginalPath);

  router.use("/auth", optionalAuth, authProxy);
  router.use("/analytics", optionalAuth, analyticsProxy);

  // Legacy /billing prefix, kept for backwards compatibility.
  router.use("/billing", optionalAuth, legacyBillingProxy);

  // Direct passthrough to Laravel.
  LARAVEL_PREFIXES.forEach((prefix) => {
    router.use(`/${prefix}`, optionalAuth, laravelProxy);
  });

  return router;
};

const proxyRouter = createProxyRouter();

module.exports = proxyRouter;
module.exports.createProxyRouter = createProxyRouter;
