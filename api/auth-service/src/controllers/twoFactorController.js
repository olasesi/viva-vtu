const bcrypt = require("bcryptjs");
const jwt = require("jsonwebtoken");
const AppError = require("../utils/AppError");
const config = require("../config");
const { prisma, generateAccessToken, generateRefreshToken } = require("../utils/tokenUtils");
const {
  generateSecret,
  verifyCode,
  buildOtpAuthUrl,
  generateRecoveryCodes,
  hashRecoveryCode,
} = require("../utils/totp");
const { userLoginsTotal, refreshTokensIssuedTotal } = require("../config/metrics");

const sanitizeUser = (user) => {
  /* eslint-disable no-unused-vars */
  const {
    password,
    emailVerificationToken,
    emailVerificationExpiry,
    passwordResetToken,
    passwordResetExpiry,
    twoFactorSecret,
    twoFactorRecoveryCodes,
    ...safe
  } = user;
  /* eslint-enable no-unused-vars */
  return safe;
};

const status = async (req, res, next) => {
  try {
    const user = await prisma.user.findUnique({
      where: { id: req.user.id },
      select: { twoFactorEnabled: true, twoFactorConfirmedAt: true },
    });

    res.status(200).json({
      status: "success",
      data: {
        enabled: Boolean(user?.twoFactorEnabled),
        confirmedAt: user?.twoFactorConfirmedAt || null,
      },
    });
  } catch (error) {
    next(error);
  }
};

const setup = async (req, res, next) => {
  try {
    const user = await prisma.user.findUnique({ where: { id: req.user.id } });

    if (!user) {
      throw new AppError("User not found", 404);
    }

    if (user.twoFactorEnabled) {
      throw new AppError("Two-factor authentication is already enabled", 409);
    }

    const secret = generateSecret();

    await prisma.user.update({
      where: { id: user.id },
      data: { twoFactorSecret: secret, twoFactorConfirmedAt: null },
    });

    res.status(200).json({
      status: "success",
      message: "Scan the QR code or enter the secret in your authenticator app",
      data: {
        secret,
        otpauthUrl: buildOtpAuthUrl({ secret, email: user.email }),
      },
    });
  } catch (error) {
    next(error);
  }
};

const confirm = async (req, res, next) => {
  try {
    const { code } = req.body;

    const user = await prisma.user.findUnique({ where: { id: req.user.id } });

    if (!user) {
      throw new AppError("User not found", 404);
    }

    if (user.twoFactorEnabled) {
      throw new AppError("Two-factor authentication is already enabled", 409);
    }

    if (!user.twoFactorSecret) {
      throw new AppError("Start two-factor setup before confirming", 400);
    }

    if (!verifyCode(user.twoFactorSecret, code)) {
      throw new AppError("Invalid authentication code", 401);
    }

    const recoveryCodes = generateRecoveryCodes();
    const hashedCodes = recoveryCodes.map(hashRecoveryCode);

    await prisma.user.update({
      where: { id: user.id },
      data: {
        twoFactorEnabled: true,
        twoFactorConfirmedAt: new Date(),
        twoFactorRecoveryCodes: hashedCodes,
      },
    });

    res.status(200).json({
      status: "success",
      message: "Two-factor authentication enabled",
      data: { recoveryCodes },
    });
  } catch (error) {
    next(error);
  }
};

const disable = async (req, res, next) => {
  try {
    const { password, code } = req.body;

    const user = await prisma.user.findUnique({ where: { id: req.user.id } });

    if (!user) {
      throw new AppError("User not found", 404);
    }

    if (!user.twoFactorEnabled) {
      throw new AppError("Two-factor authentication is not enabled", 400);
    }

    const isPasswordValid = await bcrypt.compare(password, user.password);
    if (!isPasswordValid) {
      throw new AppError("Invalid password", 401);
    }

    const recovery = (user.twoFactorRecoveryCodes || []).includes(hashRecoveryCode(code));

    if (!verifyCode(user.twoFactorSecret, code) && !recovery) {
      throw new AppError("Invalid authentication code", 401);
    }

    await prisma.user.update({
      where: { id: user.id },
      data: {
        twoFactorEnabled: false,
        twoFactorSecret: null,
        twoFactorRecoveryCodes: null,
        twoFactorConfirmedAt: null,
      },
    });

    res.status(200).json({
      status: "success",
      message: "Two-factor authentication disabled",
    });
  } catch (error) {
    next(error);
  }
};

