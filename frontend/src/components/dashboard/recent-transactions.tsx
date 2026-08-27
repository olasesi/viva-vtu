"use client";

import { useRouter } from "next/navigation";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { getStatusColor, formatCurrency, formatDate } from "@/lib/utils";
import { getTransactionLabel } from "@/lib/utils";
import { usePurchase } from "@/hooks/use-purchase";
import { ArrowRight } from "lucide-react";

export function RecentTransactions() {
  const router = useRouter();
  const { recentTransactions, isTransactionsLoading } = usePurchase();

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <CardTitle className="text-lg font-semibold">Recent Transactions</CardTitle>
        <Button
          variant="ghost"
          size="sm"
          onClick={() => router.push("/dashboard/wallet/history")}
          className="text-primary"
        >
          View All <ArrowRight className="ml-1 h-4 w-4" />
        </Button>
      </CardHeader>
      <CardContent>
        {isTransactionsLoading ? (
          <div className="space-y-4">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="flex items-center justify-between">
                <div className="space-y-2">
                  <Skeleton className="h-4 w-32" />
                  <Skeleton className="h-3 w-24" />
                </div>
                <Skeleton className="h-4 w-20" />
              </div>
            ))}
          </div>
        ) : recentTransactions.length === 0 ? (
          <div className="text-center py-8">
            <p className="text-muted-foreground text-sm">No transactions yet</p>
            <p className="text-xs text-muted-foreground mt-1">
              Start by buying airtime or data
            </p>
          </div>
        ) : (
          <div className="space-y-4">
            {recentTransactions.slice(0, 5).map((txn) => (
              <div
                key={txn.id}
                className="flex items-center justify-between py-2 border-b last:border-0"
              >
                <div className="flex-1">
                  <p className="text-sm font-medium">{getTransactionLabel(txn.type)}</p>
                  <p className="text-xs text-muted-foreground">
                    {txn.reference} &middot; {formatDate(txn.createdAt)}
                  </p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-semibold">
                    {txn.type === "wallet_fund" ? "+" : "-"}{formatCurrency(txn.totalAmount)}
                  </p>
                  <Badge
                    variant="outline"
                    className={`text-[10px] mt-1 ${getStatusColor(txn.status)}`}
                  >
                    {txn.status}
                  </Badge>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
