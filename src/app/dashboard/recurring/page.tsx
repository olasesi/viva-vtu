"use client";

import { useCallback, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  createRecurringPurchase,
  deleteRecurringPurchase,
  getRecurringPurchases,
  pauseRecurringPurchase,
  resumeRecurringPurchase,
  runRecurringPurchase,
  updateRecurringPurchase,
  type RecurringPurchaseRecord,
} from "@/lib/api";
import { useTransactionPinPrompt } from "@/components/transaction-pin/transaction-pin-provider";
import {
  CalendarClock,
  Loader2,
  Pause,
  Pencil,
  Play,
  Plus,
  RotateCcw,
  X,
} from "lucide-react";
import { toast } from "sonner";

type Category =
  | "airtime"
  | "data"
  | "electricity"
  | "cable"
  | "education"
  | "streaming";

type Frequency = "daily" | "weekly" | "monthly";

interface FormState {
  id: number;
  name: string;
  category: Category;
  frequency: Frequency;
  interval: string;
  day_of_month: string;
  start_at: string;
  amount: string;
  fields: Record<string, string>;
}

const CATEGORY_LABELS: Record<Category, string> = {
  airtime: "Airtime",
  data: "Data Bundle",
  electricity: "Electricity",
  cable: "Cable TV",
  education: "Exam Pin",
  streaming: "Streaming",
};

const NETWORKS = ["mtn", "airtel", "glo", "9mobile"];
const EXAM_TYPES = ["waec", "neco", "jamb"];

function defaultFields(category: Category): Record<string, string> {
  switch (category) {
    case "airtime":
      return { phone_number: "", network: "mtn" };
    case "data":
      return { phone_number: "", network: "mtn", plan: "" };
    case "electricity":
      return { disco: "", meter_number: "", meter_type: "prepaid" };
    case "cable":
      return { cable: "", smartcard_number: "", package: "" };
    case "education":
      return { exam_type: "waec", quantity: "1" };
    case "streaming":
      return { platform: "", plan: "", recipient: "" };
  }
}

function emptyForm(): FormState {
  return {
    id: 0,
    name: "",
    category: "data",
    frequency: "monthly",
    interval: "1",
    day_of_month: "",
    start_at: "",
    amount: "",
    fields: defaultFields("data"),
  };
}

function statusClasses(status: RecurringPurchaseRecord["status"]): string {
  switch (status) {
    case "active":
      return "bg-emerald-100 text-emerald-700";
    case "paused":
      return "bg-amber-100 text-amber-700";
    default:
      return "bg-muted text-muted-foreground";
  }
}

function formatDate(value: string | null): string {
  if (!value) {
    return "-";
  }

  const parsed = new Date(value.replace(/\.(\d{3})\d+/, ".$1"));

  return Number.isNaN(parsed.getTime()) ? "-" : parsed.toLocaleString();
}

