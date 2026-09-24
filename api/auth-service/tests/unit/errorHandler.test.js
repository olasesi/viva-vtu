const AppError = require("../../src/utils/AppError");

describe("errorHandler", () => {
  let errorHandler;
  let req;
  let res;

  beforeEach(() => {
    jest.resetModules();
    delete process.env.NODE_ENV;
    process.env.NODE_ENV = "test";

    errorHandler = require("../../src/middleware/errorHandler");

    req = { originalUrl: "/api/auth/login", method: "POST" };
    res = {
      status: jest.fn().mockReturnThis(),
      json: jest.fn().mockReturnThis(),
    };
  });

  it("responds with fail status and default message for operational errors", () => {
    const err = new AppError("Invalid email or password", 401);
    errorHandler(err, req, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(401);
    const payload = res.json.mock.calls[0][0];
    expect(payload.status).toBe("fail");
    expect(payload.message).toBe("Invalid email or password");
    expect(payload.errors).toBeUndefined();
  });

  it("maps Prisma P2002 unique constraint to 409", () => {
    const err = {
      code: "P2002",
      meta: { target: ["email"] },
      message: "Unique constraint failed",
    };
    errorHandler(err, req, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(409);
    const payload = res.json.mock.calls[0][0];
    expect(payload.message).toContain("email");
    expect(payload.status).toBe("fail");
  });

  it("maps Prisma P2025 not found to 404", () => {
    const err = { code: "P2025", message: "Record not found" };
    errorHandler(err, req, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(404);
    expect(res.json.mock.calls[0][0].message).toBe("Record not found");
  });

  it("maps JsonWebTokenError to 401", () => {
    const err = { name: "JsonWebTokenError", message: "invalid signature", statusCode: 500 };
    errorHandler(err, req, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(401);
    expect(res.json.mock.calls[0][0].message).toBe("Invalid token. Please log in again.");
  });

  it("maps TokenExpiredError to 401", () => {
    const err = { name: "TokenExpiredError", message: "jwt expired", statusCode: 500 };
    errorHandler(err, req, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(401);
    expect(res.json.mock.calls[0][0].message).toBe("Token has expired. Please log in again.");
  });

  it("responds with error status for 5xx", () => {
    const err = new Error("boom");
    errorHandler(err, req, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(500);
    expect(res.json.mock.calls[0][0].status).toBe("error");
  });

  it("does not leak stack traces in production", () => {
    process.env.NODE_ENV = "production";
    jest.resetModules();
    const prodHandler = require("../../src/middleware/errorHandler");
    prodHandler(new Error("secret detail"), req, res, jest.fn());
    const payload = res.json.mock.calls[0][0];
    expect(payload.stack).toBeUndefined();
    process.env.NODE_ENV = "test";
  });
});
