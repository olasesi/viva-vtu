const client = require("prom-client");

const register = new client.Registry();

client.collectDefaultMetrics({
  register,
  prefix: "auth_service_",
  gcDurationBuckets: [0.001, 0.01, 0.1, 1, 2, 5],
});

const httpRequestsTotal = new client.Counter({
  name: "auth_service_http_requests_total",
  help: "Total number of HTTP requests",
  labelNames: ["method", "route", "status_code"],
  registers: [register],
});

const httpRequestDurationSeconds = new client.Histogram({
  name: "auth_service_http_request_duration_seconds",
  help: "Duration of HTTP requests in seconds",
  labelNames: ["method", "route", "status_code"],
  buckets: [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
  registers: [register],
});

const userRegistrationsTotal = new client.Counter({
  name: "auth_service_user_registrations_total",
  help: "Total number of successful user registrations",
  registers: [register],
});

const userLoginsTotal = new client.Counter({
  name: "auth_service_user_logins_total",
  help: "Total number of successful user logins",
  registers: [register],
});

const refreshTokensIssuedTotal = new client.Counter({
  name: "auth_service_refresh_tokens_issued_total",
  help: "Total number of refresh tokens issued",
  registers: [register],
});

const tokenVerificationsTotal = new client.Counter({
  name: "auth_service_token_verifications_total",
  help: "Total number of token verification attempts",
  labelNames: ["result"],
  registers: [register],
});

const passwordResetsTotal = new client.Counter({
  name: "auth_service_password_resets_total",
  help: "Total number of password reset completions",
  registers: [register],
});

const emailVerificationsTotal = new client.Counter({
  name: "auth_service_email_verifications_total",
  help: "Total number of successful email verifications",
  registers: [register],
});

const emailQueueJobs = new client.Counter({
  name: "auth_service_email_queue_jobs_total",
  help: "Total number of email queue job results",
  labelNames: ["type", "result"],
  registers: [register],
});

module.exports = {
  register,
  httpRequestsTotal,
  httpRequestDurationSeconds,
  userRegistrationsTotal,
  userLoginsTotal,
  refreshTokensIssuedTotal,
  tokenVerificationsTotal,
  passwordResetsTotal,
  emailVerificationsTotal,
  emailQueueJobs,
};
