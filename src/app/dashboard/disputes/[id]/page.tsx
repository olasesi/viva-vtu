"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { getDispute } from "@/lib/api";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";

export default function DisputeDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const [dispute, setDispute] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getDispute(id)
      .then((res) => setDispute(res.data ?? null))
      .finally(() => setLoading(false));
  }, [id]);

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Link href="/dashboard/disputes">
          <Button variant="ghost" size="icon">
            <ArrowLeft className="h-4 w-4" />
          </Button>
        </Link>
        <div>
          <h1 className="text-2xl font-bold">Dispute #{dispute?.id}</h1>
          <p className="text-muted-foreground">{dispute?.status?.replace("_", " ")}</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Details</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <p><strong>Reference:</strong> {dispute?.reference}</p>
          <p><strong>Reason:</strong> {dispute?.reason?.replace("_", " ")}</p>
          <p><strong>Description:</strong> {dispute?.description}</p>
          <p><strong>Status:</strong> {dispute?.status?.replace("_", " ")}</p>
          {dispute?.admin_note && <p><strong>Admin Note:</strong> {dispute?.admin_note}</p>}
        </CardContent>
      </Card>
    </div>
  );
}