export default function RecurringPurchasesPage() {
  const [purchases, setPurchases] = useState<RecurringPurchaseRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);
  const { requestTransactionPin } = useTransactionPinPrompt();

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getRecurringPurchases();
      setPurchases(res.data || []);
    } catch {
      setPurchases([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const openCreate = () => {
    setForm(emptyForm());
    setDialogOpen(true);
  };

  const openEdit = (purchase: RecurringPurchaseRecord) => {
    const amount = String(purchase.payload.amount ?? purchase.amount ?? "");

    setForm({
      id: purchase.id,
      name: purchase.name,
      category: purchase.category,
      frequency: purchase.frequency,
      interval: String(purchase.interval),
      day_of_month: purchase.day_of_month ? String(purchase.day_of_month) : "",
      start_at: "",
      amount,
      fields: Object.fromEntries(
        Object.entries(purchase.payload)
          .filter(([key]) => key !== "amount")
          .map(([key, value]) => [key, value === null ? "" : String(value)]),
      ),
    });
    setDialogOpen(true);
  };

  const updateField = (key: string, value: string) => {
    setForm((current) => ({
      ...current,
      fields: { ...current.fields, [key]: value },
    }));
  };

  const changeCategory = (category: Category) => {
    setForm((current) => ({
      ...current,
      category,
      amount: category === current.category ? current.amount : "",
      fields: defaultFields(category),
    }));
  };

  const buildPayload = (): Record<string, string | number> => {
    const payload: Record<string, string | number> = {
      ...form.fields,
      amount: Number(form.amount),
    };

    if (form.category === "education") {
      payload.quantity = Number(form.fields.quantity || 1);
    }

    return payload;
  };

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);

    const body: Record<string, unknown> = {
      name: form.name,
      category: form.category,
      frequency: form.frequency,
      interval: Number(form.interval || 1),
      day_of_month:
        form.frequency === "monthly" && form.day_of_month
          ? Number(form.day_of_month)
          : null,
      payload: buildPayload(),
    };

    if (form.start_at) {
      body.start_at = new Date(form.start_at).toISOString();
    }

    try {
      if (form.id) {
        if (!form.start_at) {
          delete body.start_at;
        }
        await updateRecurringPurchase(form.id, body);
        toast.success("Recurring purchase updated");
      } else {
        await createRecurringPurchase(body);
        toast.success("Recurring purchase scheduled");
      }

      setDialogOpen(false);
      load();
    } catch (error: any) {
      const errors = error?.response?.data?.errors;
      const firstError = errors ? Object.values(errors)[0] : null;
      toast.error(
        (Array.isArray(firstError) ? firstError[0] : firstError) ||
          error?.response?.data?.message ||
          "Failed to save recurring purchase",
      );
    } finally {
      setSaving(false);
    }
  };

  const withBusy = async (
    id: number,
    action: () => Promise<void>,
    fallback: string,
  ) => {
    setBusyId(id);
    try {
      await action();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || fallback);
    } finally {
      setBusyId(null);
    }
  };

  const pause = (purchase: RecurringPurchaseRecord) =>
    withBusy(
      purchase.id,
      async () => {
        await pauseRecurringPurchase(purchase.id);
        toast.success("Recurring purchase paused");
        load();
      },
      "Failed to pause recurring purchase",
    );

  const resume = (purchase: RecurringPurchaseRecord) =>
    withBusy(
      purchase.id,
      async () => {
        await resumeRecurringPurchase(purchase.id);
        toast.success("Recurring purchase resumed");
        load();
      },
      "Failed to resume recurring purchase",
    );

  const cancel = (purchase: RecurringPurchaseRecord) => {
    if (!window.confirm(`Cancel the recurring purchase "${purchase.name}"?`)) {
      return;
    }

    return withBusy(
      purchase.id,
      async () => {
        await deleteRecurringPurchase(purchase.id);
        toast.success("Recurring purchase cancelled");
        load();
      },
      "Failed to cancel recurring purchase",
    );
  };

  const runNow = (purchase: RecurringPurchaseRecord) =>
    withBusy(
      purchase.id,
      async () => {
        try {
          const response = await runRecurringPurchase(purchase.id);
          toast.success(response.message || "Recurring purchase executed");
        } catch (error: any) {
          if (error?.response?.data?.code !== "transaction_pin_required") {
            throw error;
          }

          const pin = await requestTransactionPin();

          if (!pin) {
            return;
          }

          const response = await runRecurringPurchase(purchase.id, {
            transaction_pin: pin,
          });
          toast.success(response.message || "Recurring purchase executed");
        }

        load();
      },
      "Failed to run recurring purchase",
    );

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold">
            <CalendarClock className="h-6 w-6 text-primary" />
            Recurring Purchases
          </h1>
          <p className="text-muted-foreground">
            Automate repeat purchases such as monthly data, cable TV and electricity
          </p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          New Schedule
        </Button>
      </div>

      {loading ? (
        <Card>
          <CardContent className="flex items-center justify-center py-12 text-muted-foreground">
            <Loader2 className="mr-2 h-5 w-5 animate-spin" />
            Loading schedules...
          </CardContent>
        </Card>
      ) : purchases.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
            <CalendarClock className="h-10 w-10 text-muted-foreground" />
            <div>
              <p className="font-medium">No recurring purchases yet</p>
              <p className="text-sm text-muted-foreground">
                Create a schedule and we will run it automatically.
              </p>
            </div>
            <Button onClick={openCreate}>
              <Plus className="mr-2 h-4 w-4" />
              Create your first schedule
            </Button>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-3">
          {purchases.map((purchase) => (
            <Card key={purchase.id}>
              <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0">
                <div>
                  <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                    {purchase.name}
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs capitalize ${statusClasses(
                        purchase.status,
                      )}`}
                    >
                      {purchase.status}
                    </span>
                  </CardTitle>
                  <CardDescription>
                    {CATEGORY_LABELS[purchase.category]} • ₦
                    {Number(purchase.amount).toLocaleString()} •{" "}
                    {purchase.frequency} every {purchase.interval}{" "}
                    {purchase.interval === 1 ? "cycle" : "cycles"}
                  </CardDescription>
                </div>
                <div className="flex flex-wrap items-center gap-1">
                  {purchase.status !== "cancelled" && (
                    <>
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={busyId === purchase.id}
                        onClick={() => runNow(purchase)}
                      >
                        {busyId === purchase.id ? (
                          <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                        ) : (
                          <Play className="mr-1 h-4 w-4" />
                        )}
                        Run now
                      </Button>
                      {purchase.status === "active" ? (
                        <Button
                          size="icon"
                          variant="ghost"
                          aria-label="Pause recurring purchase"
                          disabled={busyId === purchase.id}
                          onClick={() => pause(purchase)}
                        >
                          <Pause className="h-4 w-4" />
                        </Button>
                      ) : (
                        <Button
                          size="icon"
                          variant="ghost"
                          aria-label="Resume recurring purchase"
                          disabled={busyId === purchase.id}
                          onClick={() => resume(purchase)}
                        >
                          <RotateCcw className="h-4 w-4" />
                        </Button>
                      )}
                      <Button
                        size="icon"
                        variant="ghost"
                        aria-label="Edit recurring purchase"
                        disabled={busyId === purchase.id}
                        onClick={() => openEdit(purchase)}
                      >
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button
                        size="icon"
                        variant="ghost"
                        aria-label="Cancel recurring purchase"
                        disabled={busyId === purchase.id}
                        onClick={() => cancel(purchase)}
                      >
                        <X className="h-4 w-4 text-red-500" />
                      </Button>
                    </>
                  )}
                </div>
              </CardHeader>
              <CardContent className="grid gap-2 text-sm text-muted-foreground sm:grid-cols-3">
                <p>
                  <span className="font-medium text-foreground">Next run:</span>{" "}
                  {formatDate(purchase.next_run_at)}
                </p>
                <p>
                  <span className="font-medium text-foreground">Last run:</span>{" "}
                  {formatDate(purchase.last_run_at)}
                </p>
                <p>
                  <span className="font-medium text-foreground">Runs:</span>{" "}
                  {purchase.run_count}
                  {purchase.failure_count > 0 && (
                    <span className="text-amber-600">
                      {" "}
                      ({purchase.failure_count} consecutive failure
                      {purchase.failure_count === 1 ? "" : "s"})
                    </span>
                  )}
                </p>
                {purchase.last_error && (
                  <p className="sm:col-span-3 text-red-600">
                    Last error: {purchase.last_error}
                  </p>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              {form.id ? "Edit Recurring Purchase" : "New Recurring Purchase"}
            </DialogTitle>
            <DialogDescription>
              Choose what to buy and how often. The purchase runs automatically.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={save} className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="recurring-name">Schedule name</Label>
                <Input
                  id="recurring-name"
                  required
                  value={form.name}
                  onChange={(event) =>
                    setForm({ ...form, name: event.target.value })
                  }
                  placeholder="Monthly 10GB data"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="recurring-category">Category</Label>
                <select
                  id="recurring-category"
                  className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  value={form.category}
                  disabled={Boolean(form.id)}
                  onChange={(event) =>
                    changeCategory(event.target.value as Category)
                  }
                >
                  {Object.entries(CATEGORY_LABELS).map(([value, label]) => (
                    <option key={value} value={value}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="recurring-amount">Amount (NGN)</Label>
                <Input
                  id="recurring-amount"
                  type="number"
                  min={50}
                  step="0.01"
                  required
                  value={form.amount}
                  onChange={(event) =>
                    setForm({ ...form, amount: event.target.value })
                  }
                  placeholder="1000"
                />
              </div>

              {form.category === "airtime" && (
                <div className="space-y-2">
                  <Label htmlFor="recurring-network">Network</Label>
                  <select
                    id="recurring-network"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={form.fields.network}
                    onChange={(event) => updateField("network", event.target.value)}
                  >
                    {NETWORKS.map((network) => (
                      <option key={network} value={network}>
                        {network.toUpperCase()}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {form.category === "data" && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-network">Network</Label>
                    <select
                      id="recurring-network"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={form.fields.network}
                      onChange={(event) => updateField("network", event.target.value)}
                    >
                      {NETWORKS.map((network) => (
                        <option key={network} value={network}>
                          {network.toUpperCase()}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-plan">Plan code</Label>
                    <Input
                      id="recurring-plan"
                      required
                      value={form.fields.plan}
                      onChange={(event) => updateField("plan", event.target.value)}
                      placeholder="gift-1gb"
                    />
                  </div>
                </>
              )}

              {form.category === "electricity" && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-disco">Disco</Label>
                    <Input
                      id="recurring-disco"
                      required
                      value={form.fields.disco}
                      onChange={(event) => updateField("disco", event.target.value)}
                      placeholder="ikeja-electric"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-meter">Meter number</Label>
                    <Input
                      id="recurring-meter"
                      required
                      value={form.fields.meter_number}
                      onChange={(event) =>
                        updateField("meter_number", event.target.value)
                      }
                      placeholder="0123456789"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-meter-type">Meter type</Label>
                    <select
                      id="recurring-meter-type"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={form.fields.meter_type}
                      onChange={(event) =>
                        updateField("meter_type", event.target.value)
                      }
                    >
                      <option value="prepaid">Prepaid</option>
                      <option value="postpaid">Postpaid</option>
                    </select>
                  </div>
                </>
              )}

              {form.category === "cable" && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-cable">Provider</Label>
                    <Input
                      id="recurring-cable"
                      required
                      value={form.fields.cable}
                      onChange={(event) => updateField("cable", event.target.value)}
                      placeholder="dstv"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-smartcard">Smartcard number</Label>
                    <Input
                      id="recurring-smartcard"
                      required
                      value={form.fields.smartcard_number}
                      onChange={(event) =>
                        updateField("smartcard_number", event.target.value)
                      }
                      placeholder="1234567890"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-package">Package code</Label>
                    <Input
                      id="recurring-package"
                      required
                      value={form.fields.package}
                      onChange={(event) =>
                        updateField("package", event.target.value)
                      }
                      placeholder="dstv-compact"
                    />
                  </div>
                </>
              )}

              {form.category === "education" && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-exam">Exam type</Label>
                    <select
                      id="recurring-exam"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={form.fields.exam_type}
                      onChange={(event) =>
                        updateField("exam_type", event.target.value)
                      }
                    >
                      {EXAM_TYPES.map((exam) => (
                        <option key={exam} value={exam}>
                          {exam.toUpperCase()}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-quantity">Quantity</Label>
                    <Input
                      id="recurring-quantity"
                      type="number"
                      min={1}
                      max={10}
                      required
                      value={form.fields.quantity}
                      onChange={(event) =>
                        updateField("quantity", event.target.value)
                      }
                    />
                  </div>
                </>
              )}

              {form.category === "streaming" && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-platform">Platform</Label>
                    <Input
                      id="recurring-platform"
                      required
                      value={form.fields.platform}
                      onChange={(event) =>
                        updateField("platform", event.target.value)
                      }
                      placeholder="netflix"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-stream-plan">Plan</Label>
                    <Input
                      id="recurring-stream-plan"
                      required
                      value={form.fields.plan}
                      onChange={(event) => updateField("plan", event.target.value)}
                      placeholder="1-month"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="recurring-recipient">Recipient</Label>
                    <Input
                      id="recurring-recipient"
                      value={form.fields.recipient}
                      onChange={(event) =>
                        updateField("recipient", event.target.value)
                      }
                      placeholder="08012345678"
                    />
                  </div>
                </>
              )}
            </div>

            {(form.category === "airtime" || form.category === "data") && (
              <div className="space-y-2">
                <Label htmlFor="recurring-phone">Phone number</Label>
                <Input
                  id="recurring-phone"
                  required
                  value={form.fields.phone_number}
                  onChange={(event) =>
                    updateField("phone_number", event.target.value)
                  }
                  placeholder="08031234567"
                />
              </div>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
              <div className="space-y-2">
                <Label htmlFor="recurring-frequency">Frequency</Label>
                <select
                  id="recurring-frequency"
                  className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  value={form.frequency}
                  onChange={(event) =>
                    setForm({
                      ...form,
                      frequency: event.target.value as Frequency,
                    })
                  }
                >
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="recurring-interval">Every</Label>
                <Input
                  id="recurring-interval"
                  type="number"
                  min={1}
                  max={12}
                  required
                  value={form.interval}
                  onChange={(event) =>
                    setForm({ ...form, interval: event.target.value })
                  }
                />
              </div>
              {form.frequency === "monthly" && (
                <div className="space-y-2">
                  <Label htmlFor="recurring-day">Day of month</Label>
                  <Input
                    id="recurring-day"
                    type="number"
                    min={1}
                    max={31}
                    value={form.day_of_month}
                    onChange={(event) =>
                      setForm({ ...form, day_of_month: event.target.value })
                    }
                    placeholder="Optional"
                  />
                </div>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="recurring-start">First run (optional)</Label>
              <Input
                id="recurring-start"
                type="datetime-local"
                value={form.start_at}
                onChange={(event) =>
                  setForm({ ...form, start_at: event.target.value })
                }
              />
              <p className="text-xs text-muted-foreground">
                Leave blank to start at the next cycle.
              </p>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setDialogOpen(false)}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? (
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : null}
                {form.id ? "Save Changes" : "Create Schedule"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
