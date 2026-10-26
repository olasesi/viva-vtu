"use client";

import { useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { getAdminDisputes } from "@/lib/api";
import Link from "next/link";
import { AlertCircle } from "lucide-react";

export default function AdminDisputesPage() {
  const [disputes, setDisputes] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAdminDisputes()
      .then((res) => setDisputes(res.data?.data || []))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Transaction Disputes</h1>
        <p className="text-muted-foreground">Review and resolve customer disputes</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Disputes</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {disputes.map((dispute) => (
            <Link key={dispute.id} href={`/dashboard/admin/disputes/${dispute.id}`}>
              <div className="flex items-center justify-between rounded-md border p-4 hover:bg-muted/50 cursor-pointer">
                <div className="flex items-center gap-3">
                  <AlertCircle className="h-5 w-5" />
                  <div>
                    <p className="font-medium">{dispute.reference}</p>
                    <p className="text-sm text-muted-foreground">{dispute.user?.first_name} {dispute.user?.last_name} • {dispute.reason.replace("_", " ")}</p>
                  </div>
                </div>
                <span className="text-sm px-2 py-1 rounded-full bg-muted">{dispute.status.replace("_", " ")}</span>
              </div>
            </Link>
          ))}
          {!loading && disputes.length === 0 && <p className="text-center text-muted-foreground py-8">No disputes</p>}
        </CardContent>
      </Card>
    </div>
  );
}
