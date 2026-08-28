/**
 * Simple metrics reporter for local development.
 * Prints service health-check URLs and basic runtime info.
 */
const os = require("os");
const { execSync } = require("child_process");

function serviceSummary() {
  const services = [
    { name: "API Gateway", port: 3000 },
    { name: "Auth Service", port: 3001 },
    { name: "Billing (Laravel)", port: 8000 },
    { name: "Analytics (Django)", port: 8001 },
    { name: "Frontend (Next.js)", port: 3002 },
    { name: "MailHog", port: 8025 },
  ];

  console.log("╔════════════════════════════════════════════════╗");
  console.log("║              VIVA VTU — Metrics Report         ║");
  console.log("╚════════════════════════════════════════════════╝");
  console.log("");
  console.log(`Platform:  ${process.platform} (${os.arch()})`);
  console.log(`Node:      ${process.version}`);
  console.log(`Hostname:  ${os.hostname()}`);
  console.log(`CPUs:      ${os.cpus().length}`);
  console.log(`Mem:       ${(os.totalmem() / 1024 ** 3).toFixed(1)} GB total`);
  console.log("");

  services.forEach((s) => {
    console.log(`  ${s.name.padEnd(20)} http://localhost:${s.port}`);
  });

  console.log("");
  console.log("Run `make logs` for live container logs.");
}

serviceSummary();
