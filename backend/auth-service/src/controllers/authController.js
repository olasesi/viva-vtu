const bcrypt = require("bcryptjs");
const crypto = require("crypto");
const AppError = require("../utils/AppError");
const { generateCryptoToken, hashToken, prisma, redis } = require("../utils/tokenUtils");
const {
  generateAccessToken,
  generateRefreshToken,
  verifyRefreshToken,
  blacklistToken,
} = require("../utils/tokenUtils");
const { queueVerificationEmail, queueResetPasswordEmail } = require("../services/emailQueue");

const sanitizeUser = (user) => {
  const { password, emailVerificationToken, emailVerificationExpiry, passwordResetToken, passwordResetExpiry, ...safe } = user;
  return safe;
};

const register = async (req, res, next) => {
  try {
    const { email, password, firstName, lastName, phone } = req.body;

    const existingUser = await prisma.user.findUnique({ where: { email } });
    if (existingUser) {
      throw new AppError("An account with this email already exists", 409);
    }

    if (phone) {
      const existingPhone = await prisma.user.findUnique({ where: { phone } });
      if (existingPhone) {
        throw new AppError("An account with this phone number already exists", 409);
      }
    }

    const salt = await bcrypt.genSalt(12);
    const hashedPassword = await bcrypt.hash(password, salt);

    const verificationToken = generateCryptoToken();
    const verificationTokenHash = hashToken(verificationToken);
    const verificationExpiry = new Date(Date.now() + 24 * 60 * 60 * 1000);

    const user = await prisma.user.create({
      data: {
        email,
        password: hashedPassword,
        firstName,
        lastName,
        phone: phone || null,
        emailVerificationToken: verificationTokenHash,
        emailVerificationExpiry: verificationExpiry,
      },
    });

    const accessToken = generateAccessToken(user);
    const refreshToken = await generateRefreshToken(user);

    await queueVerificationEmail(
      { id: user.id, email: user.email, firstName: user.firstName, lastName: user.lastName },
      verificationToken
    );

    res.status(201).json({
      status: "success",
      message: "Registration successful. Please verify your email.",
      data: {
        user: sanitizeUser(user),
        accessToken,
        refreshToken,
      },
    });
  } catch (error) {
    next(error);
  }
};

const login = async (req, res, next) => {
  try {
    const { email, password } = req.body;

    const user = await prisma.user.findUnique({ where: { email } });
    if (!user) {
      throw new AppError("Invalid email or password", 401);
    }

    if (!user.isActive) {
      throw new AppError("Account has been deactivated. Please contact support.", 403);
    }

    const isPasswordValid = await bcrypt.compare(password, user.password);
    if (!isPasswordValid) {
      throw new AppError("Invalid email or password", 401);
    }

    const accessToken = generateAccessToken(user);
    const refreshToken = await generateRefreshToken(user);

    res.status(200).json({
      status: "success",
      message: "Login successful",
      data: {
        user: sanitizeUser(user),
        accessToken,
        refreshToken,
      },
    });
  } catch (error) {
    next(error);
  }
};

const logout = async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader.split(" ")[1];

    let tokenExp = 900;
    try {
      const decoded = JSON.parse(Buffer.from(token.split(".")[1], "base64").toString());
      const now = Math.floor(Date.now() / 1000);
      tokenExp = decoded.exp - now;
      if (tokenExp < 0) tokenExp = 0;
    } catch (_) {
      tokenExp = 900;
    }

    await blacklistToken(token, tokenExp);

    if (req.user) {
      await prisma.refreshToken.deleteMany({
        where: { userId: req.user.id },
      });
    }

    res.status(200).json({
      status: "success",
      message: "Logged out successfully",
    });
  } catch (error) {
    next(error);
  }
};

const refreshToken = async (req, res, next) => {
  try {
    const { refreshToken: token } = req.body;

    if (!token) {
      throw new AppError("Refresh token is required", 400);
    }

    const decoded = await verifyRefreshToken(token);

    await prisma.refreshToken.deleteMany({
      where: { token },
    });

    const user = await prisma.user.findUnique({
      where: { id: decoded.id },
    });

    if (!user) {
      throw new AppError("User not found", 401);
    }

    if (!user.isActive) {
      throw new AppError("Account has been deactivated", 403);
    }

    const newAccessToken = generateAccessToken(user);
    const newRefreshToken = await generateRefreshToken(user);

    res.status(200).json({
      status: "success",
      message: "Tokens refreshed successfully",
      data: {
        accessToken: newAccessToken,
        refreshToken: newRefreshToken,
      },
    });
  } catch (error) {
    if (error.name === "JsonWebTokenError" || error.name === "TokenExpiredError") {
      return next(new AppError("Invalid or expired refresh token", 401));
    }
    next(error);
  }
};

