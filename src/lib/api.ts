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
  SettingsGroupItem,
  SettingsGroupSchema,
  PublicSettings,
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
  (error) => Promise.reject(error),
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
  },
);

export async function login(
  payload: LoginPayload,
): Promise<ApiResponse<{ user: User; token: string }>> {
  const { data } = await api.post("/api/auth/login", payload);
  return data;
}

export async function register(
  payload: RegisterPayload,
): Promise<ApiResponse<{ user: User; token: string }>> {
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

export async function fundWallet(
  payload: FundWalletPayload,
): Promise<ApiResponse<{ authorization_url: string; reference: string }>> {
  const { data } = await api.post("/api/wallet/fund", payload);
  return data;
}

export async function verifyPayment(
  reference: string,
): Promise<ApiResponse<Wallet>> {
  const { data } = await api.get(`/api/wallet/verify/${reference}`);
  return data;
}

export async function buyAirtime(
  payload: AirtimePayload,
): Promise<ApiResponse<Transaction>> {
  const { data } = await api.post("/api/purchase/airtime", payload);
  return data;
}

export async function buyData(
  payload: DataPayload,
): Promise<ApiResponse<Transaction>> {
  const { data } = await api.post("/api/purchase/data", payload);
  return data;
}

export async function buyElectricity(
  payload: ElectricityPayload,
): Promise<ApiResponse<Transaction>> {
  const { data } = await api.post("/api/purchase/electricity", payload);
  return data;
}

export async function buyCableTv(
  payload: CableTvPayload,
): Promise<ApiResponse<Transaction>> {
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

export async function getTransaction(
  id: string,
): Promise<ApiResponse<Transaction>> {
  const { data } = await api.get(`/api/transactions/${id}`);
  return data;
}

export async function getKycStatus(): Promise<
  ApiResponse<{
    level: number;
    verified: boolean;
    verifiedAt?: string;
    bvn?: string | null;
    nin?: string | null;
  }>
> {
  const { data } = await api.get("/api/kyc");
  return data;
}

export async function submitKyc(payload: {
  bvn?: string;
  nin?: string;
}): Promise<
  ApiResponse<{
    level: number;
    verified: boolean;
    verifiedAt?: string;
    bvn?: string | null;
    nin?: string | null;
  }>
> {
  const { data } = await api.post("/api/kyc", payload);
  return data;
}

export async function getReferralStatus(): Promise<
  ApiResponse<{
    code: string;
    link: string;
    totalEarned: number;
    referralsCount: number;
  }>
> {
  const { data } = await api.get("/api/referral");
  return data;
}

export async function getResellerHierarchy(): Promise<
  ApiResponse<{
    parent: { id: number; name: string } | null;
    downline: Array<{
      id: number;
      name: string;
      email: string;
      isReseller: boolean;
      marginRate: number;
      joinedAt?: string;
    }>;
    downlineCount: number;
  }>
> {
  const { data } = await api.get("/api/reseller/hierarchy");
  return data;
}

export async function getResellerWebhooks(): Promise<ApiResponse<any[]>> {
  const { data } = await api.get("/api/reseller/webhooks");
  return data;
}

export async function createResellerWebhook(payload: {
  url: string;
  events?: string[];
}): Promise<ApiResponse<any>> {
  const { data } = await api.post("/api/reseller/webhooks", payload);
  return data;
}

export async function deleteResellerWebhook(
  id: number,
): Promise<ApiResponse<any>> {
  const { data } = await api.delete(`/api/reseller/webhooks/${id}`);
  return data;
}

export async function getAggregatorHealth(): Promise<
  ApiResponse<{
    providers: Record<
      string,
      { enabled: boolean; healthy: boolean; consecutive_failures: number }
    >;
    unhealthy: string[];
    checkedAt: string;
  }>
> {
  const { data } = await api.get("/api/admin/aggregator-health");
  return data;
}

export async function assignReseller(payload: {
  reseller_id: number;
  parent_id: number;
  margin_rate?: number;
}): Promise<ApiResponse<any>> {
  const { data } = await api.post("/api/admin/resellers/assign", payload);
  return data;
}

export async function getTickets(): Promise<ApiResponse<any>> {
  const { data } = await api.get("/api/tickets");
  return data;
}

export async function createTicket(payload: any): Promise<ApiResponse<any>> {
  const { data } = await api.post("/api/tickets", payload);
  return data;
}

export async function getTicket(id: number): Promise<ApiResponse<any>> {
  const { data } = await api.get(`/api/tickets/${id}`);
  return data;
}

export async function replyTicket(
  id: number,
  payload: { message: string },
): Promise<ApiResponse<any>> {
  const { data } = await api.post(`/api/tickets/${id}/reply`, payload);
  return data;
}

export async function getAdminTickets(params?: any): Promise<ApiResponse<any>> {
  const { data } = await api.get("/api/admin/tickets", { params });
  return data;
}

export async function getAdminTicket(id: number): Promise<ApiResponse<any>> {
  const { data } = await api.get(`/api/admin/tickets/${id}`);
  return data;
}

export async function replyAdminTicket(
  id: number,
  payload: any,
): Promise<ApiResponse<any>> {
  const { data } = await api.post(`/api/admin/tickets/${id}/reply`, payload);
  return data;
}

export async function updateAdminTicket(
  id: number,
  payload: any,
): Promise<ApiResponse<any>> {
  const { data } = await api.patch(`/api/admin/tickets/${id}`, payload);
  return data;
}

export async function getServices(): Promise<
  ApiResponse<{ networks: any[]; electricity: any[]; cable: any[] }>
> {
  const { data } = await api.get("/api/services");
  return data;
}

export async function getAllUsers(params?: {
  page?: number;
  limit?: number;
  search?: string;
}) {
  const { data } = await api.get("/api/admin/users", { params });
  return data;
}

export async function getAllTransactionsAdmin(params?: {
  page?: number;
  limit?: number;
  type?: string;
  status?: string;
}) {
  const { data } = await api.get("/api/admin/transactions", { params });
  return data;
}

export async function getDashboardStats() {
  const { data } = await api.get("/api/admin/stats");
  return data;
}

export async function getAdminSettings(): Promise<
  ApiResponse<SettingsGroupItem[]>
> {
  const { data } = await api.get("/api/settings");
  return data;
}

export async function getAdminSettingsGroup(
  group: string,
): Promise<ApiResponse<SettingsGroupItem>> {
  const { data } = await api.get(`/api/settings/${group}`);
  return data;
}

export async function getAdminSettingsSchema(
  group: string,
): Promise<ApiResponse<SettingsGroupSchema>> {
  const { data } = await api.get(`/api/settings/${group}/schema`);
  return data;
}

export async function updateAdminSettingsGroup(
  group: string,
  fields: Record<string, unknown>,
): Promise<ApiResponse<SettingsGroupItem>> {
  const { data } = await api.post(`/api/settings/${group}`, { fields });
  return data;
}

export async function getPublicSettings(): Promise<
  ApiResponse<PublicSettings>
> {
  const { data } = await api.get("/api/settings/public");
  return data;
}

export default api;
