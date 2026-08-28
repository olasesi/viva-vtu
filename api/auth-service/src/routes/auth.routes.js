const express = require("express");
const { body } = require("express-validator");
const validate = require("../middleware/validate");
const authenticate = require("../middleware/authenticate");
const authController = require("../controllers/authController");

const router = express.Router();

router.post(
  "/register",
  validate([
    body("email")
      .trim()
      .notEmpty().withMessage("Email is required")
      .isEmail().withMessage("Please provide a valid email address")
      .normalizeEmail(),
    body("password")
      .notEmpty().withMessage("Password is required")
      .isLength({ min: 8 }).withMessage("Password must be at least 8 characters long")
      .matches(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/).withMessage("Password must contain at least one uppercase letter, one lowercase letter, and one number"),
    body("firstName")
      .trim()
      .notEmpty().withMessage("First name is required")
      .isLength({ min: 2, max: 50 }).withMessage("First name must be between 2 and 50 characters")
      .matches(/^[a-zA-Z\s'-]+$/).withMessage("First name can only contain letters, spaces, hyphens, and apostrophes"),
    body("lastName")
      .trim()
      .notEmpty().withMessage("Last name is required")
      .isLength({ min: 2, max: 50 }).withMessage("Last name must be between 2 and 50 characters")
      .matches(/^[a-zA-Z\s'-]+$/).withMessage("Last name can only contain letters, spaces, hyphens, and apostrophes"),
    body("phone")
      .optional({ values: "null" })
      .trim()
      .matches(/^\+?[\d\s-]{10,15}$/).withMessage("Please provide a valid phone number"),
  ]),
  authController.register
);

router.post(
  "/login",
  validate([
    body("email")
      .trim()
      .notEmpty().withMessage("Email is required")
      .isEmail().withMessage("Please provide a valid email address")
      .normalizeEmail(),
    body("password")
      .notEmpty().withMessage("Password is required"),
  ]),
  authController.login
);

router.post(
  "/logout",
  authenticate,
  authController.logout
);

router.post(
  "/refresh",
  validate([
    body("refreshToken")
      .notEmpty().withMessage("Refresh token is required")
      .isString().withMessage("Refresh token must be a string"),
  ]),
  authController.refreshToken
);

router.post(
  "/verify-email",
  validate([
    body("token")
      .notEmpty().withMessage("Verification token is required")
      .isString().withMessage("Token must be a string"),
  ]),
  authController.verifyEmail
);

router.post(
  "/forgot-password",
  validate([
    body("email")
      .trim()
      .notEmpty().withMessage("Email is required")
      .isEmail().withMessage("Please provide a valid email address")
      .normalizeEmail(),
  ]),
  authController.forgotPassword
);

router.post(
  "/reset-password",
  validate([
    body("token")
      .notEmpty().withMessage("Reset token is required")
      .isString().withMessage("Token must be a string"),
    body("password")
      .notEmpty().withMessage("Password is required")
      .isLength({ min: 8 }).withMessage("Password must be at least 8 characters long")
      .matches(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/).withMessage("Password must contain at least one uppercase letter, one lowercase letter, and one number"),
  ]),
  authController.resetPassword
);

router.get(
  "/profile",
  authenticate,
  authController.getProfile
);

router.put(
  "/profile",
  authenticate,
  validate([
    body("firstName")
      .optional()
      .trim()
      .isLength({ min: 2, max: 50 }).withMessage("First name must be between 2 and 50 characters")
      .matches(/^[a-zA-Z\s'-]+$/).withMessage("First name can only contain letters, spaces, hyphens, and apostrophes"),
    body("lastName")
      .optional()
      .trim()
      .isLength({ min: 2, max: 50 }).withMessage("Last name must be between 2 and 50 characters")
      .matches(/^[a-zA-Z\s'-]+$/).withMessage("Last name can only contain letters, spaces, hyphens, and apostrophes"),
    body("phone")
      .optional({ values: "null" })
      .trim()
      .matches(/^\+?[\d\s-]{10,15}$/).withMessage("Please provide a valid phone number"),
  ]),
  authController.updateProfile
);

module.exports = router;
