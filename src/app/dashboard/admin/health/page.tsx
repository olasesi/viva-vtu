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
import { getAggregatorHealth } from "@/lib/api";
import { Activity, CheckCircle, XCircle } from "lucide-react";

export default function AdminHealthPage() {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<any>(null);

  useEffect(() => {
    getAggregatorHealth()
      .then((res) => setData(res.data ?? null))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Aggregator Health</h1>
        <p className="text-muted-foreground">
          Circuit breaker status and failures
        </p>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center gap-3">
          <Activity className="h-6 w-6" />
          <div>
            <CardTitle>Providers</CardTitle>
            <CardDescription>
              Unhealthy: {data?.unhealthy?.length || 0}
            </CardDescription>
          </div>
        </CardHeader>
        <CardContent>
          <div className="grid gap-2 md:grid-cols-2">
            {Object.entries(data?.providers || {}).map(
              ([slug, info]: [string, any]) => (
                <div
                  key={slug}
                  className="flex items-center justify-between rounded-md border p-3"
                >
                  <div className="flex items-center gap-2">
                    {info.healthy ? (
                      <CheckCircle className="h-4 w-4 text-green-500" />
                    ) : (
                      <XCircle className="h-4 w-4 text-red-500" />
                    )}
                    <span className="font-medium">{slug}</span>
                  </div>
                  <div className="text-sm text-muted-foreground">
                    {info.enabled ? "enabled" : "disabled"} •{" "}
                    {info.consecutive_failures} failures
                  </div>
                </div>
              ),
            )}
            {!loading && Object.keys(data?.providers || {}).length === 0 && (
              <p className="text-sm text-muted-foreground">No data</p>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
