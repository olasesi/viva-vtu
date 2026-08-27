import { create } from "zustand";
import type { User } from "@/types";

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  login: (user: User, token: string) => void;
  logout: () => void;
  setUser: (user: User) => void;
  setToken: (token: string) => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  token: null,
  isAuthenticated: false,

  login: (user, token) => {
    if (typeof window !== "undefined") {
      localStorage.setItem("viva-vtu-token", token);
      localStorage.setItem("viva-vtu-user", JSON.stringify(user));
    }
    set({ user, token, isAuthenticated: true });
  },

  logout: () => {
    if (typeof window !== "undefined") {
      localStorage.removeItem("viva-vtu-token");
      localStorage.removeItem("viva-vtu-user");
    }
    set({ user: null, token: null, isAuthenticated: false });
  },

  setUser: (user) => {
    if (typeof window !== "undefined") {
      localStorage.setItem("viva-vtu-user", JSON.stringify(user));
    }
    set({ user });
  },

  setToken: (token) => {
    if (typeof window !== "undefined") {
      localStorage.setItem("viva-vtu-token", token);
    }
    set({ token });
  },
}));

export function initializeAuth(): void {
  if (typeof window !== "undefined") {
    const token = localStorage.getItem("viva-vtu-token");
    const userStr = localStorage.getItem("viva-vtu-user");
    if (token && userStr) {
      try {
        const user = JSON.parse(userStr) as User;
        useAuthStore.getState().login(user, token);
      } catch {
        localStorage.removeItem("viva-vtu-token");
        localStorage.removeItem("viva-vtu-user");
      }
    }
  }
}
