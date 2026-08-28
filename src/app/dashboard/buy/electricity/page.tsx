"use client";

import { ElectricityForm } from "@/components/forms/electricity-form";

export default function ElectricityPage() {
  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold">Pay Electricity Bill</h1>
        <p className="text-muted-foreground">
          Pay your electricity bills instantly to any distribution company.
        </p>
      </div>
      <ElectricityForm />
    </div>
  );
}