const regenerateRecoveryCodes = async (req, res, next) => {
  try {
    const { password, code } = req.body;

    const user = await prisma.user.findUnique({ where: { id: req.user.id } });

    if (!user) {
      throw new AppError("User not found", 404);
    }

    if (!user.twoFactorEnabled) {
      throw new AppError("Two-factor authentication is not enabled", 400);
    }

    const isPasswordValid = await bcrypt.compare(password, user.password);
    if (!isPasswordValid) {
      throw new AppError("Invalid password", 401);
    }

    const isRecovery = (user.twoFactorRecoveryCodes || []).includes(hashRecoveryCode(code));

    if (!verifyCode(user.twoFactorSecret, code) && !isRecovery) {
      throw new AppError("Invalid authentication code", 401);
    }

    const recoveryCodes = generateRecoveryCodes();
    const hashedCodes = recoveryCodes.map(hashRecoveryCode);

    await prisma.user.update({
      where: { id: user.id },
      data: { twoFactorRecoveryCodes: hashedCodes },
    });

    res.status(200).json({
      status: "success",
      message: "Recovery codes regenerated",
      data: { recoveryCodes },
    });
  } catch (error) {
    next(error);
  }
};

const issueChallengeToken = (user) =>
  jwt.sign({ id: user.id, purpose: "2fa" }, config.jwt.accessSecret, {
    expiresIn: "5m",
  });

const verifyLogin = async (req, res, next) => {
  try {
    const { challengeToken, code } = req.body;

    let decoded;

    try {
      decoded = jwt.verify(challengeToken, config.jwt.accessSecret);
    } catch (_) {
      throw new AppError("Invalid or expired challenge. Please log in again.", 401);
    }

    if (decoded.purpose !== "2fa") {
      throw new AppError("Invalid challenge token", 401);
    }

    const user = await prisma.user.findUnique({ where: { id: decoded.id } });

    if (!user) {
      throw new AppError("User not found. Please log in again.", 401);
    }

    if (!user.isActive) {
      throw new AppError("Account has been deactivated. Please contact support.", 403);
    }

    if (!user.twoFactorEnabled) {
      throw new AppError("Two-factor authentication is not enabled", 400);
    }

    const recoveryCodes = user.twoFactorRecoveryCodes || [];
    const hashed = hashRecoveryCode(code);
    const recoveryIndex = recoveryCodes.indexOf(hashed);
    const isRecovery = recoveryIndex !== -1;

    if (!verifyCode(user.twoFactorSecret, code) && !isRecovery) {
      throw new AppError("Invalid authentication code", 401);
    }

    if (isRecovery) {
      const remaining = recoveryCodes.filter((_, index) => index !== recoveryIndex);
      await prisma.user.update({
        where: { id: user.id },
        data: { twoFactorRecoveryCodes: remaining },
      });
    }

    const accessToken = generateAccessToken(user);
    const refreshToken = await generateRefreshToken(user);

    userLoginsTotal.inc();
    refreshTokensIssuedTotal.inc();

    res.status(200).json({
      status: "success",
      message: "Login successful",
      data: {
        user: sanitizeUser(user),
        accessToken,
        refreshToken,
        usedRecoveryCode: isRecovery,
      },
    });
  } catch (error) {
    next(error);
  }
};

module.exports = {
  status,
  setup,
  confirm,
  disable,
  regenerateRecoveryCodes,
  issueChallengeToken,
  verifyLogin,
};
