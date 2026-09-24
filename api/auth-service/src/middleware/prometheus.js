const { httpRequestsTotal, httpRequestDurationSeconds } = require("../config/metrics");

const prometheusMetrics = (req, res, next) => {
  const start = process.hrtime();

  res.on("finish", () => {
    const duration = process.hrtime(start);
    const seconds = duration[0] + duration[1] / 1e9;
    const route = req.route ? req.baseUrl + req.route.path : req.path;

    httpRequestsTotal.inc({ method: req.method, route, status_code: res.statusCode });
    httpRequestDurationSeconds.observe(
      { method: req.method, route, status_code: res.statusCode },
      seconds,
    );
  });

  next();
};

module.exports = prometheusMetrics;
