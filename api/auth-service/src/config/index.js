const config = {
  port: parseInt(process.env.PORT, 10) || 3001,
  nodeEnv: process.env.NODE_ENV || "development",
  frontendUrl: process.env.FRONTEND_URL || "http://localhost:3000",

  database: {
    url: process.env.DATABASE_URL,
  },

  jwt: {
    accessSecret: process.env.JWT_ACCESS_SECRET || "change-me-access-secret",
    accessExpiry: process.env.JWT_ACCESS_EXPIRY || "15m",
    refreshSecret: process.env.JWT_REFRESH_SECRET || "change-me-refresh-secret",
    refreshExpiry: process.env.JWT_REFRESH_EXPIRY || "7d",
  },

  redis: {
    host: process.env.REDIS_HOST || "127.0.0.1",
    port: parseInt(process.env.REDIS_PORT, 10) || 6379,
    password: process.env.REDIS_PASSWORD || undefined,
  },

  mail: {
    host: process.env.SMTP_HOST || "smtp.mailtrap.io",
    port: parseInt(process.env.SMTP_PORT, 10) || 587,
    user: process.env.SMTP_USER || "",
    pass: process.env.SMTP_PASS || "",
    from: process.env.MAIL_FROM || "noreply@vivavtu.com",
  },

  bull: {
    redis: {
      host: process.env.REDIS_HOST || "127.0.0.1",
      port: parseInt(process.env.REDIS_PORT, 10) || 6379,
      password: process.env.REDIS_PASSWORD || undefined,
    },
  },

  log: {
    level: process.env.LOG_LEVEL || "info",
    dir: process.env.LOG_DIR || "logs",
  },
};

module.exports = config;
