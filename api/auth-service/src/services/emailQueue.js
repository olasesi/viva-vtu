const Bull = require("bull");
const config = require("../config");
const logger = require("../config/logger");
const { sendVerificationEmail, sendPasswordResetEmail } = require("../utils/email");

const emailQueue = new Bull("email", {
  redis: {
    host: config.bull.redis.host,
    port: config.bull.redis.port,
    password: config.bull.redis.password,
  },
  defaultJobOptions: {
    removeOnComplete: 100,
    removeOnFail: 50,
    attempts: 3,
    backoff: {
      type: "exponential",
      delay: 2000,
    },
  },
});

emailQueue.process("verify-email", async (job) => {
  const { user, token } = job.data;
  logger.info(`Processing verification email job for ${user.email}`);
  await sendVerificationEmail(user, token);
  logger.info(`Verification email sent successfully to ${user.email}`);
});

emailQueue.process("reset-password", async (job) => {
  const { user, token } = job.data;
  logger.info(`Processing password reset email job for ${user.email}`);
  await sendPasswordResetEmail(user, token);
  logger.info(`Password reset email sent successfully to ${user.email}`);
});

emailQueue.on("completed", (job) => {
  logger.info(`Email job ${job.id} completed for ${job.data.user?.email || "unknown"}`);
});

emailQueue.on("failed", (job, err) => {
  logger.error(`Email job ${job.id} failed after ${job.attemptsMade} attempts:`, {
    error: err.message,
    email: job.data.user?.email,
    jobType: job.name,
  });
});

emailQueue.on("error", (err) => {
  logger.error("Email queue error:", { error: err.message });
});

const queueVerificationEmail = async (user, token) => {
  const job = await emailQueue.add("verify-email", { user, token });
  logger.info(`Verification email queued (job ${job.id}) for ${user.email}`);
  return job;
};

const queueResetPasswordEmail = async (user, token) => {
  const job = await emailQueue.add("reset-password", { user, token });
  logger.info(`Password reset email queued (job ${job.id}) for ${user.email}`);
  return job;
};

module.exports = {
  emailQueue,
  queueVerificationEmail,
  queueResetPasswordEmail,
};
