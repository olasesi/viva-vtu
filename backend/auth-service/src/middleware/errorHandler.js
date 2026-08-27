const config = require("../config");
const logger = require("../config/logger");

const errorHandler = (err, req, res, next) => {
  let statusCode = err.statusCode || 500;
  let message = err.message || "Internal Server Error";
  let errors = undefined;

  if (err.code === "P2002") {
    statusCode = 409;
    const target = err.meta?.target;
    if (Array.isArray(target)) {
      message = `Duplicate value for: ${target.join(", ")}`;
    } else if (typeof target === "string") {
      message = `A record with this ${target} already exists`;
    } else {
      message = "A record with this value already exists";
    }
  }

  if (err.code === "P2025") {
    statusCode = 404;
    message = "Record not found";
  }

  if (err.name === "JsonWebTokenError") {
    statusCode = 401;
    message = "Invalid token. Please log in again.";
  }

  if (err.name === "TokenExpiredError") {
    statusCode = 401;
    message = "Token has expired. Please log in again.";
  }

  if (err.name === "SyntaxError" && err.status === 400 && "body" in err) {
    statusCode = 400;
    message = "Malformed JSON in request body";
  }

  logger.error("Error:", {
    statusCode,
    message: err.message,
    stack: err.stack,
    path: req.originalUrl,
    method: req.method,
    code: err.code,
  });

  const response = {
    status: statusCode < 500 ? "fail" : "error",
    message,
  };

  if (errors) {
    response.errors = errors;
  }

  if (config.nodeEnv === "development") {
    response.stack = err.stack;
  }

  res.status(statusCode).json(response);
};

module.exports = errorHandler;
