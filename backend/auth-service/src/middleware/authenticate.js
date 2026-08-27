const { verifyAccessToken, isTokenBlacklisted, prisma } = require("../utils/tokenUtils");
const AppError = require("../utils/AppError");

const authenticate = async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization;

    if (!authHeader || !authHeader.startsWith("Bearer ")) {
      throw new AppError("Authentication required. Please provide a valid token.", 401);
    }

    const token = authHeader.split(" ")[1];

    if (!token) {
      throw new AppError("Authentication required. Please provide a valid token.", 401);
    }

    const blacklisted = await isTokenBlacklisted(token);
    if (blacklisted) {
      throw new AppError("Token has been revoked. Please log in again.", 401);
    }

    const decoded = verifyAccessToken(token);

    const user = await prisma.user.findUnique({
      where: { id: decoded.id },
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true,
        phone: true,
        isEmailVerified: true,
        isActive: true,
        role: true,
        createdAt: true,
        updatedAt: true,
      },
    });

    if (!user) {
      throw new AppError("User not found. Please log in again.", 401);
    }

    if (!user.isActive) {
      throw new AppError("Account has been deactivated. Please contact support.", 403);
    }

    req.user = user;
    next();
  } catch (error) {
    if (error instanceof AppError) {
      return next(error);
    }

    if (error.name === "JsonWebTokenError") {
      return next(new AppError("Invalid token. Please log in again.", 401));
    }

    if (error.name === "TokenExpiredError") {
      return next(new AppError("Token has expired. Please log in again.", 401));
    }

    next(error);
  }
};

module.exports = authenticate;
