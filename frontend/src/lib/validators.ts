import { z } from "zod";

export const loginSchema = z.object({
  email: z.string().email("Please enter a valid email address"),
  password: z.string().min(6, "Password must be at least 6 characters"),
});

export const registerSchema = z.object({
  firstName: z.string().min(2, "First name must be at least 2 characters"),
  lastName: z.string().min(2, "Last name must be at least 2 characters"),
  email: z.string().email("Please enter a valid email address"),
  password: z.string().min(6, "Password must be at least 6 characters"),
  confirmPassword: z.string(),
  phone: z.string().optional(),
}).refine((data) => data.password === data.confirmPassword, {
  message: "Passwords do not match",
  path: ["confirmPassword"],
});

export const airtimeSchema = z.object({
  network: z.string().min(1, "Please select a network provider"),
  phoneNumber: z
    .string()
    .min(11, "Phone number must be 11 digits")
    .max(11, "Phone number must be 11 digits")
    .regex(/^\d+$/, "Phone number must contain only digits"),
  amount: z.number().min(50, "Minimum amount is ₦50").max(50000, "Maximum amount is ₦50,000"),
  pin: z.string().optional(),
});

export const dataSchema = z.object({
  network: z.string().min(1, "Please select a network provider"),
  phoneNumber: z
    .string()
    .min(11, "Phone number must be 11 digits")
    .max(11, "Phone number must be 11 digits")
    .regex(/^\d+$/, "Phone number must contain only digits"),
  plan: z.string().min(1, "Please select a data plan"),
  pin: z.string().optional(),
});

export const electricitySchema = z.object({
  distributor: z.string().min(1, "Please select an electricity distributor"),
  meterNumber: z
    .string()
    .min(10, "Meter number must be at least 10 digits")
    .max(15, "Meter number must not exceed 15 digits")
    .regex(/^\d+$/, "Meter number must contain only digits"),
  amount: z.number().min(500, "Minimum amount is ₦500").max(100000, "Maximum amount is ₦100,000"),
  meterType: z.enum(["prepaid", "postpaid"], {
    required_error: "Please select meter type",
  }),
  pin: z.string().optional(),
});

export const cableSchema = z.object({
  provider: z.string().min(1, "Please select a cable provider"),
  smartCardNumber: z
    .string()
    .min(10, "Smart card number must be at least 10 digits")
    .max(15, "Smart card number must not exceed 15 digits")
    .regex(/^\d+$/, "Smart card number must contain only digits"),
  plan: z.string().min(1, "Please select a plan"),
  pin: z.string().optional(),
});

export const fundWalletSchema = z.object({
  amount: z.number().min(100, "Minimum amount is ₦100").max(500000, "Maximum amount is ₦500,000"),
  paymentMethod: z.enum(["paystack", "flutterwave"], {
    required_error: "Please select a payment method",
  }),
});

export type LoginInput = z.infer<typeof loginSchema>;
export type RegisterInput = z.infer<typeof registerSchema>;
export type AirtimeInput = z.infer<typeof airtimeSchema>;
export type DataInput = z.infer<typeof dataSchema>;
export type ElectricityInput = z.infer<typeof electricitySchema>;
export type CableInput = z.infer<typeof cableSchema>;
export type FundWalletInput = z.infer<typeof fundWalletSchema>;
