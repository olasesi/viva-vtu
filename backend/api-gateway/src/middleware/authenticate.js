const jwt = require("jsonwebtoken");
const config = require("../config");
const { UnauthorizedError } = require("./errorHandler");

const authenticate = (req, res, next) => {
  const authHeader = req.headers.authorization;

  if (!authHeader || !authHeader.startsWith("Bearer ")) {
    return next(new UnauthorizedError("Missing or malformed Authorization header"));
  }

  const token = authHeader.split(" ")[1];

  try {
    const decoded = jwt.verify(token, config.jwt.secret);
    req.user = decoded;
    return next();
  } catch (err) {
    if (err.name === "TokenExpiredError") {
      return next(new UnauthorizedError("Token has expired"));
    }
    if (err.name === "JsonWebTokenError") {
      return next(new UnauthorizedError("Invalid token"));
    }
    return next(new UnauthorizedError("Token verification failed"));
  }
};

const optionalAuth = (req, res, next) => {
  const authHeader = req.headers.authorization;

  if (!authHeader || !authHeader.startsWith("Bearer ")) {
    return next();
  }

  const token = authHeader.split(" ")[1];

  try {
    const decoded = jwt.verify(token, config.jwt.secret);
    req.user = decoded;
  } catch (err) {
    // Silently ignore invalid tokens for optional auth
  }

  return next();
};

module.exports = { authenticate, optionalAuth };
