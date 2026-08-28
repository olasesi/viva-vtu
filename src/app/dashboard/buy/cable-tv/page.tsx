"use client";

import { CableTvForm } from "@/components/forms/cable-tv-form";

export default function CableTvPage() {
  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold">Cable TV Subscription</h1>
        <p className="text-muted-foreground">
          Subscribe to DStv, GOtv, or StarTimes. Choose your preferred package.
        </p>
      </div>
      <CableTvForm />
    </div>
  );
}
