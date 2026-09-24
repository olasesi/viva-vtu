const { body } = require("express-validator");
const validate = require("../../src/middleware/validate");
const buildReq = (bodyValue) => ({ body: bodyValue });

const buildRes = () => {
  const res = {};
  res.status = jest.fn().mockReturnValue(res);
  res.json = jest.fn().mockReturnValue(res);
  return res;
};

describe("validate middleware", () => {
  it("calls next() when validations pass", async () => {
    const req = buildReq({ email: "john@example.com", password: "StrongPass1" });
    const res = buildRes();
    const next = jest.fn();

    const validators = [
      body("email").isEmail().withMessage("invalid email"),
      body("password").notEmpty().withMessage("password required"),
    ];

    const middleware = validate(validators);
    await middleware(req, res, next);

    expect(next).toHaveBeenCalled();
    expect(res.status).not.toHaveBeenCalled();
  });

  it("returns 422 with field errors when validation fails", async () => {
    const req = buildReq({ email: "not-an-email", password: "" });
    const res = buildRes();
    const next = jest.fn();

    const validators = [
      body("email").isEmail().withMessage("Please provide a valid email address"),
      body("password").notEmpty().withMessage("Password is required"),
    ];

    const middleware = validate(validators);
    await middleware(req, res, next);

    expect(next).not.toHaveBeenCalled();
    expect(res.status).toHaveBeenCalledWith(422);
    const payload = res.json.mock.calls[0][0];
    expect(payload.status).toBe("fail");
    expect(payload.message).toBe("Validation failed");
    expect(payload.errors[0]).toMatchObject({ field: "email" });
  });

  it("reports the second chain when the first chain is valid", async () => {
    const req = buildReq({ email: "john@example.com", password: "" });
    const res = buildRes();
    const next = jest.fn();

    const validators = [
      body("email").isEmail().withMessage("Please provide a valid email address"),
      body("password").notEmpty().withMessage("Password is required"),
    ];

    const middleware = validate(validators);
    await middleware(req, res, next);

    expect(res.status).toHaveBeenCalledWith(422);
    const payload = res.json.mock.calls[0][0];
    expect(payload.errors[0]).toMatchObject({ field: "password" });
  });

  it("returns 422 with a single error when the first validator fails and has message", async () => {
    const req = buildReq({});
    const res = buildRes();
    const next = jest.fn();

    const validators = [
      body("email").isEmail().withMessage("Please provide a valid email address"),
    ];

    const middleware = validate(validators);
    await middleware(req, res, next);

    expect(res.status).toHaveBeenCalledWith(422);
    const payload = res.json.mock.calls[0][0];
    expect(payload.errors[0].message).toBe("Please provide a valid email address");
  });
});
