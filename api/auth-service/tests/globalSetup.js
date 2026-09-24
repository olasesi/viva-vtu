const { execSync } = require("child_process");
const path = require("path");

module.exports = () => {
  try {
    execSync("npx prisma migrate deploy", {
      env: {
        ...process.env,
        DATABASE_URL: "mysql://root:@127.0.0.1:3306/viva_vtu_auth_test",
      },
      cwd: path.resolve(__dirname, ".."),
      stdio: "inherit",
    });
  } catch (error) {
    process.stderr.write(`[globalSetup] prisma migrate deploy failed: ${error.message}\n`);
    process.exit(1);
  }
};
