const express = require("express");
const { createProxyMiddleware } = require("http-proxy-middleware");
const config = require("../config");
const { optionalAuth } = require("../middleware/authenticate");
const logger = require("../config/logger");

const router = express.Router();

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

const authProxy = createServiceProxy(config.services.auth, {
  "^/api/auth": "",
});

const billingProxy = createServiceProxy(config.services.billing, {
  "^/api/billing": "",
});

const analyticsProxy = createServiceProxy(config.services.analytics, {
  "^/api/analytics": "",
});

router.use("/auth", optionalAuth, authProxy);
router.use("/billing", optionalAuth, billingProxy);
router.use("/analytics", optionalAuth, analyticsProxy);

module.exports = router;
