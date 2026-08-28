const jwt = require("jsonwebtoken");
const crypto = require("crypto");
const config = require("../config");
const { PrismaClient } = require("@prisma/client");
const Redis = require("ioredis");

const prisma = new PrismaClient();

const redis = new Redis({
  host: config.redis.host,
  port: config.redis.port,
  password: config.redis.password,
  retryStrategy: (times) => Math.min(times * 50, 2000),
});

redis.on("error", (err) => {
  console.error("Redis connection error in tokenUtils:", err.message);
});

const generateAccessToken = (user) => {
  const payload = {
    id: user.id,
    email: user.email,
    role: user.role,
  };

  return jwt.sign(payload, config.jwt.accessSecret, {
    expiresIn: config.jwt.accessExpiry,
  });
};

const generateRefreshToken = async (user) => {
  const payload = {
    id: user.id,
    type: "refresh",
  };

  const token = jwt.sign(payload, config.jwt.refreshSecret, {
    expiresIn: config.jwt.refreshExpiry,
  });

  const decoded = jwt.decode(token);
  const expiresAt = new Date(decoded.exp * 1000);

  await prisma.refreshToken.create({
    data: {
      token,
      userId: user.id,
      expiresAt,
    },
  });

  return token;
};

const verifyAccessToken = (token) => {
  return jwt.verify(token, config.jwt.accessSecret);
};

const verifyRefreshToken = async (token) => {
  const decoded = jwt.verify(token, config.jwt.refreshSecret);

  const storedToken = await prisma.refreshToken.findUnique({
    where: { token },
  });

  if (!storedToken) {
    throw new Error("Refresh token not found in database");
  }

  if (new Date() > storedToken.expiresAt) {
    await prisma.refreshToken.delete({ where: { id: storedToken.id } });
    throw new Error("Refresh token has expired");
  }

  return decoded;
};

const blacklistToken = async (token, expiry) => {
  const ttl = expiry || 900;
  await redis.setex(`bl_${token}`, ttl, "1");
};

const isTokenBlacklisted = async (token) => {
  const result = await redis.get(`bl_${token}`);
  return result === "1";
};

const generateCryptoToken = () => {
  return crypto.randomBytes(32).toString("hex");
};

const hashToken = (token) => {
  return crypto.createHash("sha256").update(token).digest("hex");
};

module.exports = {
  generateAccessToken,
  generateRefreshToken,
  verifyAccessToken,
  verifyRefreshToken,
  blacklistToken,
  isTokenBlacklisted,
  generateCryptoToken,
  hashToken,
  prisma,
  redis,
};
