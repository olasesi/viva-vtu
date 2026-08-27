export interface User {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  phone?: string;
  role: "user" | "admin";
  isVerified: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface Wallet {
  id: string;
  userId: string;
  balance: number;
  currency: string;
  updatedAt: string;
}

export interface Transaction {
  id: string;
  userId: string;
  type: "airtime" | "data" | "electricity" | "cable_tv" | "wallet_fund" | "transfer";
  status: "successful" | "pending" | "failed";
  amount: number;
  fee: number;
  totalAmount: number;
  reference: string;
  description: string;
  provider?: string;
  phoneNumber?: string;
  meterNumber?: string;
  smartCardNumber?: string;
  createdAt: string;
  updatedAt: string;
}

export interface ServiceProvider {
  id: string;
  name: string;
  code: string;
  type: "network" | "electricity" | "cable";
  icon?: string;
  isActive: boolean;
}

export interface Product {
  id: string;
  name: string;
  code: string;
  price: number;
  providerId: string;
  provider: ServiceProvider;
  description?: string;
  validity?: string;
  isActive: boolean;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface PaginatedResponse<T> {
  success: boolean;
  message: string;
  data: {
    items: T[];
    total: number;
    page: number;
    limit: number;
    totalPages: number;
  };
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterPayload {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  confirmPassword: string;
  phone?: string;
}

export interface AirtimePayload {
  network: string;
  phoneNumber: string;
  amount: number;
  pin?: string;
}

export interface DataPayload {
  network: string;
  phoneNumber: string;
  plan: string;
  pin?: string;
}

export interface ElectricityPayload {
  distributor: string;
  meterNumber: string;
  amount: number;
  meterType: "prepaid" | "postpaid";
  pin?: string;
}

export interface CableTvPayload {
  provider: string;
  smartCardNumber: string;
  plan: string;
  pin?: string;
}

export interface FundWalletPayload {
  amount: number;
  paymentMethod: "paystack" | "flutterwave";
}
