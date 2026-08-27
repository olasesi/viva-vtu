"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api";
import type { AirtimePayload, DataPayload, ElectricityPayload, CableTvPayload } from "@/types";
import { toast } from "sonner";

export function usePurchase() {
  const queryClient = useQueryClient();

  const airtimeMutation = useMutation({
    mutationFn: (payload: AirtimePayload) => api.buyAirtime(payload),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast.success(`Airtime purchase successful! Reference: ${response.data.reference}`);
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || "Airtime purchase failed");
    },
  });

  const dataMutation = useMutation({
    mutationFn: (payload: DataPayload) => api.buyData(payload),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast.success(`Data purchase successful! Reference: ${response.data.reference}`);
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || "Data purchase failed");
    },
  });

  const electricityMutation = useMutation({
    mutationFn: (payload: ElectricityPayload) => api.buyElectricity(payload),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast.success(`Electricity payment successful! Reference: ${response.data.reference}`);
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || "Electricity payment failed");
    },
  });

  const cableMutation = useMutation({
    mutationFn: (payload: CableTvPayload) => api.buyCableTv(payload),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast.success(`Cable TV subscription successful! Reference: ${response.data.reference}`);
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || "Cable TV subscription failed");
    },
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
