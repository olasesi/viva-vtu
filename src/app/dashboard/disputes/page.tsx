"use client";

import { useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { getDisputes } from "@/lib/api";
import Link from "next/link";
import { AlertCircle, Plus } from "lucide-react";

export default function DisputesPage() {
  const [disputes, setDisputes] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getDisputes()
      .then((res) => setDisputes(res.data?.data || []))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Transaction Disputes</h1>
          <p className="text-muted-foreground">Report and track transaction issues</p>
        </div>
        <Link href="/dashboard/disputes/new">
          <Button>
            <Plus className="h-4 w-4 mr-2" />
            New Dispute
          </Button>
        </Link>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>My Disputes</CardTitle>
          <CardDescription>All disputes you've submitted</CardDescription>
        </CardHeader>
        <CardContent className="space-y-2">
          {disputes.map((dispute) => (
            <Link key={dispute.id} href={`/dashboard/disputes/${dispute.id}`}>
              <div className="flex items-center justify-between rounded-md border p-4 hover:bg-muted/50 cursor-pointer">
                <div className="flex items-center gap-3">
                  <AlertCircle className="h-5 w-5" />
                  <div>
                    <p className="font-medium">{dispute.reference}</p>
                    <p className="text-sm text-muted-foreground">{dispute.reason.replace("_", " ")}</p>
                  </div>
                </div>
                <span className="text-sm px-2 py-1 rounded-full bg-muted">{dispute.status.replace("_", " ")}</span>
              </div>
            </Link>
          ))}
          {!loading && disputes.length === 0 && <p className="text-center text-muted-foreground py-8">No disputes yet</p>}
        </CardContent>
      </Card>
    </div>
  );
}
