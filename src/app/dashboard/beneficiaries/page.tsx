"use client";

import { useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  createBeneficiary,
  deleteBeneficiary,
  getBeneficiaries,
  toggleBeneficiaryFavorite,
} from "@/lib/api";
import {
  Building2,
  Phone,
  Star,
  Trash2,
  UserPlus,
  Users,
  Wifi,
} from "lucide-react";
import { toast } from "sonner";

type BeneficiaryType = "wallet" | "bank" | "airtime" | "data";

const TYPE_LABELS: Record<BeneficiaryType, string> = {
  wallet: "Wallet / Transfer",
  bank: "Bank Account",
  airtime: "Airtime Number",
  data: "Data Number",
};

const emptyForm = {
  name: "",
  type: "wallet" as BeneficiaryType,
  identifier: "",
  bank_name: "",
  bank_code: "",
};

export default function BeneficiariesPage() {
  const [beneficiaries, setBeneficiaries] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await getBeneficiaries();
      setBeneficiaries(res.data || []);
    } catch {
      setBeneficiaries([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);

    try {
      await createBeneficiary({
        name: form.name,
        type: form.type,
        identifier: form.identifier,
        bank_name: form.type === "bank" ? form.bank_name || undefined : undefined,
        bank_code: form.type === "bank" ? form.bank_code || undefined : undefined,
      });
      toast.success("Beneficiary saved");
      setForm(emptyForm);
      setOpen(false);
      load();
    } catch (error: any) {
      const errors = error?.response?.data?.errors;
      const firstError = errors ? Object.values(errors)[0] : null;
      toast.error(
        (Array.isArray(firstError) ? firstError[0] : firstError) ||
          error?.response?.data?.message ||
          "Failed to save beneficiary",
      );
    } finally {
      setSaving(false);
    }
  };

  const toggleFavorite = async (id: number) => {
    try {
      await toggleBeneficiaryFavorite(id);
      load();
    } catch {
      toast.error("Failed to update favorite");
    }
  };

  const remove = async (id: number) => {
    try {
      await deleteBeneficiary(id);
      toast.success("Beneficiary removed");
      load();
    } catch {
      toast.error("Failed to remove beneficiary");
    }
  };

  const iconFor = (type: BeneficiaryType) => {
    switch (type) {
      case "bank":
        return <Building2 className="h-5 w-5" />;
      case "airtime":
        return <Phone className="h-5 w-5" />;
      case "data":
        return <Wifi className="h-5 w-5" />;
      default:
        return <Users className="h-5 w-5" />;
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">Beneficiaries</h1>
          <p className="text-muted-foreground">
            Save the people and accounts you pay most often
          </p>
        </div>
        <Button onClick={() => setOpen(true)}>
          <UserPlus className="h-4 w-4 mr-2" />
          Add Beneficiary
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Saved Beneficiaries</CardTitle>
          <CardDescription>
            Favorites appear first when you make a payment
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-2">
          {beneficiaries.map((beneficiary) => (
            <div
              key={beneficiary.id}
              className="flex items-center justify-between gap-3 rounded-md border p-4"
            >
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                  {iconFor(beneficiary.type)}
                </div>
                <div>
                  <p className="font-medium flex items-center gap-2">
                    {beneficiary.name}
                    {beneficiary.is_favorite && (
                      <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
                    )}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    {beneficiary.identifier}
                    {beneficiary.bank_name ? ` • ${beneficiary.bank_name}` : ""}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {TYPE_LABELS[beneficiary.type as BeneficiaryType] || beneficiary.type}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-1">
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label="Toggle favorite"
                  onClick={() => toggleFavorite(beneficiary.id)}
                >
                  <Star
                    className={`h-4 w-4 ${
                      beneficiary.is_favorite ? "fill-yellow-400 text-yellow-400" : ""
                    }`}
                  />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label="Remove beneficiary"
                  onClick={() => remove(beneficiary.id)}
                >
                  <Trash2 className="h-4 w-4 text-red-500" />
                </Button>
              </div>
            </div>
          ))}

          {!loading && beneficiaries.length === 0 && (
            <div className="py-10 text-center text-muted-foreground">
              <Users className="mx-auto mb-2 h-8 w-8" />
              <p>No saved beneficiaries yet</p>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Add Beneficiary</DialogTitle>
          </DialogHeader>
          <form onSubmit={save} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="beneficiary-name">Name</Label>
              <Input
                id="beneficiary-name"
                required
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
                placeholder="Ada Okafor"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="beneficiary-type">Type</Label>
              <select
                id="beneficiary-type"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={form.type}
                onChange={(event) =>
                  setForm({ ...form, type: event.target.value as BeneficiaryType })
                }
              >
                <option value="wallet">Wallet / Transfer</option>
                <option value="bank">Bank Account</option>
                <option value="airtime">Airtime Number</option>
                <option value="data">Data Number</option>
              </select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="beneficiary-identifier">
                {form.type === "wallet" || form.type === "bank"
                  ? form.type === "bank"
                    ? "Account Number"
                    : "Email or Phone"
                  : "Phone Number"}
              </Label>
              <Input
                id="beneficiary-identifier"
                required
                value={form.identifier}
                onChange={(event) => setForm({ ...form, identifier: event.target.value })}
                placeholder={
                  form.type === "bank" ? "0123456789" : "ada@example.com or 08012345678"
                }
              />
            </div>

            {form.type === "bank" && (
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="beneficiary-bank">Bank Name</Label>
                  <Input
                    id="beneficiary-bank"
                    value={form.bank_name}
                    onChange={(event) => setForm({ ...form, bank_name: event.target.value })}
                    placeholder="GTBank"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="beneficiary-bank-code">Bank Code</Label>
                  <Input
                    id="beneficiary-bank-code"
                    value={form.bank_code}
                    onChange={(event) => setForm({ ...form, bank_code: event.target.value })}
                    placeholder="044"
                  />
                </div>
              </div>
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? "Saving..." : "Save Beneficiary"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
