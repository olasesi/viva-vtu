const rateLimit = require("express-rate-limit");
const config = require("../config");
const logger = require("../config/logger");

const rateLimiter = rateLimit({
  windowMs: config.rateLimit.windowMs,
  max: config.rateLimit.max,
  standardHeaders: true,
  legacyHeaders: false,
  message: {
    status: "error",
    message: "Too many requests, please try again later.",
  },
  handler: (req, res, next, options) => {
    logger.warn("Rate limit exceeded", {
      ip: req.ip,
      path: req.originalUrl,
      method: req.method,
    });
    res.status(options.statusCode).json(options.message);
  },
  skip: (req) => req.path === "/health",
});

module.exports = { rateLimiter };
