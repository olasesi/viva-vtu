"use client";

import { useEffect, useState } from "react";
import { Bell } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  getNotifications,
  getUnreadNotificationCount,
  markAllNotificationsAsRead,
  markNotificationAsRead,
} from "@/lib/api";
import { useRouter } from "next/navigation";

export function NotificationBell() {
  const [unread, setUnread] = useState(0);
  const [notifications, setNotifications] = useState<any[]>([]);
  const router = useRouter();

  const load = async () => {
    try {
      const [countRes, notifRes] = await Promise.all([
        getUnreadNotificationCount(),
        getNotifications(),
      ]);
      setUnread(countRes.unread_count ?? 0);
      setNotifications(notifRes.data?.data || notifRes.data || []);
    } catch {}
  };

  useEffect(() => {
    load();
    const interval = setInterval(load, 60000);
    return () => clearInterval(interval);
  }, []);

  const markAll = async () => {
    await markAllNotificationsAsRead();
    setUnread(0);
    load();
  };

  const markRead = async (id: string) => {
    await markNotificationAsRead(id);
    setUnread((u) => Math.max(0, u - 1));
    load();
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="h-5 w-5" />
          {unread > 0 && (
            <span className="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-red-500 text-[10px] text-white flex items-center justify-center">
              {unread > 9 ? "9+" : unread}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuLabel className="flex items-center justify-between">
          Notifications
          {unread > 0 && (
            <Button variant="ghost" size="sm" onClick={markAll}>
              Mark all read
            </Button>
          )}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        {notifications.slice(0, 5).map((n: any) => (
          <DropdownMenuItem
            key={n.id}
            className="flex flex-col items-start gap-1 cursor-pointer"
            onClick={() => markRead(n.id)}
          >
            <p className="font-medium text-sm">{n.data?.title}</p>
            <p className="text-xs text-muted-foreground">{n.data?.body}</p>
            {!n.read_at && <span className="text-xs text-blue-500">New</span>}
          </DropdownMenuItem>
        ))}
        {notifications.length === 0 && (
          <DropdownMenuItem disabled>No notifications</DropdownMenuItem>
        )}
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={() => router.push("/dashboard/notifications")}
        >
          View all
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
