const AppError = require("../../src/utils/AppError");

describe("AppError", () => {
  it("creates an error with default values", () => {
    const err = new AppError("Something broke");
    expect(err).toBeInstanceOf(Error);
    expect(err.message).toBe("Something broke");
    expect(err.statusCode).toBe(500);
    expect(err.isOperational).toBe(true);
    expect(err.status).toBe("error");
  });

  it("creates a fail error for 4xx codes", () => {
    const err = new AppError("Bad request", 400);
    expect(err.statusCode).toBe(400);
    expect(err.status).toBe("fail");
    expect(err.isOperational).toBe(true);
  });

  it("creates a fail error for 422", () => {
    const err = new AppError("Validation error", 422);
    expect(err.status).toBe("fail");
  });

  it("creates an internal error for 5xx codes", () => {
    const err = new AppError("Server exploded", 503);
    expect(err.statusCode).toBe(503);
    expect(err.status).toBe("error");
  });
});
