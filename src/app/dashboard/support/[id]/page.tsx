"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { getTicket, replyTicket } from "@/lib/api";
import { ArrowLeft, Send } from "lucide-react";
import Link from "next/link";
import { toast } from "sonner";

export default function TicketDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const [ticket, setTicket] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");
  const [sending, setSending] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await getTicket(id);
      setTicket(res.data ?? null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [id]);

  const reply = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!message) return;
    setSending(true);
    try {
      const res = await replyTicket(id, { message });
      if (res.success) {
        toast.success("Reply sent");
        setMessage("");
        load();
      } else {
        toast.error(res.message || "Failed to send reply");
      }
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Link href="/dashboard/support">
          <Button variant="ghost" size="icon">
            <ArrowLeft className="h-4 w-4" />
          </Button>
        </Link>
        <div>
          <h1 className="text-2xl font-bold">{ticket?.subject || "Ticket"}</h1>
          <p className="text-muted-foreground">
            {ticket?.ticket_number} • {ticket?.status?.replace("_", " ")}
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Conversation</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {(ticket?.messages || []).map((msg: any) => (
            <div
              key={msg.id}
              className={`rounded-lg p-4 ${msg.is_internal_note ? "bg-yellow-50 border border-yellow-200" : "bg-muted"}`}
            >
              <div className="flex items-center justify-between mb-2">
                <p className="font-medium">
                  {msg.user?.first_name || "User"} {msg.user?.last_name || ""}
                </p>
                <p className="text-xs text-muted-foreground">
                  {new Date(msg.created_at).toLocaleString()}
                </p>
              </div>
              <p className="whitespace-pre-wrap">{msg.message}</p>
              {msg.is_internal_note && (
                <p className="text-xs text-yellow-700 mt-2">Internal note</p>
              )}
            </div>
          ))}
        </CardContent>
      </Card>

      {ticket?.status !== "closed" && ticket?.status !== "resolved" && (
        <Card>
          <CardHeader>
            <CardTitle>Reply</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={reply} className="space-y-4">
              <Textarea
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                placeholder="Type your message..."
                rows={4}
              />
              <Button type="submit" disabled={sending}>
                <Send className="h-4 w-4 mr-2" />
                {sending ? "Sending..." : "Send Reply"}
              </Button>
            </form>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
