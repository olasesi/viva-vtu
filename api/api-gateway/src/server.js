const app = require("./app");

const config = require("./config");
const logger = require("./config/logger");

const { port, nodeEnv } = config;

const server = app.listen(port, () => {
  logger.info(`API Gateway running on port ${port} [${nodeEnv}]`);
});

server.on("error", (err) => {
  if (err.code === "EADDRINUSE") {
    logger.error(`Port ${port} is already in use`);
  } else {
    logger.error("Server error:", { message: err.message });
  }
  process.exit(1);
});

const gracefulShutdown = (signal) => {
  logger.info(`${signal} received. Starting graceful shutdown...`);
  server.close(() => {
    logger.info("HTTP server closed. Exiting process.");
    process.exit(0);
  });
  setTimeout(() => {
    logger.error("Forced shutdown after timeout.");
    process.exit(1);
  }, 10000);
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
