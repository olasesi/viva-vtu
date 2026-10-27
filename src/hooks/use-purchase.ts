"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api";
import type { AirtimePayload, DataPayload, ElectricityPayload, CableTvPayload } from "@/types";
import { toast } from "sonner";
import { useTransactionPinPrompt } from "@/components/transaction-pin/transaction-pin-provider";

const PIN_REQUIRED = "transaction_pin_required";

function isPinRequired(error: any) {
  return error?.response?.data?.code === PIN_REQUIRED;
}

export function usePurchase() {
  const queryClient = useQueryClient();
  const { requestTransactionPin } = useTransactionPinPrompt();

  const withPinRetry = async <T,>(
    payload: Record<string, any>,
    call: (data: Record<string, any>) => Promise<T>,
  ): Promise<T> => {
    try {
      return await call(payload);
    } catch (error) {
      if (!isPinRequired(error)) {
        throw error;
      }

      const pin = await requestTransactionPin();

      if (!pin) {
        throw Object.assign(new Error("Transaction PIN is required"), {
          cancelled: true,
        });
      }

      return call({ ...payload, transaction_pin: pin });
    }
  };

  const handleError = (error: any, fallback: string) => {
    if (error?.cancelled) {
      return;
    }

    toast.error(error?.response?.data?.message || fallback);
  };

  const airtimeMutation = useMutation({
    mutationFn: (payload: AirtimePayload) =>
      withPinRetry(payload as any, (data) => api.buyAirtime(data as AirtimePayload)),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast.success(`Airtime purchase successful! Reference: ${response.data.reference}`);
    },
    onError: (error: any) => handleError(error, "Airtime purchase failed"),
  });

  const dataMutation = useMutation({
    mutationFn: (payload: DataPayload) =>
      withPinRetry(payload as any, (data) => api.buyData(data as DataPayload)),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast.success(`Data purchase successful! Reference: ${response.data.reference}`);
    },
    onError: (error: any) => handleError(error, "Data purchase failed"),
  });

  const electricityMutation = useMutation({
    mutationFn: (payload: ElectricityPayload) =>
      withPinRetry(payload as any, (data) => api.buyElectricity(data as ElectricityPayload)),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast.success(`Electricity payment successful! Reference: ${response.data.reference}`);
    },
    onError: (error: any) => handleError(error, "Electricity payment failed"),
  });

  const cableMutation = useMutation({
    mutationFn: (payload: CableTvPayload) =>
      withPinRetry(payload as any, (data) => api.buyCableTv(data as CableTvPayload)),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast.success(`Cable TV subscription successful! Reference: ${response.data.reference}`);
    },
    onError: (error: any) => handleError(error, "Cable TV subscription failed"),
  });

  const transactionsQuery = useQuery({
    queryKey: ["transactions"],
    queryFn: () => api.getTransactions({ limit: 10 }),
  });

  return {
    buyAirtime: airtimeMutation.mutate,
    isAirtimeLoading: airtimeMutation.isPending,
    airtimeSuccess: airtimeMutation.isSuccess,

    buyData: dataMutation.mutate,
    isDataLoading: dataMutation.isPending,
    dataSuccess: dataMutation.isSuccess,

    buyElectricity: electricityMutation.mutate,
    isElectricityLoading: electricityMutation.isPending,
    electricitySuccess: electricityMutation.isSuccess,

    buyCableTv: cableMutation.mutate,
    isCableLoading: cableMutation.isPending,
    cableSuccess: cableMutation.isSuccess,

    recentTransactions: transactionsQuery.data?.data?.items || [],
    isTransactionsLoading: transactionsQuery.isLoading,
  };
}
