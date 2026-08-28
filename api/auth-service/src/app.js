require("dotenv").config();

const express = require("express");
const helmet = require("helmet");
const cors = require("cors");
const compression = require("compression");
const morgan = require("morgan");

const config = require("./config");
const logger = require("./config/logger");
const errorHandler = require("./middleware/errorHandler");
const authRoutes = require("./routes/auth.routes");
const { prisma, redis } = require("./utils/tokenUtils");
const { emailQueue } = require("./services/emailQueue");

const app = express();

app.use(helmet());
app.use(cors({ origin: config.frontendUrl, credentials: true }));
app.use(compression());
app.use(express.json({ limit: "10mb" }));
app.use(express.urlencoded({ extended: true, limit: "10mb" }));
app.use(morgan("combined", { stream: { write: (msg) => logger.info(msg.trim()) } }));

app.get("/health", (req, res) => {
  res.status(200).json({
    status: "ok",
    service: "auth-service",
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
  });
});

app.use("/api/auth", authRoutes);

app.use((req, res, next) => {
  res.status(404).json({
    status: "fail",
    message: `Route ${req.method} ${req.originalUrl} not found`,
  });
});

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
});

process.on("uncaughtException", (err) => {
  logger.error("Uncaught Exception:", { message: err.message, stack: err.stack });
  gracefulShutdown("uncaughtException");
});

module.exports = app;
