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
import { getReferralStatus } from "@/lib/api";
import { Copy, Gift, Users, Wallet } from "lucide-react";
import { toast } from "sonner";

export default function ReferralPage() {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<{
    code: string;
    link: string;
    totalEarned: number;
    referralsCount: number;
  } | null>(null);

  useEffect(() => {
    getReferralStatus()
      .then((res) => setData(res.data ?? null))
      .finally(() => setLoading(false));
  }, []);

  const copy = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success("Copied to clipboard");
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Referrals</h1>
        <p className="text-muted-foreground">
          Earn commissions when people you refer buy utilities
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Referral Code</CardTitle>
            <Gift className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              <code className="text-lg font-bold">
                {loading ? "..." : data?.code}
              </code>
              <Button
                size="icon"
                variant="ghost"
                onClick={() => data?.code && copy(data.code)}
              >
                <Copy className="h-4 w-4" />
              </Button>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Referrals</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {loading ? "..." : (data?.referralsCount ?? 0)}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Earned</CardTitle>
            <Wallet className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              ₦{(loading ? 0 : (data?.totalEarned ?? 0)).toLocaleString()}
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Your Referral Link</CardTitle>
          <CardDescription>
            Share this link with friends and family
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex gap-2">
            <Input readOnly value={loading ? "" : data?.link || ""} />
            <Button onClick={() => data?.link && copy(data.link)}>
              Copy Link
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
