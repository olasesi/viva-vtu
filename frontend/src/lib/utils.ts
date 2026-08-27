import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatCurrency(amount: number): string {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    minimumFractionDigits: 2,
  }).format(amount);
}

export function formatDate(date: string | Date): string {
  return new Intl.DateTimeFormat("en-NG", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(date));
}

export function formatDateShort(date: string | Date): string {
  return new Intl.DateTimeFormat("en-NG", {
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(new Date(date));
}

export function truncate(str: string, len: number): string {
  if (str.length <= len) return str;
  return str.slice(0, len) + "...";
}

export function getStatusColor(status: string): string {
  switch (status) {
    case "successful":
      return "bg-emerald-100 text-emerald-800 border-emerald-200";
    case "pending":
      return "bg-yellow-100 text-yellow-800 border-yellow-200";
    case "failed":
      return "bg-red-100 text-red-800 border-red-200";
    default:
      return "bg-gray-100 text-gray-800 border-gray-200";
  }
}

export function getTransactionLabel(type: string): string {
  switch (type) {
    case "airtime":
      return "Airtime Purchase";
    case "data":
      return "Data Purchase";
    case "electricity":
      return "Electricity Payment";
    case "cable_tv":
      return "Cable TV Subscription";
    case "wallet_fund":
      return "Wallet Funding";
    case "transfer":
      return "Transfer";
    default:
      return type;
  }
}

export function generateReference(): string {
  return `VTU-${Date.now()}-${Math.random().toString(36).substr(2, 9).toUpperCase()}`;
}
