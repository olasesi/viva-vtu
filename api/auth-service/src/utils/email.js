const nodemailer = require("nodemailer");
const config = require("../config");
const logger = require("../config/logger");

const createTransporter = () => {
  return nodemailer.createTransport({
    host: config.mail.host,
    port: config.mail.port,
    secure: config.mail.port === 465,
    auth: {
      user: config.mail.user,
      pass: config.mail.pass,
    },
  });
};

const sendVerificationEmail = async (user, token) => {
  const verificationUrl = `${config.frontendUrl}/verify-email?token=${token}`;

  const htmlContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Verify Your Email - Viva VTU</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa;">
      <div style="max-width: 600px; margin: 0 auto; padding: 40px 20px;">
        <div style="background-color: #ffffff; border-radius: 12px; padding: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
          <div style="text-align: center; margin-bottom: 32px;">
            <h1 style="color: #1a73e8; margin: 0; font-size: 28px;">Viva VTU</h1>
          </div>
          <h2 style="color: #333; text-align: center; margin-bottom: 8px;">Verify Your Email Address</h2>
          <p style="color: #666; text-align: center; font-size: 16px; line-height: 1.6;">
            Hello ${user.firstName} ${user.lastName},
          </p>
          <p style="color: #666; text-align: center; font-size: 16px; line-height: 1.6;">
            Thank you for registering with Viva VTU. Please click the button below to verify your email address.
          </p>
          <div style="text-align: center; margin: 32px 0;">
            <a href="${verificationUrl}" style="background-color: #1a73e8; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-size: 16px; font-weight: 600; display: inline-block;">
              Verify Email Address
            </a>
          </div>
          <p style="color: #999; text-align: center; font-size: 14px; line-height: 1.6;">
            If you did not create an account, please ignore this email. This link will expire in 24 hours.
          </p>
          <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
          <p style="color: #bbb; text-align: center; font-size: 12px;">
            &copy; ${new Date().getFullYear()} Viva VTU. All rights reserved.
          </p>
        </div>
      </div>
    </body>
    </html>
  `;

  const transporter = createTransporter();

  const mailOptions = {
    from: `"Viva VTU" <${config.mail.from}>`,
    to: user.email,
    subject: "Verify Your Email - Viva VTU",
    html: htmlContent,
  };

  await transporter.sendMail(mailOptions);
  logger.info(`Verification email sent to ${user.email}`);
};

const sendPasswordResetEmail = async (user, token) => {
  const resetUrl = `${config.frontendUrl}/reset-password?token=${token}`;

  const htmlContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Reset Your Password - Viva VTU</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa;">
      <div style="max-width: 600px; margin: 0 auto; padding: 40px 20px;">
        <div style="background-color: #ffffff; border-radius: 12px; padding: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
          <div style="text-align: center; margin-bottom: 32px;">
            <h1 style="color: #1a73e8; margin: 0; font-size: 28px;">Viva VTU</h1>
          </div>
          <h2 style="color: #333; text-align: center; margin-bottom: 8px;">Reset Your Password</h2>
          <p style="color: #666; text-align: center; font-size: 16px; line-height: 1.6;">
            Hello ${user.firstName} ${user.lastName},
          </p>
          <p style="color: #666; text-align: center; font-size: 16px; line-height: 1.6;">
            We received a request to reset your password. Click the button below to set a new password.
          </p>
          <div style="text-align: center; margin: 32px 0;">
            <a href="${resetUrl}" style="background-color: #e74c3c; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-size: 16px; font-weight: 600; display: inline-block;">
              Reset Password
            </a>
          </div>
          <p style="color: #999; text-align: center; font-size: 14px; line-height: 1.6;">
            If you did not request a password reset, please ignore this email. This link will expire in 1 hour.
          </p>
          <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
          <p style="color: #bbb; text-align: center; font-size: 12px;">
            &copy; ${new Date().getFullYear()} Viva VTU. All rights reserved.
          </p>
        </div>
      </div>
    </body>
    </html>
  `;

  const transporter = createTransporter();

  const mailOptions = {
    from: `"Viva VTU" <${config.mail.from}>`,
    to: user.email,
    subject: "Reset Your Password - Viva VTU",
    html: htmlContent,
  };

  await transporter.sendMail(mailOptions);
  logger.info(`Password reset email sent to ${user.email}`);
};

module.exports = {
  createTransporter,
  sendVerificationEmail,
  sendPasswordResetEmail,
};
