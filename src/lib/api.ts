import axios from "axios";
import { API_BASE_URL } from "./constants";
import type {
  ApiResponse,
  PaginatedResponse,
  User,
  Wallet,
  Transaction,
  LoginPayload,
  RegisterPayload,
  AirtimePayload,
  DataPayload,
  ElectricityPayload,
  CableTvPayload,
  FundWalletPayload,
} from "@/types";

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    "Content-Type": "application/json",
  },
});

api.interceptors.request.use(
  (config) => {
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("viva-vtu-token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    }
    return config;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      if (typeof window !== "undefined") {
        localStorage.removeItem("viva-vtu-token");
        localStorage.removeItem("viva-vtu-user");
        window.location.href = "/login";
      }
    }
    return Promise.reject(error);
  }
);

export async function login(payload: LoginPayload): Promise<ApiResponse<{ user: User; token: string }>> {
  const { data } = await api.post("/api/auth/login", payload);
  return data;
}

export async function register(payload: RegisterPayload): Promise<ApiResponse<{ user: User; token: string }>> {
  const { data } = await api.post("/api/auth/register", payload);
  return data;
}

export async function getProfile(): Promise<ApiResponse<User>> {
  const { data } = await api.get("/api/auth/profile");
  return data;
}

export async function getWalletBalance(): Promise<ApiResponse<Wallet>> {
  const { data } = await api.get("/api/wallet/balance");
  return data;
}

export async function fundWallet(payload: FundWalletPayload): Promise<ApiResponse<{ authorization_url: string; reference: string }>> {
  const { data } = await api.post("/api/wallet/fund", payload);
  return data;
}

export async function verifyPayment(reference: string): Promise<ApiResponse<Wallet>> {
  const { data } = await api.get(`/api/wallet/verify/${reference}`);
  return data;
}

export async function buyAirtime(payload: AirtimePayload): Promise<ApiResponse<Transaction>> {
  const { data } = await api.post("/api/purchase/airtime", payload);
  return data;
}

export async function buyData(payload: DataPayload): Promise<ApiResponse<Transaction>> {
  const { data } = await api.post("/api/purchase/data", payload);
  return data;
}

export async function buyElectricity(payload: ElectricityPayload): Promise<ApiResponse<Transaction>> {
  const { data } = await api.post("/api/purchase/electricity", payload);
  return data;
}

export async function buyCableTv(payload: CableTvPayload): Promise<ApiResponse<Transaction>> {
  const { data } = await api.post("/api/purchase/cable-tv", payload);
  return data;
}

export async function getTransactions(params?: {
  type?: string;
  status?: string;
  page?: number;
  limit?: number;
  startDate?: string;
  endDate?: string;
}): Promise<PaginatedResponse<Transaction>> {
  const { data } = await api.get("/api/transactions", { params });
  return data;
}

export async function getTransaction(id: string): Promise<ApiResponse<Transaction>> {
  const { data } = await api.get(`/api/transactions/${id}`);
  return data;
}

export async function getServices(): Promise<ApiResponse<{ networks: any[]; electricity: any[]; cable: any[] }>> {
  const { data } = await api.get("/api/services");
  return data;
}

export async function getAllUsers(params?: { page?: number; limit?: number; search?: string }) {
  const { data } = await api.get("/api/admin/users", { params });
  return data;
}

export async function getAllTransactionsAdmin(params?: { page?: number; limit?: number; type?: string; status?: string }) {
  const { data } = await api.get("/api/admin/transactions", { params });
  return data;
}

export async function getDashboardStats() {
  const { data } = await api.get("/api/admin/stats");
  return data;
}

export default api;
