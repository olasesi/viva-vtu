"use client";

import { useEffect } from "react";
import { useAuth } from "@/hooks/use-auth";
import { initializeAuth } from "@/stores/auth-store";
import { WalletBalance } from "@/components/dashboard/wallet-balance";
import { QuickActions } from "@/components/dashboard/quick-actions";
import { RecentTransactions } from "@/components/dashboard/recent-transactions";
import { StatsCards } from "@/components/dashboard/stats-cards";

export default function DashboardPage() {
  const { user } = useAuth();

  useEffect(() => {
    initializeAuth();
  }, []);

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold">
          Welcome back, {user?.firstName || "User"} 👋
        </h1>
        <p className="text-muted-foreground">
          Here&apos;s what&apos;s happening with your account today.
        </p>
      </div>

      <WalletBalance />
      <QuickActions />

      <div className="grid gap-6 lg:grid-cols-2">
        <RecentTransactions />

        <div className="space-y-6">
          <StatsCards
            totalUsers={1250}
            totalTransactions={8430}
            totalRevenue={2500000}
            successRate={99.2}
          />
        </div>
      </div>
    </div>
  );
}
