"use client";

import { useEffect, useState } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  getResellerHierarchy,
  getResellerWebhooks,
  createResellerWebhook,
  deleteResellerWebhook,
} from "@/lib/api";
import { toast } from "sonner";
import { Network, Webhook, Trash2 } from "lucide-react";

export default function ResellerPage() {
  const [hierarchy, setHierarchy] = useState<any>(null);
  const [webhooks, setWebhooks] = useState<any[]>([]);
  const [url, setUrl] = useState("");
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const [h, w] = await Promise.all([
        getResellerHierarchy(),
        getResellerWebhooks(),
      ]);
      setHierarchy(h.data ?? null);
      setWebhooks(w.data ?? []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const create = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!url) return;
    try {
      const res = await createResellerWebhook({ url });
      if (res.success) {
        toast.success("Webhook created. Copy the secret from the response.");
        setUrl("");
        load();
      } else {
        toast.error(res.message || "Failed to create webhook");
      }
    } catch {
      toast.error("Failed to create webhook");
    }
  };

  const remove = async (id: number) => {
    try {
      await deleteResellerWebhook(id);
      toast.success("Webhook removed");
      load();
    } catch {
      toast.error("Failed to remove webhook");
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Reseller Panel</h1>
        <p className="text-muted-foreground">
          Manage your downline and webhook subscriptions
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader className="flex flex-row items-center gap-3">
            <Network className="h-6 w-6" />
            <div>
              <CardTitle>Hierarchy</CardTitle>
              <CardDescription>
                Downline: {hierarchy?.downlineCount ?? 0}
              </CardDescription>
            </div>
          </CardHeader>
          <CardContent>
            {hierarchy?.parent && (
              <p className="text-sm mb-2">Parent: {hierarchy.parent.name}</p>
            )}
            <div className="space-y-2">
              {(hierarchy?.downline || []).map((m: any) => (
                <div
                  key={m.id}
                  className="flex justify-between rounded-md border p-2 text-sm"
                >
                  <span>{m.name}</span>
                  <span className="text-muted-foreground">
                    {(m.marginRate * 100).toFixed(2)}% margin
                  </span>
                </div>
              ))}
              {!loading && (hierarchy?.downline || []).length === 0 && (
                <p className="text-sm text-muted-foreground">No downline yet</p>
              )}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center gap-3">
            <Webhook className="h-6 w-6" />
            <div>
              <CardTitle>Webhooks</CardTitle>
              <CardDescription>Get order events in real-time</CardDescription>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <form onSubmit={create} className="flex gap-2">
              <Input
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="https://your-app.com/viva-webhook"
              />
              <Button type="submit">Add</Button>
            </form>
            <div className="space-y-2">
              {webhooks.map((w) => (
                <div
                  key={w.id}
                  className="flex items-center justify-between rounded-md border p-2 text-sm"
                >
                  <span className="truncate">{w.url}</span>
                  <Button
                    size="icon"
                    variant="ghost"
                    onClick={() => remove(w.id)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              ))}
              {!loading && webhooks.length === 0 && (
                <p className="text-sm text-muted-foreground">
                  No webhooks configured
                </p>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
