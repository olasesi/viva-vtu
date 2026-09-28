const swaggerJsdoc = require("swagger-jsdoc");

const options = {
  definition: {
    openapi: "3.0.0",
    info: {
      title: "Viva VTU API Gateway",
      version: "1.0.0",
      description:
        'API Gateway for the Viva VTU microservices architecture. This gateway routes requests to downstream services. Route map:\n\n| Prefix | Target service | Forwarded path |\n| --- | --- | --- |\n| /api/auth/* | Auth | path without /api/auth ("/login") |\n| /api/analytics/* | Analytics | path without /api/analytics ("/dashboard") |\n| /api/billing/* | Billing (Laravel) | "/api" + path ("/api/wallet/balance") |\n| /api/wallet/* | Billing (Laravel) | unchanged ("/api/wallet/balance") |\n| /api/purchase/* | Billing (Laravel) | unchanged |\n| /api/services/* | Billing (Laravel) | unchanged |\n| /api/transactions/* | Billing (Laravel) | unchanged |\n| /api/service-requests/* | Billing (Laravel) | unchanged |\n| /api/settings/* | Billing (Laravel) | unchanged |\n| /api/admin/* | Billing (Laravel) | unchanged |\n| /api/webhook/* | Billing (Laravel) | unchanged (no auth) |\n\nSignals: a `/health` endpoint reports gateway liveness; structured logs (Winston) cover pro/con target requests and errors.',
      contact: {
        name: "Viva VTU Team",
      },
      license: {
        name: "ISC",
      },
    },
    servers: [
      {
        url: "http://localhost:3000",
        description: "Development server",
      },
      {
        url: "https://api.vivavtu.com",
        description: "Production server",
      },
    ],
    components: {
      securitySchemes: {
        bearerAuth: {
          type: "http",
          scheme: "bearer",
          bearerFormat: "JWT",
          description: "Enter your JWT token",
        },
      },
      schemas: {
        HealthCheck: {
          type: "object",
          properties: {
            status: {
              type: "string",
              example: "ok",
            },
            timestamp: {
              type: "string",
              format: "date-time",
              example: "2025-01-15T10:30:00.000Z",
            },
            uptime: {
              type: "number",
              example: 12345.678,
            },
          },
        },
        ErrorResponse: {
          type: "object",
          properties: {
            status: {
              type: "string",
              example: "error",
            },
            message: {
              type: "string",
              example: "Something went wrong",
            },
          },
        },
      },
    },
  },
  apis: [],
};

const swaggerSpec = swaggerJsdoc(options);

module.exports = swaggerSpec;
