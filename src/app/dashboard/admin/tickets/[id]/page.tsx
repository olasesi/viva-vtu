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
import { Label } from "@/components/ui/label";
import { getAdminTicket, replyAdminTicket, updateAdminTicket } from "@/lib/api";
import { ArrowLeft, Send } from "lucide-react";
import Link from "next/link";
import { toast } from "sonner";

export default function AdminTicketDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const [ticket, setTicket] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");
  const [internal, setInternal] = useState(false);
  const [status, setStatus] = useState("");
  const [sending, setSending] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await getAdminTicket(id);
      setTicket(res.data ?? null);
      setStatus(res.data?.status || "in_progress");
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
      const res = await replyAdminTicket(id, { message, status, internal });
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

  const update = async (newStatus: string) => {
    try {
      await updateAdminTicket(id, { status: newStatus });
      toast.success("Ticket updated");
      load();
    } catch {
      toast.error("Failed to update ticket");
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Link href="/dashboard/admin/tickets">
          <Button variant="ghost" size="icon">
            <ArrowLeft className="h-4 w-4" />
          </Button>
        </Link>
        <div className="flex-1">
          <h1 className="text-2xl font-bold">{ticket?.subject || "Ticket"}</h1>
          <p className="text-muted-foreground">
            {ticket?.ticket_number} • {ticket?.user?.email}
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            size="sm"
            variant="outline"
            onClick={() => update("in_progress")}
          >
            In Progress
          </Button>
          <Button
            size="sm"
            variant="outline"
            onClick={() => update("resolved")}
          >
            Resolve
          </Button>
          <Button size="sm" variant="outline" onClick={() => update("closed")}>
            Close
          </Button>
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
              className={`rounded-lg p-4 ${msg.is_internal_note ? "bg-yellow-50 border border-yellow-200" : msg.user?.role === "admin" ? "bg-blue-50 border border-blue-200" : "bg-muted"}`}
            >
              <div className="flex items-center justify-between mb-2">
                <p className="font-medium">
                  {msg.user?.first_name || "User"} {msg.user?.last_name || ""}{" "}
                  {msg.user?.role === "admin" && "(Admin)"}
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

      <Card>
        <CardHeader>
          <CardTitle>Reply to Ticket</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={reply} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="status">Update Status</Label>
              <select
                id="status"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2"
                value={status}
                onChange={(e) => setStatus(e.target.value)}
              >
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="awaiting_response">Awaiting Response</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <Textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Type your message..."
              rows={4}
            />
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="internal"
                checked={internal}
                onChange={(e) => setInternal(e.target.checked)}
              />
              <Label htmlFor="internal">
                Internal note (only visible to admins)
              </Label>
            </div>
            <Button type="submit" disabled={sending}>
              <Send className="h-4 w-4 mr-2" />
              {sending ? "Sending..." : "Send Reply"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
