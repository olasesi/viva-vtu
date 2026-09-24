const Sentry = require("@sentry/node");

const config = require("./index");

if (config.sentry.dsn && config.nodeEnv === "production") {
  Sentry.init({
    dsn: config.sentry.dsn,
    environment: config.nodeEnv,
    tracesSampleRate: parseFloat(config.sentry.tracesSampleRate) || 0.1,
    release: process.env.npm_package_version || process.env.RELEASE,
    beforeSend(event) {
      if (event.request) {
        delete event.request.cookies;
      }
      return event;
    },
  });
  Sentry.setupExpressErrorHandler ? Sentry.setupExpressErrorHandler : null;
}

module.exports = Sentry;
