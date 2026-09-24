jest.mock("ioredis", () => require("ioredis-mock"));

jest.mock("../src/services/emailQueue", () => {
  return {
    emailQueue: {
      close: jest.fn().mockResolvedValue(undefined),
      process: jest.fn(),
      add: jest.fn().mockResolvedValue({ id: "mock-job" }),
      on: jest.fn(),
    },
    queueVerificationEmail: jest.fn().mockResolvedValue({ id: "mock-verify-job" }),
    queueResetPasswordEmail: jest.fn().mockResolvedValue({ id: "mock-reset-job" }),
  };
});
