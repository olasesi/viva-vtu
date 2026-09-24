const jwt = require("jsonwebtoken");
const crypto = require("crypto");

jest.mock("@prisma/client", () => ({
  PrismaClient: jest.fn().mockImplementation(() => ({
    refreshToken: {
      create: jest.fn().mockResolvedValue({}),
      findUnique: jest.fn(),
      delete: jest.fn().mockResolvedValue({}),
      deleteMany: jest.fn().mockResolvedValue({ count: 1 }),
    },
  })),
}));

jest.mock("ioredis", () => {
  const MockRedis = require("ioredis-mock");
  return MockRedis;
});

jest.mock("../../src/config", () => ({
  jwt: {
    accessSecret: "unit-access-secret",
    accessExpiry: "15m",
    refreshSecret: "unit-refresh-secret",
    refreshExpiry: "7d",
  },
  redis: { host: "127.0.0.1", port: 6379, password: undefined },
}));

const tokenUtils = require("../../src/utils/tokenUtils");

const user = {
  id: "11111111-1111-1111-1111-111111111111",
  email: "john@example.com",
  role: "USER",
};

describe("tokenUtils", () => {
  afterEach(async () => {
    await tokenUtils.redis.flushall();
  });

  describe("generateAccessToken", () => {
    it("signs a JWT containing id, email, and role", () => {
      const token = tokenUtils.generateAccessToken(user);
      const decoded = jwt.verify(token, "unit-access-secret");
      expect(decoded.id).toBe(user.id);
      expect(decoded.email).toBe(user.email);
      expect(decoded.role).toBe("USER");
    });
  });

  describe("verifyAccessToken", () => {
    it("verifies a valid token", () => {
      const token = tokenUtils.generateAccessToken(user);
      const decoded = tokenUtils.verifyAccessToken(token);
      expect(decoded.id).toBe(user.id);
    });

    it("throws on an invalid token", () => {
      expect(() => tokenUtils.verifyAccessToken("not-a-token")).toThrow();
    });

    it("throws on a token signed with a different secret", () => {
      const token = jwt.sign({ id: user.id }, "wrong-secret", { expiresIn: "15m" });
      expect(() => tokenUtils.verifyAccessToken(token)).toThrow();
    });
  });

  describe("generateRefreshToken", () => {
    it("stores a token and returns a signed JWT", async () => {
      const prisma = tokenUtils.prisma;
      prisma.refreshToken.create.mockResolvedValue({ id: "rt-1" });

      const token = await tokenUtils.generateRefreshToken(user);
      const decoded = jwt.verify(token, "unit-refresh-secret");
      expect(decoded.id).toBe(user.id);
      expect(decoded.type).toBe("refresh");
      expect(prisma.refreshToken.create).toHaveBeenCalled();
      const args = prisma.refreshToken.create.mock.calls[0][0];
      expect(args.data.userId).toBe(user.id);
      expect(args.data.token).toBe(tokenUtils.hashToken(token));
      expect(args.data.token).not.toBe(token);
    });
  });

  describe("verifyRefreshToken", () => {
    it("returns the decoded payload when token exists and is not expired", async () => {
      const prisma = tokenUtils.prisma;
      const future = new Date(Date.now() + 3600 * 1000);
      prisma.refreshToken.findUnique.mockResolvedValue({ id: "rt-1", expiresAt: future });

      const token = await tokenUtils.generateRefreshToken(user);
      const decoded = await tokenUtils.verifyRefreshToken(token);
      expect(decoded.id).toBe(user.id);
    });

    it("throws when token is not stored", async () => {
      const prisma = tokenUtils.prisma;
      prisma.refreshToken.findUnique.mockResolvedValue(null);
      const token = await tokenUtils.generateRefreshToken(user);
      await expect(tokenUtils.verifyRefreshToken(token)).rejects.toThrow(
        "Refresh token not found in database",
      );
    });

    it("throws and deletes when token is expired", async () => {
      const prisma = tokenUtils.prisma;
      const past = new Date(Date.now() - 3600 * 1000);
      prisma.refreshToken.findUnique.mockResolvedValue({ id: "rt-1", expiresAt: past });

      const token = await tokenUtils.generateRefreshToken(user);
      await expect(tokenUtils.verifyRefreshToken(token)).rejects.toThrow(
        "Refresh token has expired",
      );
      expect(prisma.refreshToken.delete).toHaveBeenCalledWith({ where: { id: "rt-1" } });
    });
  });

  describe("blacklist helpers", () => {
    it("blacklists a token and reports it", async () => {
      await tokenUtils.blacklistToken("abc123", 900);
      expect(await tokenUtils.isTokenBlacklisted("abc123")).toBe(true);
      expect(await tokenUtils.isTokenBlacklisted("nope")).toBe(false);
    });
  });

  describe("crypto token helpers", () => {
    it("generates a 64-char hex token", () => {
      const t = tokenUtils.generateCryptoToken();
      expect(t).toMatch(/^[a-f0-9]{64}$/);
    });

    it("hashes a token as sha256 hex", () => {
      const hashed = tokenUtils.hashToken("raw-token");
      const expected = crypto.createHash("sha256").update("raw-token").digest("hex");
      expect(hashed).toBe(expected);
    });
  });
});
