"use client";

import { useCallback, useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { getAuditLogs } from "@/lib/api";
import { Activity, ChevronLeft, ChevronRight, ScrollText } from "lucide-react";

export default function AdminAuditLogsPage() {
  const [logs, setLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [filters, setFilters] = useState({
    log_name: "",
    event: "",
    search: "",
    from: "",
    to: "",
  });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getAuditLogs({
        page,
        limit: 25,
        log_name: filters.log_name || undefined,
        event: filters.event || undefined,
        search: filters.search || undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
      });
      setLogs(res.data?.data || []);
      setLastPage(res.data?.last_page || 1);
    } catch {
      setLogs([]);
    } finally {
      setLoading(false);
    }
  }, [page, filters]);

  useEffect(() => {
    load();
  }, [load]);

  const applyFilters = (event: React.FormEvent) => {
    event.preventDefault();
    setPage(1);
    load();
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Audit Logs</h1>
        <p className="text-muted-foreground">
          A chronological record of administrative actions
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
          <CardDescription>Narrow down the audit trail</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={applyFilters} className="grid gap-3 md:grid-cols-5">
            <div className="space-y-2">
              <Label htmlFor="log_name">Log</Label>
              <select
                id="log_name"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={filters.log_name}
                onChange={(event) => setFilters({ ...filters, log_name: event.target.value })}
              >
                <option value="">All</option>
                <option value="admin">Admin</option>
                <option value="user">User</option>
                <option value="default">Default</option>
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="event">Event</Label>
              <select
                id="event"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={filters.event}
                onChange={(event) => setFilters({ ...filters, event: event.target.value })}
              >
                <option value="">All</option>
                <option value="post">POST</option>
                <option value="patch">PATCH</option>
                <option value="put">PUT</option>
                <option value="delete">DELETE</option>
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="search">Search</Label>
              <Input
                id="search"
                value={filters.search}
                onChange={(event) => setFilters({ ...filters, search: event.target.value })}
                placeholder="Description or payload"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="from">From</Label>
              <Input
                id="from"
                type="date"
                value={filters.from}
                onChange={(event) => setFilters({ ...filters, from: event.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="to">To</Label>
              <Input
                id="to"
                type="date"
                value={filters.to}
                onChange={(event) => setFilters({ ...filters, to: event.target.value })}
              />
            </div>
            <div className="md:col-span-5">
              <Button type="submit">Apply Filters</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ScrollText className="h-5 w-5" />
            Activity
          </CardTitle>
          <CardDescription>Most recent actions first</CardDescription>
        </CardHeader>
        <CardContent className="space-y-2">
          {logs.map((log) => (
            <div key={log.id} className="rounded-md border p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                  <Activity className="h-4 w-4 text-muted-foreground" />
                  <div>
                    <p className="font-medium">{log.description}</p>
                    <p className="text-sm text-muted-foreground">
                      {log.causer
                        ? `${log.causer.first_name} ${log.causer.last_name} (${log.causer.email})`
                        : "System"}
                      {log.properties?.path ? ` • /${log.properties.path}` : ""}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  {log.event && (
                    <span className="rounded-full bg-muted px-2 py-1 text-xs uppercase">
                      {log.event}
                    </span>
                  )}
                  {log.properties?.status && (
                    <span className="rounded-full bg-muted px-2 py-1 text-xs">
                      {log.properties.status}
                    </span>
                  )}
                  <span className="text-xs text-muted-foreground">
                    {new Date(log.created_at).toLocaleString()}
                  </span>
                </div>
              </div>
              {log.properties?.payload && (
                <pre className="mt-3 max-h-40 overflow-auto rounded bg-muted/50 p-2 text-xs">
                  {JSON.stringify(log.properties.payload, null, 2)}
                </pre>
              )}
            </div>
          ))}

          {!loading && logs.length === 0 && (
            <p className="py-8 text-center text-muted-foreground">No audit logs found</p>
          )}

          <div className="flex items-center justify-between pt-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              onClick={() => setPage((current) => Math.max(1, current - 1))}
            >
              <ChevronLeft className="h-4 w-4 mr-1" />
              Previous
            </Button>
            <span className="text-sm text-muted-foreground">
              Page {page} of {lastPage}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= lastPage}
              onClick={() => setPage((current) => Math.min(lastPage, current + 1))}
            >
              Next
              <ChevronRight className="h-4 w-4 ml-1" />
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
