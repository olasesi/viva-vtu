const request = require("supertest");
const jwt = require("jsonwebtoken");

const app = require("../../src/app");
const config = require("../../src/config");
const { prisma, cleanDatabase } = require("../helpers/db");
const {
  queueVerificationEmail,
  queueResetPasswordEmail,
} = require("../../src/services/emailQueue");

const stamp = Date.now();
const email = `jane+${stamp}@example.com`;
const validPassword = "SecurePass123";
const phone = "+2348012345678";

let accessToken;
let refreshToken;

const registerBody = {
  email,
  password: validPassword,
  firstName: "Jane",
  lastName: "Doe",
  phone,
};

const loginUser = async (userEmail, password = validPassword) => {
  const res = await request(app).post("/api/auth/login").send({ email: userEmail, password });
  return res;
};

beforeAll(async () => {
  await cleanDatabase();
  jest.clearAllMocks();
});

afterAll(async () => {
  await cleanDatabase();
  await prisma.$disconnect();
});

describe("POST /api/auth/register", () => {
  it("registers a new user and returns tokens", async () => {
    const res = await request(app).post("/api/auth/register").send(registerBody);

    expect(res.status).toBe(201);
    expect(res.body.status).toBe("success");
    expect(res.body.message).toMatch(/Registration successful/);
    expect(res.body.data.user).toBeTruthy();
    expect(res.body.data.user.email).toBe(email);
    expect(res.body.data.user.password).toBeUndefined();
    expect(res.body.data.user.role).toBe("USER");
    expect(res.body.data.accessToken).toBeTruthy();
    expect(res.body.data.refreshToken).toBeTruthy();

    accessToken = res.body.data.accessToken;
    refreshToken = res.body.data.refreshToken;
  });

  it("queues a verification email with a raw token", () => {
    expect(queueVerificationEmail).toHaveBeenCalledTimes(1);
    const [userArg, tokenArg] = queueVerificationEmail.mock.calls[0];
    expect(userArg.email).toBe(email);
    expect(typeof tokenArg).toBe("string");
    expect(tokenArg.length).toBe(64);
  });

  it("persists the user in the database with a hashed password", async () => {
    const user = await prisma.user.findUnique({ where: { email } });
    expect(user).toBeTruthy();
    expect(user.password).not.toBe(validPassword);
    expect(user.isEmailVerified).toBe(false);
    expect(user.role).toBe("USER");
    expect(user.emailVerificationToken).toBeTruthy();
  });

  it("rejects a duplicate email with 409", async () => {
    const res = await request(app).post("/api/auth/register").send(registerBody);
    expect(res.status).toBe(409);
    expect(res.body.message).toMatch(/already exists/);
  });

  it("rejects an invalid email with 422", async () => {
    const res = await request(app)
      .post("/api/auth/register")
      .send({
        ...registerBody,
        email: "not-an-email",
      });
    expect(res.status).toBe(422);
    expect(res.body.status).toBe("fail");
    expect(res.body.errors.some((e) => e.field === "email")).toBe(true);
  });

  it("rejects a weak password with 422", async () => {
    const res = await request(app)
      .post("/api/auth/register")
      .send({
        ...registerBody,
        email: `weak+${stamp}@example.com`,
        password: "abc",
      });
    expect(res.status).toBe(422);
    expect(res.body.errors.some((e) => e.field === "password")).toBe(true);
  });
});

describe("POST /api/auth/login", () => {
  it("logs in with valid credentials and returns tokens", async () => {
    const res = await loginUser(email);
    expect(res.status).toBe(200);
    expect(res.body.status).toBe("success");
    expect(res.body.data.accessToken).toBeTruthy();
    expect(res.body.data.refreshToken).toBeTruthy();
    expect(res.body.data.user.email).toBe(email);
    expect(res.body.data.user.password).toBeUndefined();
    accessToken = res.body.data.accessToken;
    refreshToken = res.body.data.refreshToken;
  });

  it("embeds the user id, email, and role in the access token", () => {
    const decoded = jwt.verify(accessToken, config.jwt.accessSecret);
    expect(decoded.email).toBe(email);
    expect(decoded.role).toBe("USER");
    expect(decoded.id).toBeTruthy();
  });

  it("rejects an unknown email with 401", async () => {
    const res = await loginUser(`nobody+${stamp}@example.com`);
    expect(res.status).toBe(401);
    expect(res.body.message).toMatch(/Invalid email or password/);
  });

  it("rejects a wrong password with 401", async () => {
    const res = await loginUser(email, "WrongPassword1");
    expect(res.status).toBe(401);
    expect(res.body.message).toMatch(/Invalid email or password/);
  });
});

