require("dotenv").config();

const express = require("express");
const helmet = require("helmet");
const cors = require("cors");
const compression = require("compression");
const morgan = require("morgan");
const swaggerUi = require("swagger-ui-express");

const config = require("./config");
const logger = require("./config/logger");
const { errorHandler, NotFoundError } = require("./middleware/errorHandler");
const { rateLimiter } = require("./middleware/rateLimiter");
const { requestLogger } = require("./middleware/requestLogger");
const proxyRoutes = require("./routes/proxy");
const swaggerSpec = require("./swagger");

const app = express();

app.use(helmet());
app.use(cors({ origin: config.cors.origin, credentials: true }));
app.use(compression());
app.use(morgan("combined", { stream: { write: (msg) => logger.info(msg.trim()) } }));
app.use(requestLogger);
app.use(rateLimiter);

app.get("/health", (req, res) => {
  res.status(200).json({
    status: "ok",
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
  });
});

app.use(
  "/api-docs",
  swaggerUi.serve,
  swaggerUi.setup(swaggerSpec, {
    explorer: true,
    customCss: ".swagger-ui .topbar { display: none }",
    customSiteTitle: "Viva VTU API Gateway",
  }),
);

app.use("/api", proxyRoutes);

app.use((req, res, next) => {
  next(new NotFoundError(`Route ${req.method} ${req.originalUrl} not found`));
});

app.use(errorHandler);

module.exports = app;
