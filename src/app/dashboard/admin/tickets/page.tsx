"use client";

import { useEffect, useState } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { getAdminTickets } from "@/lib/api";
import Link from "next/link";
import { MessageSquare } from "lucide-react";

export default function AdminTicketsPage() {
  const [tickets, setTickets] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAdminTickets()
      .then((res) => setTickets(res.data?.data || []))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Support Tickets</h1>
        <p className="text-muted-foreground">
          Manage customer support requests
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Tickets</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {tickets.map((ticket) => (
              <Link
                key={ticket.id}
                href={`/dashboard/admin/tickets/${ticket.id}`}
              >
                <div className="flex items-center justify-between rounded-md border p-4 hover:bg-muted/50 cursor-pointer">
                  <div className="flex items-center gap-3">
                    <MessageSquare className="h-5 w-5" />
                    <div>
                      <p className="font-medium">{ticket.subject}</p>
                      <p className="text-sm text-muted-foreground">
                        {ticket.ticket_number} • {ticket.user?.first_name}{" "}
                        {ticket.user?.last_name}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="text-sm px-2 py-1 rounded-full bg-muted">
                      {ticket.status.replace("_", " ")}
                    </span>
                    <span className="text-sm px-2 py-1 rounded-full bg-muted">
                      {ticket.priority}
                    </span>
                    <span className="text-sm px-2 py-1 rounded-full bg-muted">
                      {ticket.category}
                    </span>
                  </div>
                </div>
              </Link>
            ))}
            {!loading && tickets.length === 0 && (
              <p className="text-center text-muted-foreground py-8">
                No tickets
              </p>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
