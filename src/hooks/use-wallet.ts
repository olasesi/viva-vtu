"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api";
import type { FundWalletPayload } from "@/types";
import { toast } from "sonner";

export function useWallet() {
  const queryClient = useQueryClient();

  const balanceQuery = useQuery({
    queryKey: ["wallet", "balance"],
    queryFn: () => api.getWalletBalance(),
    refetchInterval: 30000,
  });

  const fundMutation = useMutation({
    mutationFn: (payload: FundWalletPayload) => api.fundWallet(payload),
    onSuccess: (response) => {
      const url = response.data.authorization_url;
      if (url) {
        window.location.href = url;
      }
      toast.success("Payment gateway loading...");
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || "Failed to initiate payment");
    },
  });

  const verifyPaymentMutation = useMutation({
    mutationFn: (reference: string) => api.verifyPayment(reference),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      toast.success("Payment verified successfully!");
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || "Payment verification failed");
    },
  });

  return {
    balance: balanceQuery.data?.data,
    isBalanceLoading: balanceQuery.isLoading,
    refetchBalance: balanceQuery.refetch,
    fundWallet: fundMutation.mutate,
    isFunding: fundMutation.isPending,
    verifyPayment: verifyPaymentMutation.mutate,
    isVerifying: verifyPaymentMutation.isPending,
  };
}
