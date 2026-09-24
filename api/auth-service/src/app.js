require("dotenv").config();
require("./config/sentry");

const express = require("express");
const helmet = require("helmet");
const cors = require("cors");
const compression = require("compression");
const morgan = require("morgan");
const Sentry = require("@sentry/node");

const config = require("./config");
const logger = require("./config/logger");
const errorHandler = require("./middleware/errorHandler");
const prometheusMetrics = require("./middleware/prometheus");
const authRoutes = require("./routes/auth.routes");
const swaggerSpec = require("./swagger");
const swaggerUi = require("swagger-ui-express");
const { prisma, redis } = require("./utils/tokenUtils");
const { emailQueue } = require("./services/emailQueue");
const metrics = require("./config/metrics");

const app = express();

app.use(helmet());
app.use(cors({ origin: config.frontendUrl, credentials: true }));
app.use(compression());
app.use(express.json({ limit: "10mb" }));
app.use(express.urlencoded({ extended: true, limit: "10mb" }));
app.use(morgan("combined", { stream: { write: (msg) => logger.info(msg.trim()) } }));
app.use(prometheusMetrics);

app.get("/health", (_req, res) => {
  res.status(200).json({
    status: "ok",
    service: "auth-service",
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
  });
});

app.get("/readyz", async (_req, res) => {
  const checks = {
    database: { ok: false },
    redis: { ok: false },
  };

  try {
    await prisma.$queryRaw`SELECT 1`;
    checks.database.ok = true;
  } catch (err) {
    logger.error("Readiness check failed: database", { error: err.message });
  }

  try {
    checks.redis.ok = redis.status === "ready" || redis.status === "connect";
  } catch (err) {
    logger.error("Readiness check failed: redis", { error: err.message });
  }

  const healthy = checks.database.ok && checks.redis.ok;
  res.status(healthy ? 200 : 503).json({
    status: healthy ? "ready" : "not_ready",
    service: "auth-service",
    checks,
    timestamp: new Date().toISOString(),
  });
});

app.get("/metrics", async (_req, res) => {
  try {
    res.set("Content-Type", metrics.register.contentType);
    res.end(await metrics.register.metrics());
  } catch (err) {
    logger.error("Failed to expose metrics:", { error: err.message });
    res.status(500).end();
  }
});

app.use(
  "/api-docs",
  swaggerUi.serve,
  swaggerUi.setup(swaggerSpec, {
    explorer: true,
    customSiteTitle: "Viva VTU Auth Service",
  }),
);

app.use("/api/auth", authRoutes);

app.use((req, res, _next) => {
  res.status(404).json({
    status: "fail",
    message: `Route ${req.method} ${req.originalUrl} not found`,
  });
});

if (config.sentry.dsn && config.nodeEnv === "production") {
  Sentry.setupExpressErrorHandler(app);
}
app.use(errorHandler);

const gracefulShutdown = async (signal) => {
  logger.info(`${signal} received. Starting graceful shutdown...`);
  try {
    await prisma.$disconnect();
    logger.info("Prisma client disconnected");

    redis.disconnect();
    logger.info("Redis connection closed");

    await emailQueue.close();
    logger.info("Bull queue closed");
  } catch (err) {
    logger.error("Error during shutdown cleanup:", { error: err.message });
  }

  process.exit(0);
};

process.on("SIGTERM", () => gracefulShutdown("SIGTERM"));
process.on("SIGINT", () => gracefulShutdown("SIGINT"));

process.on("unhandledRejection", (reason) => {
  logger.error("Unhandled Rejection:", { reason: reason?.message || reason });
  Sentry.captureException(reason instanceof Error ? reason : new Error(String(reason)));
});

process.on("uncaughtException", (err) => {
  logger.error("Uncaught Exception:", { message: err.message, stack: err.stack });
  Sentry.captureException(err);
  gracefulShutdown("uncaughtException");
});

module.exports = app;
