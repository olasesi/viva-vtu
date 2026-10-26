"use client";

import { useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { createDispute, getTransactions } from "@/lib/api";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";

export default function NewDisputePage() {
  const router = useRouter();
  const [transactions, setTransactions] = useState<any[]>([]);
  const [form, setForm] = useState({ transaction_id: "", reason: "debited_but_failed", description: "" });
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    getTransactions()
      .then((res: any) => setTransactions(res.data?.data || res.data || []))
      .catch(() => {});
  }, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await createDispute({ ...form, transaction_id: Number(form.transaction_id) });
      if (res.success) {
        toast.success("Dispute submitted");
        router.push("/dashboard/disputes");
      } else {
        toast.error(res.message || "Failed to submit dispute");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Link href="/dashboard/disputes">
          <Button variant="ghost" size="icon">
            <ArrowLeft className="h-4 w-4" />
          </Button>
        </Link>
        <div>
          <h1 className="text-2xl font-bold">Submit Dispute</h1>
          <p className="text-muted-foreground">Report an issue with a transaction</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Dispute Details</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="transaction">Transaction</Label>
              <select id="transaction" required className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2" value={form.transaction_id} onChange={(e) => setForm({ ...form, transaction_id: e.target.value })}>
                <option value="">Select transaction</option>
                {transactions.map((t: any) => (
                  <option key={t.id} value={t.id}>
                    {t.reference} - ₦{t.amount} - {t.status}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="reason">Reason</Label>
              <select id="reason" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2" value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })}>
                <option value="debited_but_failed">Debited but failed / not received</option>
                <option value="not_received">Not received</option>
                <option value="wrong_amount">Wrong amount</option>
                <option value="unauthorized">Unauthorized transaction</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="description">Description</Label>
              <Textarea id="description" required rows={4} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder={"Provide details about the issue..."} />
            </div>
            <Button type="submit" disabled={submitting}>{submitting ? "Submitting..." : "Submit Dispute"}</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
