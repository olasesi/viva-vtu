"use client";

import { useEffect } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useAuthStore } from "@/stores/auth-store";
import * as api from "@/lib/api";
import type { LoginPayload, RegisterPayload } from "@/types";

export function useAuth() {
  const { user, isAuthenticated, login: storeLogin, logout: storeLogout, setUser } = useAuthStore();
  const router = useRouter();
  const queryClient = useQueryClient();

  const loginMutation = useMutation({
    mutationFn: (payload: LoginPayload) => api.login(payload),
    onSuccess: (response) => {
      const data: any = response.data;

      if (data?.twoFactorRequired) {
        sessionStorage.setItem("viva-2fa-challenge", data.challengeToken);
        router.push("/login?step=2fa");
        return;
      }

      storeLogin(data.user, data.token);
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      router.push("/dashboard");
    },
  });

  const verifyTwoFactorMutation = useMutation({
    mutationFn: (payload: { challengeToken: string; code: string }) =>
      api.verifyTwoFactorLogin(payload),
    onSuccess: (response) => {
      sessionStorage.removeItem("viva-2fa-challenge");
      storeLogin(response.data.user, response.data.token);
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      router.push("/dashboard");
    },
  });

  const registerMutation = useMutation({
    mutationFn: (payload: RegisterPayload) => api.register(payload),
    onSuccess: (response) => {
      storeLogin(response.data.user, response.data.token);
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      router.push("/dashboard");
    },
  });

  const profileQuery = useQuery({
    queryKey: ["profile"],
    queryFn: () => api.getProfile(),
    enabled: isAuthenticated,
  });

  useEffect(() => {
    if (profileQuery.data?.data) {
      setUser(profileQuery.data.data);
    }
  }, [profileQuery.data, setUser]);

  const logout = () => {
    storeLogout();
    queryClient.clear();
    router.push("/login");
  };

  return {
    user,
    isAuthenticated,
    login: loginMutation.mutate,
    loginError: loginMutation.error,
    isLoginLoading: loginMutation.isPending,
    register: registerMutation.mutate,
    registerError: registerMutation.error,
    isRegisterLoading: registerMutation.isPending,
    verifyTwoFactor: verifyTwoFactorMutation.mutate,
    verifyTwoFactorError: verifyTwoFactorMutation.error,
    isVerifyTwoFactorLoading: verifyTwoFactorMutation.isPending,
    logout,
    profile: profileQuery.data?.data,
  };
}
