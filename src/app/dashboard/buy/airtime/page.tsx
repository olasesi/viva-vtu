"use client";

import { AirtimeForm } from "@/components/forms/airtime-form";

export default function BuyAirtimePage() {
  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold">Buy Airtime</h1>
        <p className="text-muted-foreground">
          Purchase airtime for any Nigerian network instantly.
        </p>
      </div>
      <AirtimeForm />
    </div>
  );
}
