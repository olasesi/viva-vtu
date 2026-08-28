const app = require("./app");

const config = require("./config");
const logger = require("./config/logger");

const port = config.port;

const server = app.listen(port, () => {
  logger.info(`Gateway server listening on port ${port}`);
});

server.on("error", (err) => {
  if (err.code === "EADDRINUSE") {
    logger.error(`Port ${port} is already in use`);
  } else {
    logger.error("Server error:", { message: err.message });
  }
  process.exit(1);
});
