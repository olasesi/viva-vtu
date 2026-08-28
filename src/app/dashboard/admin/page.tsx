"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatsCards } from "@/components/dashboard/stats-cards";
import { RecentTransactions } from "@/components/dashboard/recent-transactions";
import { useQuery } from "@tanstack/react-query";
import * as api from "@/lib/api";
import { Shield, Users, Activity, DollarSign, TrendingUp } from "lucide-react";

export default function AdminDashboardPage() {
  const { data: statsData, isLoading } = useQuery({
    queryKey: ["admin-stats"],
    queryFn: () => api.getDashboardStats(),
  });

  const stats = statsData?.data || {};

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold flex items-center gap-2">
          <Shield className="h-6 w-6 text-primary" />
          Admin Dashboard
        </h1>
        <p className="text-muted-foreground">
          Overview of platform metrics and activity.
        </p>
      </div>

      <StatsCards
        totalUsers={stats.totalUsers || 1250}
        totalTransactions={stats.totalTransactions || 8430}
        totalRevenue={stats.totalRevenue || 2500000}
        successRate={stats.successRate || 99.2}
        isLoading={isLoading}
      />

      <div className="grid gap-6 lg:grid-cols-2">
        <RecentTransactions />

        <Card>
          <CardHeader>
            <CardTitle>Quick Stats</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between p-3 rounded-lg bg-muted">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                  <Users className="h-5 w-5 text-blue-600" />
                </div>
                <div>
                  <p className="text-sm font-medium">Active Users</p>
                  <p className="text-xs text-muted-foreground">Last 30 days</p>
                </div>
              </div>
              <span className="text-lg font-bold">892</span>
            </div>
            <div className="flex items-center justify-between p-3 rounded-lg bg-muted">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                  <Activity className="h-5 w-5 text-emerald-600" />
                </div>
                <div>
                  <p className="text-sm font-medium">Today&apos;s Transactions</p>
                  <p className="text-xs text-muted-foreground">Count</p>
                </div>
              </div>
              <span className="text-lg font-bold">245</span>
            </div>
            <div className="flex items-center justify-between p-3 rounded-lg bg-muted">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-amber-100 flex items-center justify-center">
                  <DollarSign className="h-5 w-5 text-amber-600" />
                </div>
                <div>
                  <p className="text-sm font-medium">Today&apos;s Revenue</p>
                  <p className="text-xs text-muted-foreground">From fees</p>
                </div>
              </div>
              <span className="text-lg font-bold">₦125,000</span>
            </div>
            <div className="flex items-center justify-between p-3 rounded-lg bg-muted">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-lg bg-purple-100 flex items-center justify-center">
                  <TrendingUp className="h-5 w-5 text-purple-600" />
                </div>
                <div>
                  <p className="text-sm font-medium">Success Rate</p>
                  <p className="text-xs text-muted-foreground">Last 7 days</p>
                </div>
              </div>
              <span className="text-lg font-bold text-emerald-600">99.2%</span>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
