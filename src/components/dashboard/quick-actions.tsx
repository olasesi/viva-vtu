"use client";

import { useRouter } from "next/navigation";
import { Card, CardContent } from "@/components/ui/card";
import { Wifi, Phone, Zap, Tv } from "lucide-react";

const actions = [
  {
    title: "Buy Data",
    description: "MTN, Airtel, Glo, 9mobile",
    icon: Wifi,
    href: "/dashboard/buy/data",
    color: "bg-blue-50 text-blue-600",
    iconBg: "bg-blue-100",
  },
  {
    title: "Buy Airtime",
    description: "All networks available",
    icon: Phone,
    href: "/dashboard/buy/airtime",
    color: "bg-emerald-50 text-emerald-600",
    iconBg: "bg-emerald-100",
  },
  {
    title: "Electricity",
    description: "Pay electricity bills",
    icon: Zap,
    href: "/dashboard/buy/electricity",
    color: "bg-amber-50 text-amber-600",
    iconBg: "bg-amber-100",
  },
  {
    title: "Cable TV",
    description: "DStv, GOtv, StarTimes",
    icon: Tv,
    href: "/dashboard/buy/cable-tv",
    color: "bg-purple-50 text-purple-600",
    iconBg: "bg-purple-100",
  },
];

export function QuickActions() {
  const router = useRouter();

  return (
    <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
      {actions.map((action) => (
        <Card
          key={action.title}
          className="cursor-pointer hover:shadow-md transition-all hover:-translate-y-0.5 border"
          onClick={() => router.push(action.href)}
        >
          <CardContent className="p-4 flex flex-col items-center text-center gap-3">
            <div className={`h-12 w-12 rounded-xl ${action.iconBg} flex items-center justify-center`}>
              <action.icon className={`h-6 w-6 ${action.color.split(" ")[1]}`} />
            </div>
            <div>
              <p className="font-semibold text-sm">{action.title}</p>
              <p className="text-xs text-muted-foreground">{action.description}</p>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