const verifyEmail = async (req, res, next) => {
  try {
    const { token } = req.body;

    if (!token) {
      throw new AppError("Verification token is required", 400);
    }

    const tokenHash = hashToken(token);

    const user = await prisma.user.findFirst({
      where: {
        emailVerificationToken: tokenHash,
      },
    });

    if (!user) {
      throw new AppError("Invalid verification token", 400);
    }

    if (user.isEmailVerified) {
      throw new AppError("Email is already verified", 400);
    }

    if (user.emailVerificationExpiry && new Date() > user.emailVerificationExpiry) {
      throw new AppError("Verification token has expired. Please request a new one.", 400);
    }

    await prisma.user.update({
      where: { id: user.id },
      data: {
        isEmailVerified: true,
        emailVerificationToken: null,
        emailVerificationExpiry: null,
      },
    });

    res.status(200).json({
      status: "success",
      message: "Email verified successfully",
    });
  } catch (error) {
    next(error);
  }
};

const forgotPassword = async (req, res, next) => {
  try {
    const { email } = req.body;

    const user = await prisma.user.findUnique({ where: { email } });

    if (!user) {
      res.status(200).json({
        status: "success",
        message: "If an account exists with this email, a password reset link has been sent.",
      });
      return;
    }

    const resetToken = generateCryptoToken();
    const resetTokenHash = hashToken(resetToken);
    const resetExpiry = new Date(Date.now() + 60 * 60 * 1000);

    await prisma.user.update({
      where: { id: user.id },
      data: {
        passwordResetToken: resetTokenHash,
        passwordResetExpiry: resetExpiry,
      },
    });

    await queueResetPasswordEmail(
      { id: user.id, email: user.email, firstName: user.firstName, lastName: user.lastName },
      resetToken
    );

    res.status(200).json({
      status: "success",
      message: "If an account exists with this email, a password reset link has been sent.",
    });
  } catch (error) {
    next(error);
  }
};

const resetPassword = async (req, res, next) => {
  try {
    const { token, password } = req.body;

    if (!token || !password) {
      throw new AppError("Token and new password are required", 400);
    }

    const tokenHash = hashToken(token);

    const user = await prisma.user.findFirst({
      where: {
        passwordResetToken: tokenHash,
      },
    });

    if (!user) {
      throw new AppError("Invalid or expired reset token", 400);
    }

    if (user.passwordResetExpiry && new Date() > user.passwordResetExpiry) {
      throw new AppError("Reset token has expired. Please request a new one.", 400);
    }

    const salt = await bcrypt.genSalt(12);
    const hashedPassword = await bcrypt.hash(password, salt);

    await prisma.user.update({
      where: { id: user.id },
      data: {
        password: hashedPassword,
        passwordResetToken: null,
        passwordResetExpiry: null,
      },
    });

    await prisma.refreshToken.deleteMany({
      where: { userId: user.id },
    });

    res.status(200).json({
      status: "success",
      message: "Password reset successfully. Please log in with your new password.",
    });
  } catch (error) {
    next(error);
  }
};

const getProfile = async (req, res, next) => {
  try {
    res.status(200).json({
      status: "success",
      data: {
        user: req.user,
      },
    });
  } catch (error) {
    next(error);
  }
};

const updateProfile = async (req, res, next) => {
  try {
    const { firstName, lastName, phone } = req.body;

    const updateData = {};
    if (firstName !== undefined) updateData.firstName = firstName;
    if (lastName !== undefined) updateData.lastName = lastName;
    if (phone !== undefined) updateData.phone = phone || null;

    if (updateData.phone) {
      const existingPhone = await prisma.user.findFirst({
        where: {
          phone: updateData.phone,
          id: { not: req.user.id },
        },
      });
      if (existingPhone) {
        throw new AppError("This phone number is already in use", 409);
      }
    }

    const updatedUser = await prisma.user.update({
      where: { id: req.user.id },
      data: updateData,
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

    res.status(200).json({
      status: "success",
      message: "Profile updated successfully",
      data: {
        user: updatedUser,
      },
    });
  } catch (error) {
    next(error);
  }
};

module.exports = {
  register,
  login,
  logout,
  refreshToken,
  verifyEmail,
  forgotPassword,
  resetPassword,
  getProfile,
  updateProfile,
};
