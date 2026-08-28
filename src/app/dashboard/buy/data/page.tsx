"use client";

import { DataForm } from "@/components/forms/data-form";

export default function BuyDataPage() {
  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold">Buy Data</h1>
        <p className="text-muted-foreground">
          Purchase data bundles for MTN, Airtel, Glo, and 9mobile.
        </p>
      </div>
      <DataForm />
    </div>
  );
}