describe("POST /api/auth/verify", () => {
  it("returns the authenticated user for a valid token", async () => {
    const res = await request(app)
      .post("/api/auth/verify")
      .set("Authorization", `Bearer ${accessToken}`);

    expect(res.status).toBe(200);
    expect(res.body.success).toBe(true);
    expect(res.body.user).toBeTruthy();
    expect(res.body.user.email).toBe(email);
    expect(res.body.user.role).toBe("USER");
    expect(res.body.user.password).toBeUndefined();
  });

  it("rejects a missing token with 401", async () => {
    const res = await request(app).post("/api/auth/verify");
    expect(res.status).toBe(401);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toMatch(/required/i);
  });

  it("rejects a malformed token with 401", async () => {
    const res = await request(app)
      .post("/api/auth/verify")
      .set("Authorization", "Bearer garbage.token.value");
    expect(res.status).toBe(401);
    expect(res.body.success).toBe(false);
  });

  it("rejects a token signed with the wrong secret with 401", async () => {
    const forged = jwt.sign({ id: "abc", email, role: "USER" }, "wrong-secret", {
      expiresIn: "15m",
    });
    const res = await request(app)
      .post("/api/auth/verify")
      .set("Authorization", `Bearer ${forged}`);
    expect(res.status).toBe(401);
    expect(res.body.success).toBe(false);
  });

  it("rejects a blacklisted (logged out) token with 401", async () => {
    const logoutRes = await request(app)
      .post("/api/auth/logout")
      .set("Authorization", `Bearer ${accessToken}`);
    expect(logoutRes.status).toBe(200);

    const res = await request(app)
      .post("/api/auth/verify")
      .set("Authorization", `Bearer ${accessToken}`);
    expect(res.status).toBe(401);
    expect(res.body.success).toBe(false);
    expect(res.body.message).toMatch(/revoked/i);

    // Re-login to restore a fresh token for later tests
    const fresh = await loginUser(email);
    accessToken = fresh.body.data.accessToken;
    refreshToken = fresh.body.data.refreshToken;
  });
});

describe("POST /api/auth/refresh", () => {
  let preRotationToken;

  it("returns new tokens for a valid refresh token", async () => {
    preRotationToken = refreshToken;
    const res = await request(app).post("/api/auth/refresh").send({ refreshToken });

    expect(res.status).toBe(200);
    expect(res.body.data.accessToken).toBeTruthy();
    expect(res.body.data.refreshToken).toBeTruthy();

    accessToken = res.body.data.accessToken;
    refreshToken = res.body.data.refreshToken;
  });

  it("rejects a refresh token with 400 when missing", async () => {
    const res = await request(app).post("/api/auth/refresh").send({});
    expect(res.status).toBe(422);
    expect(res.body.status).toBe("fail");
  });

  it("rejects an invalid refresh token with 401", async () => {
    const res = await request(app)
      .post("/api/auth/refresh")
      .send({ refreshToken: "not-a-real-token" });
    expect(res.status).toBe(401);
    expect(res.body.status).toBe("fail");
  });

  it("rejects the rotated (single-use) refresh token", async () => {
    // preRotationToken was already rotated; reusing it must fail
    const res = await request(app)
      .post("/api/auth/refresh")
      .send({ refreshToken: preRotationToken });
    expect(res.status).toBe(401);
  });
});

describe("POST /api/auth/verify-email", () => {
  it("marks the email as verified with a valid token", async () => {
    const rawToken = queueVerificationEmail.mock.calls[0][1];
    const res = await request(app).post("/api/auth/verify-email").send({ token: rawToken });

    expect(res.status).toBe(200);
    expect(res.body.message).toMatch(/verified successfully/i);

    const user = await prisma.user.findUnique({ where: { email } });
    expect(user.isEmailVerified).toBe(true);
    expect(user.emailVerificationToken).toBeNull();
  });

  it("rejects reusing the same token with 400", async () => {
    const rawToken = queueVerificationEmail.mock.calls[0][1];
    const res = await request(app).post("/api/auth/verify-email").send({ token: rawToken });
    expect(res.status).toBe(400);
  });

  it("rejects an unknown token with 400", async () => {
    const res = await request(app)
      .post("/api/auth/verify-email")
      .send({ token: "deadbeef".repeat(8) });
    expect(res.status).toBe(400);
    expect(res.body.message).toMatch(/Invalid verification token/i);
  });
});

