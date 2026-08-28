"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Wallet, TrendingUp, ArrowUpRight } from "lucide-react";
import { formatCurrency } from "@/lib/utils";
import { useWallet } from "@/hooks/use-wallet";

export function WalletBalance() {
  const { balance, isBalanceLoading } = useWallet();

  if (isBalanceLoading) {
    return (
      <Card className="bg-gradient-to-br from-primary to-emerald-700 text-white border-0">
        <CardContent className="p-6">
          <Skeleton className="h-4 w-24 bg-white/20 mb-2" />
          <Skeleton className="h-10 w-48 bg-white/20 mb-4" />
          <Skeleton className="h-4 w-32 bg-white/20" />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="bg-gradient-to-br from-primary to-emerald-700 text-white border-0 overflow-hidden relative">
      <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16" />
      <div className="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full -ml-12 -mb-12" />
      <CardContent className="p-6 relative z-10">
        <div className="flex items-center justify-between mb-2">
          <p className="text-emerald-100 text-sm font-medium">Wallet Balance</p>
          <div className="h-10 w-10 rounded-full bg-white/20 flex items-center justify-center">
            <Wallet className="h-5 w-5" />
          </div>
        </div>
        <p className="text-3xl font-bold mb-1">{formatCurrency(balance?.balance || 0)}</p>
        <div className="flex items-center gap-1 text-emerald-200 text-sm">
          <TrendingUp className="h-4 w-4" />
          <span>Available for transactions</span>
        </div>
      </CardContent>
    </Card>
  );
}
