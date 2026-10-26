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
import { Bell, CheckCheck } from "lucide-react";
import {
  getNotifications,
  markAllNotificationsAsRead,
  markNotificationAsRead,
} from "@/lib/api";
import { toast } from "sonner";

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const res = await getNotifications();
      setNotifications(res.data?.data || res.data || []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const markAll = async () => {
    await markAllNotificationsAsRead();
    toast.success("All notifications marked as read");
    load();
  };

  const markRead = async (id: string) => {
    await markNotificationAsRead(id);
    load();
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Notifications</h1>
          <p className="text-muted-foreground">
            Stay updated on your transactions
          </p>
        </div>
        <Button onClick={markAll} variant="outline">
          <CheckCheck className="h-4 w-4 mr-2" />
          Mark all as read
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Notifications</CardTitle>
          <CardDescription>Your recent updates</CardDescription>
        </CardHeader>
        <CardContent className="space-y-2">
          {notifications.map((n: any) => (
            <div
              key={n.id}
              onClick={() => markRead(n.id)}
              className={`flex items-start gap-3 rounded-md border p-4 cursor-pointer hover:bg-muted/50 ${!n.read_at ? "bg-blue-50/50" : ""}`}
            >
              <Bell className="h-5 w-5 mt-0.5" />
              <div className="flex-1">
                <p className="font-medium">{n.data?.title}</p>
                <p className="text-sm text-muted-foreground">{n.data?.body}</p>
                <p className="text-xs text-muted-foreground mt-1">
                  {new Date(n.created_at).toLocaleString()}
                </p>
              </div>
              {!n.read_at && (
                <span className="h-2 w-2 rounded-full bg-blue-500" />
              )}
            </div>
          ))}
          {!loading && notifications.length === 0 && (
            <p className="text-center text-muted-foreground py-8">
              No notifications
            </p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
