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
import { assignReseller } from "@/lib/api";
import { toast } from "sonner";

export default function AdminResellersPage() {
  const [resellerId, setResellerId] = useState("");
  const [parentId, setParentId] = useState("");
  const [marginRate, setMarginRate] = useState("0.01");
  const [submitting, setSubmitting] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await assignReseller({
        reseller_id: Number(resellerId),
        parent_id: Number(parentId),
        margin_rate: Number(marginRate),
      });
      if (res.success) {
        toast.success("Reseller assigned");
        setResellerId("");
        setParentId("");
      } else {
        toast.error(res.message || "Failed to assign");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Reseller Hierarchy</h1>
        <p className="text-muted-foreground">
          Assign downlines and set margin rates
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Assign Reseller</CardTitle>
          <CardDescription>
            Attach a reseller to a parent with a commission margin
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="resellerId">Reseller User ID</Label>
              <Input
                id="resellerId"
                value={resellerId}
                onChange={(e) => setResellerId(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="parentId">Parent User ID</Label>
              <Input
                id="parentId"
                value={parentId}
                onChange={(e) => setParentId(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="marginRate">Margin Rate (e.g. 0.05 = 5%)</Label>
              <Input
                id="marginRate"
                value={marginRate}
                onChange={(e) => setMarginRate(e.target.value)}
              />
            </div>
            <div className="md:col-span-3">
              <Button type="submit" disabled={submitting}>
                {submitting ? "Assigning..." : "Assign"}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
