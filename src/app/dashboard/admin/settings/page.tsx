"use client";

import { useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Separator } from "@/components/ui/separator";
import { SettingsGroupsNav } from "@/components/dashboard/settings/settings-groups";
import {
  SettingsForm,
  buildInitialValues,
  type SettingsValues,
} from "@/components/dashboard/settings/settings-form";
import {
  useAdminSettings,
  useAdminSettingsGroup,
  useAdminSettingsSchema,
  useUpdateSettingsGroup,
} from "@/hooks/use-settings";
import { toast } from "sonner";
import { Save, Settings } from "lucide-react";

export default function AdminSettingsPage() {
  const { data, isLoading } = useAdminSettings();
  const groups = data?.data ?? [];

  const [activeGroup, setActiveGroup] = useState<string>("");
  const [values, setValues] = useState<SettingsValues>({});
  const [savedValues, setSavedValues] = useState<SettingsValues>({});

  useEffect(() => {
    if (!activeGroup && groups.length > 0) {
      setActiveGroup(groups[0].group);
    }
  }, [groups, activeGroup]);

  const groupDetail = useAdminSettingsGroup(activeGroup || null);
  const schemaDetail = useAdminSettingsSchema(activeGroup || null);

  const schema = schemaDetail.data?.data;
  const schemaFields = schema?.fields ?? {};
  const fields = groupDetail.data?.data?.fields ?? {};

  const isDetailLoading = groupDetail.isLoading || schemaDetail.isLoading;

  useEffect(() => {
    if (!schema || isDetailLoading) {
      return;
    }

    const next = buildInitialValues(schemaFields, fields);
    setValues(next);
    setSavedValues(next);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeGroup, schema?.group, isDetailLoading]);

  const updateMutation = useUpdateSettingsGroup(activeGroup);

  const hasChanges = useMemo(() => {
    return Object.keys(values).some((key) => {
      return values[key] !== savedValues[key];
    });
  }, [values, savedValues]);

  const handleSave = async () => {
    const payload: Record<string, unknown> = {};

    for (const [key, definition] of Object.entries(schemaFields)) {
      const value = values[key];

      if (definition.type === "array") {
        try {
          payload[key] = JSON.parse(String(value ?? "[]"));
        } catch {
          toast.error(`${definition.label} must be valid JSON.`);
          return;
        }
      } else {
        payload[key] = value;
      }
    }

    updateMutation.mutate(payload, {
      onSuccess: (response) => {
        setSavedValues(buildInitialValues(schemaFields, response.data.fields));
        toast.success(
          response.message || `${schema?.label ?? activeGroup} settings saved`,
        );
      },
      onError: (error) => {
        const detail = error as {
          response?: {
            data?: { errors?: Record<string, string[]>; message?: string };
          };
        };
        const errors = detail.response?.data?.errors;

        if (errors) {
          const [first] = Object.values(errors);

          toast.error(first?.[0] ?? "Please fix the highlighted fields.");
        } else {
          toast.error(
            detail.response?.data?.message ?? "Failed to save settings.",
          );
        }
      },
    });
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-bold">
          <Settings className="h-6 w-6 text-primary" />
          Settings
        </h1>
        <p className="text-muted-foreground">
          Configure platform-wide defaults for brand, API, themes, layout, mail,
          security, notifications, payments and support.
        </p>
      </div>

      {isLoading ? (
        <div className="grid gap-6 md:grid-cols-[240px_1fr]">
          <div className="space-y-2">
            {[...Array(6)].map((_, index) => (
              <Skeleton key={index} className="h-10 w-full" />
            ))}
          </div>
          <Skeleton className="h-96 w-full" />
        </div>
      ) : (
        <div className="grid gap-6 md:grid-cols-[240px_1fr]">
          <SettingsGroupsNav
            groups={groups}
            activeGroup={activeGroup}
            onSelect={setActiveGroup}
          />

          <Card>
            <CardHeader>
              <div>
                <CardTitle>{schema?.label ?? activeGroup} settings</CardTitle>
                <CardDescription>
                  {groups.length} groups · {Object.keys(schemaFields).length}{" "}
                  fields in &quot;{schema?.label ?? activeGroup}&quot;. Fields
                  marked as sensitive are encrypted at rest and shown masked.
                </CardDescription>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              {isDetailLoading ? (
                <div className="space-y-6">
                  {[...Array(4)].map((_, index) => (
                    <Skeleton key={index} className="h-12 w-full" />
                  ))}
                </div>
              ) : (
                <>
                  <SettingsForm
                    schema={schemaFields}
                    values={values}
                    onChange={(key, value) =>
                      setValues((prev) => ({ ...prev, [key]: value }))
                    }
                  />
                  <Separator />
                  <div className="flex items-center justify-end gap-3">
                    <Button
                      onClick={handleSave}
                      disabled={!hasChanges || updateMutation.isPending}
                    >
                      <Save className="mr-2 h-4 w-4" />
                      {updateMutation.isPending ? "Saving..." : "Save changes"}
                    </Button>
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
