"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as api from "@/lib/api";
import type { SettingsGroupItem, SettingsGroupSchema } from "@/types";

export function useAdminSettings() {
  return useQuery({
    queryKey: ["admin-settings"],
    queryFn: () => api.getAdminSettings(),
  });
}

export function useAdminSettingsGroup(group: string | null) {
  return useQuery({
    queryKey: ["admin-settings", group],
    queryFn: () => api.getAdminSettingsGroup(group as string),
    enabled: !!group,
  });
}

export function useAdminSettingsSchema(group: string | null) {
  return useQuery({
    queryKey: ["admin-settings-schema", group],
    queryFn: () => api.getAdminSettingsSchema(group as string),
    enabled: !!group,
  });
}

export function useUpdateSettingsGroup(group: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: Record<string, unknown>) =>
      api.updateAdminSettingsGroup(group, fields),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin-settings"] });
      queryClient.invalidateQueries({ queryKey: ["admin-settings", group] });
      queryClient.invalidateQueries({
        queryKey: ["admin-settings-schema", group],
      });
      queryClient.invalidateQueries({ queryKey: ["public-settings"] });
    },
  });
}

export function usePublicSettings() {
  return useQuery({
    queryKey: ["public-settings"],
    queryFn: () => api.getPublicSettings(),
  });
}

export type { SettingsGroupItem, SettingsGroupSchema };
