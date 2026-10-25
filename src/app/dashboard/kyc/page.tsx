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
import { getKycStatus, submitKyc } from "@/lib/api";
import { ShieldCheck, ShieldAlert } from "lucide-react";
import { toast } from "sonner";

export default function KycPage() {
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [data, setData] = useState<{
    level: number;
    verified: boolean;
    verifiedAt?: string;
    bvn?: string | null;
    nin?: string | null;
  } | null>(null);
  const [bvn, setBvn] = useState("");
  const [nin, setNin] = useState("");

  const load = () => {
    setLoading(true);
    getKycStatus()
      .then((res) => setData(res.data ?? null))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!bvn && !nin) return toast.error("Provide either BVN or NIN");
    setSubmitting(true);
    try {
      const res = await submitKyc({
        bvn: bvn || undefined,
        nin: nin || undefined,
      });
      if (res.success) {
        toast.success(res.message || "KYC updated");
        setBvn("");
        setNin("");
        load();
      } else {
        toast.error(res.message || "Failed to submit KYC");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">KYC Verification</h1>
        <p className="text-muted-foreground">
          Verify your identity with BVN or NIN
        </p>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center gap-3">
          {data?.verified ? (
            <ShieldCheck className="h-8 w-8 text-green-500" />
          ) : (
            <ShieldAlert className="h-8 w-8 text-amber-500" />
          )}
          <div>
            <CardTitle>Verification Status</CardTitle>
            <CardDescription>
              {loading
                ? "Loading..."
                : data?.verified
                  ? `Verified - Level ${data.level}`
                  : "Not yet verified"}
            </CardDescription>
          </div>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2">
          <div>
            <p className="text-sm text-muted-foreground">BVN</p>
            <p className="font-medium">
              {loading ? "-" : data?.bvn || "Not provided"}
            </p>
          </div>
          <div>
            <p className="text-sm text-muted-foreground">NIN</p>
            <p className="font-medium">
              {loading ? "-" : data?.nin || "Not provided"}
            </p>
          </div>
        </CardContent>
      </Card>

      {data?.level < 2 && (
        <Card>
          <CardHeader>
            <CardTitle>Submit KYC Details</CardTitle>
            <CardDescription>
              Your details are hashed and never stored in plain text
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="bvn">BVN (11 digits)</Label>
                <Input
                  id="bvn"
                  maxLength={11}
                  value={bvn}
                  onChange={(e) => setBvn(e.target.value.replace(/\D/g, ""))}
                  placeholder="20345678901"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="nin">NIN (11 digits)</Label>
                <Input
                  id="nin"
                  maxLength={11}
                  value={nin}
                  onChange={(e) => setNin(e.target.value.replace(/\D/g, ""))}
                  placeholder="12345678901"
                />
              </div>
              <div className="md:col-span-2">
                <Button type="submit" disabled={submitting}>
                  {submitting ? "Submitting..." : "Submit for Verification"}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
