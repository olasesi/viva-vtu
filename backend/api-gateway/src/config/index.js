const config = {
  port: parseInt(process.env.PORT, 10) || 3000,
  nodeEnv: process.env.NODE_ENV || "development",

  services: {
    auth: process.env.AUTH_SERVICE_URL || "http://auth:3001",
    billing: process.env.BILLING_SERVICE_URL || "http://billing:8000",
    analytics: process.env.ANALYTICS_SERVICE_URL || "http://analytics:8001",
  },

  jwt: {
    secret: process.env.JWT_SECRET || "change-me-in-production",
  },

  rateLimit: {
    windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS, 10) || 15 * 60 * 1000,
    max: parseInt(process.env.RATE_LIMIT_MAX, 10) || 100,
  },

  cors: {
    origin: process.env.CORS_ORIGIN || "*",
  },

  log: {
    level: process.env.LOG_LEVEL || "info",
    dir: process.env.LOG_DIR || "logs",
  },
};

module.exports = config;
