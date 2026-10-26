"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getAdminDispute, updateAdminDispute } from "@/lib/api";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import { toast } from "sonner";

export default function AdminDisputeDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const [dispute, setDispute] = useState<any>(null);
  const [status, setStatus] = useState("under_review");
  const [adminNote, setAdminNote] = useState("");
  const [saving, setSaving] = useState(false);

  const load = async () => {
    const res = await getAdminDispute(id);
    setDispute(res.data ?? null);
    setStatus(res.data?.status || "under_review");
    setAdminNote(res.data?.admin_note || "");
  };

  useEffect(() => {
    load();
  }, [id]);

  const save = async () => {
    setSaving(true);
    try {
      const res = await updateAdminDispute(id, { status, admin_note: adminNote });
      if (res.success) {
        toast.success("Dispute updated");
        load();
      } else {
        toast.error(res.message || "Failed to update");
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Link href="/dashboard/admin/disputes">
          <Button variant="ghost" size="icon">
            <ArrowLeft className="h-4 w-4" />
          </Button>
        </Link>
        <div>
          <h1 className="text-2xl font-bold">Dispute #{dispute?.id}</h1>
          <p className="text-muted-foreground">{dispute?.user?.email}</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Dispute Details</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <p><strong>Reference:</strong> {dispute?.reference}</p>
          <p><strong>Reason:</strong> {dispute?.reason?.replace("_", " ")}</p>
          <p><strong>Description:</strong> {dispute?.description}</p>
          <p><strong>Amount:</strong> ₦{dispute?.transaction?.amount}</p>
          <p><strong>Status:</strong> {dispute?.status?.replace("_", " ")}</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Update Dispute</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>Status</Label>
            <select className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2" value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="open">Open</option>
              <option value="under_review">Under Review</option>
              <option value="resolved">Resolved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div className="space-y-2">
            <Label>Admin Note</Label>
            <Textarea value={adminNote} onChange={(e) => setAdminNote(e.target.value)} rows={4} />
          </div>
          <Button onClick={save} disabled={saving}>{saving ? "Saving..." : "Update Dispute"}</Button>
        </CardContent>
      </Card>
    </div>
  );
}
