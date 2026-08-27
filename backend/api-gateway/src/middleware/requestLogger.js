const logger = require("../config/logger");

const requestLogger = (req, res, next) => {
  const start = Date.now();

  res.on("finish", () => {
    const duration = Date.now() - start;
    const logData = {
      method: req.method,
      url: req.originalUrl,
      status: res.statusCode,
      duration: `${duration}ms`,
      ip: req.ip,
      userAgent: req.get("user-agent"),
    };

    if (res.statusCode >= 500) {
      logger.error("Request completed", logData);
    } else if (res.statusCode >= 400) {
      logger.warn("Request completed", logData);
    } else {
      logger.info("Request completed", logData);
    }
  });

  next();
};

module.exports = { requestLogger };
