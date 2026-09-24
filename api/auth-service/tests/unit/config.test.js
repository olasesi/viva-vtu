describe("config", () => {
  let config;

  beforeEach(() => {
    jest.resetModules();
    config = require("../../src/config");
  });

  afterEach(() => {
    delete process.env.PORT;
    delete process.env.JWT_ACCESS_SECRET;
    delete process.env.JWT_REFRESH_SECRET;
    delete process.env.LOG_LEVEL;
    delete process.env.FRONTEND_URL;
  });

  it("loads default values when env vars are absent", () => {
    delete process.env.PORT;
    delete process.env.JWT_ACCESS_SECRET;
    delete process.env.JWT_REFRESH_SECRET;
    delete process.env.JWT_ACCESS_EXPIRY;
    delete process.env.JWT_REFRESH_EXPIRY;
    delete process.env.LOG_LEVEL;
    delete process.env.FRONTEND_URL;

    jest.resetModules();
    config = require("../../src/config");

    expect(config.port).toBe(3001);
    expect(config.jwt.accessSecret).toBe("change-me-access-secret");
    expect(config.jwt.accessExpiry).toBe("15m");
    expect(config.jwt.refreshExpiry).toBe("7d");
    expect(config.log.level).toBe("info");
    expect(config.frontendUrl).toBe("http://localhost:3000");
  });

  it("reads values from environment variables", () => {
    process.env.PORT = "4100";
    process.env.JWT_ACCESS_SECRET = "secret-a";
    process.env.JWT_REFRESH_SECRET = "secret-b";
    process.env.JWT_ACCESS_EXPIRY = "5m";
    process.env.JWT_REFRESH_EXPIRY = "14d";
    process.env.LOG_LEVEL = "warn";
    process.env.FRONTEND_URL = "https://app.vivavtu.com";

    jest.resetModules();
    config = require("../../src/config");

    expect(config.port).toBe(4100);
    expect(config.jwt.accessSecret).toBe("secret-a");
    expect(config.jwt.refreshSecret).toBe("secret-b");
    expect(config.jwt.accessExpiry).toBe("5m");
    expect(config.jwt.refreshExpiry).toBe("14d");
    expect(config.log.level).toBe("warn");
    expect(config.frontendUrl).toBe("https://app.vivavtu.com");
  });

  it("parses redis port as an integer", () => {
    process.env.REDIS_PORT = "6380";
    jest.resetModules();
    config = require("../../src/config");
    expect(config.redis.port).toBe(6380);
  });
});
