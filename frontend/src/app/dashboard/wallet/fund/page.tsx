"use client";

import { FundWalletForm } from "@/components/forms/fund-wallet-form";

export default function FundWalletPage() {
  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold">Fund Wallet</h1>
        <p className="text-muted-foreground">
          Add money to your wallet using Paystack or Flutterwave.
        </p>
      </div>
      <FundWalletForm />
    </div>
  );
}