describe("POST /api/auth/forgot-password and /reset-password", () => {
  it("returns a generic success for an unknown email (no user enumeration)", async () => {
    const res = await request(app)
      .post("/api/auth/forgot-password")
      .send({ email: `ghost+${stamp}@example.com` });
    expect(res.status).toBe(200);
    expect(res.body.message).toMatch(/If an account exists/i);
    expect(queueResetPasswordEmail).not.toHaveBeenCalled();
  });

  it("queues a reset email for a known email", async () => {
    const res = await request(app).post("/api/auth/forgot-password").send({ email });
    expect(res.status).toBe(200);
    expect(res.body.message).toMatch(/If an account exists/i);
    expect(queueResetPasswordEmail).toHaveBeenCalledTimes(1);
  });

  it("resets the password with a valid token and invalidates old sessions", async () => {
    const rawResetToken = queueResetPasswordEmail.mock.calls[0][1];
    const newPassword = "BrandNewPass456";

    const res = await request(app)
      .post("/api/auth/reset-password")
      .send({ token: rawResetToken, password: newPassword });
    expect(res.status).toBe(200);
    expect(res.body.message).toMatch(/Password reset successfully/i);

    // Old password must no longer work
    const oldLogin = await loginUser(email, validPassword);
    expect(oldLogin.status).toBe(401);

    // New password must work
    const newLogin = await loginUser(email, newPassword);
    expect(newLogin.status).toBe(200);
    accessToken = newLogin.body.data.accessToken;
    refreshToken = newLogin.body.data.refreshToken;
  });

  it("rejects reusing the reset token with 400", async () => {
    const rawResetToken = queueResetPasswordEmail.mock.calls[0][1];
    const res = await request(app)
      .post("/api/auth/reset-password")
      .send({ token: rawResetToken, password: "AnotherPass789" });
    expect(res.status).toBe(400);
  });
});

describe("GET/PUT /api/auth/profile", () => {
  it("returns the current user profile for an authenticated request", async () => {
    const res = await request(app)
      .get("/api/auth/profile")
      .set("Authorization", `Bearer ${accessToken}`);

    expect(res.status).toBe(200);
    expect(res.body.data.user.email).toBe(email);
    expect(res.body.data.user.password).toBeUndefined();
  });

  it("rejects an unauthenticated request with 401", async () => {
    const res = await request(app).get("/api/auth/profile");
    expect(res.status).toBe(401);
  });

  it("updates the profile", async () => {
    const res = await request(app)
      .put("/api/auth/profile")
      .set("Authorization", `Bearer ${accessToken}`)
      .send({ firstName: "Janet", lastName: "Smith", phone: "+2348098765432" });

    expect(res.status).toBe(200);
    expect(res.body.data.user.firstName).toBe("Janet");
    expect(res.body.data.user.lastName).toBe("Smith");
    expect(res.body.data.user.phone).toBe("+2348098765432");
  });

  it("rejects an invalid profile update with 422", async () => {
    const res = await request(app)
      .put("/api/auth/profile")
      .set("Authorization", `Bearer ${accessToken}`)
      .send({ firstName: "X" });
    expect(res.status).toBe(422);
  });
});

describe("GET /health", () => {
  it("returns ok status", async () => {
    const res = await request(app).get("/health");
    expect(res.status).toBe(200);
    expect(res.body.status).toBe("ok");
    expect(res.body.service).toBe("auth-service");
  });
});

describe("role handling", () => {
  it("embeds the canonical role set in the token and verify response", async () => {
    const adminEmail = `admin+${stamp}@example.com`;
    const adminPassword = "AdminPass12345";
    const bcrypt = require("bcryptjs");
    const hashed = await bcrypt.hash(adminPassword, 10);

    await prisma.user.create({
      data: {
        email: adminEmail,
        password: hashed,
        firstName: "Admin",
        lastName: "User",
        role: "ADMIN",
        isEmailVerified: true,
      },
    });

    const loginRes = await request(app)
      .post("/api/auth/login")
      .send({ email: adminEmail, password: adminPassword });
    expect(loginRes.status).toBe(200);
    const token = loginRes.body.data.accessToken;

    const decoded = jwt.verify(token, config.jwt.accessSecret);
    expect(decoded.role).toBe("ADMIN");

    const verifyRes = await request(app)
      .post("/api/auth/verify")
      .set("Authorization", `Bearer ${token}`);
    expect(verifyRes.status).toBe(200);
    expect(verifyRes.body.user.role).toBe("ADMIN");
  });
});
