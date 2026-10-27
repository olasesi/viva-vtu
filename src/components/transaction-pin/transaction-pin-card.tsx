"use client";

import { useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  changeTransactionPin,
  getTransactionPinStatus,
  removeTransactionPin,
  setTransactionPin,
} from "@/lib/api";
import { KeyRound, Lock, Trash2 } from "lucide-react";
import { toast } from "sonner";

export function TransactionPinCard() {
  const [loading, setLoading] = useState(true);
  const [enabled, setEnabled] = useState(false);
  const [mode, setMode] = useState<"idle" | "set" | "change" | "remove">("idle");
  const [currentPin, setCurrentPin] = useState("");
  const [pin, setPin] = useState("");
  const [pinConfirmation, setPinConfirmation] = useState("");
  const [busy, setBusy] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await getTransactionPinStatus();
      setEnabled(Boolean(res.data?.enabled));
    } catch {
      setEnabled(false);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const resetForm = () => {
    setMode("idle");
    setCurrentPin("");
    setPin("");
    setPinConfirmation("");
  };

  const errorMessage = (error: any, fallback: string) =>
    error?.response?.data?.message || fallback;

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);

    try {
      if (mode === "set") {
        await setTransactionPin({ pin, pin_confirmation: pinConfirmation });
        toast.success("Transaction PIN set successfully");
        setEnabled(true);
      } else if (mode === "change") {
        await changeTransactionPin({
          current_pin: currentPin,
          pin,
          pin_confirmation: pinConfirmation,
        });
        toast.success("Transaction PIN updated successfully");
      } else if (mode === "remove") {
        await removeTransactionPin({ pin: currentPin });
        toast.success("Transaction PIN removed");
        setEnabled(false);
      }

      resetForm();
    } catch (error: any) {
      toast.error(errorMessage(error, "Something went wrong"));
    } finally {
      setBusy(false);
    }
  };

  const canSubmit = () => {
    if (mode === "remove") {
      return currentPin.length === 4;
    }

    const base = pin.length === 4 && pinConfirmation.length === 4 && pin === pinConfirmation;

    return mode === "change" ? base && currentPin.length === 4 : base;
  };

  const pinInput = (
    id: string,
    label: string,
    value: string,
    onChange: (value: string) => void,
    autoComplete: string,
  ) => (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        type="password"
        inputMode="numeric"
        maxLength={4}
        autoComplete={autoComplete}
        value={value}
        onChange={(event) => onChange(event.target.value.replace(/\D/g, ""))}
        placeholder="••••"
      />
    </div>
  );

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Lock className="h-5 w-5" />
          Transaction PIN
        </CardTitle>
        <CardDescription>
          {enabled
            ? "Your transaction PIN is required to authorise purchases and transfers."
            : "Set a 4-digit PIN to authorise purchases and transfers. It is separate from your login password."}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {mode === "idle" && (
          <div className="flex flex-wrap gap-2">
            {enabled ? (
              <>
                <Button variant="outline" onClick={() => setMode("change")}>
                  <KeyRound className="h-4 w-4 mr-2" />
                  Change PIN
                </Button>
                <Button variant="destructive" onClick={() => setMode("remove")}>
                  <Trash2 className="h-4 w-4 mr-2" />
                  Remove PIN
                </Button>
              </>
            ) : (
              <Button onClick={() => setMode("set")} disabled={loading}>
                Set Transaction PIN
              </Button>
            )}
          </div>
        )}

        {mode !== "idle" && (
          <form onSubmit={submit} className="space-y-4">
            {mode === "change" &&
              pinInput("current-pin", "Current PIN", currentPin, setCurrentPin, "current-password")}

            {mode === "remove" &&
              pinInput("current-pin", "Current PIN", currentPin, setCurrentPin, "current-password")}

            {mode !== "remove" && (
              <>
                {pinInput("new-pin", "New PIN", pin, setPin, "new-password")}
                {pinInput(
                  "confirm-pin",
                  "Confirm PIN",
                  pinConfirmation,
                  setPinConfirmation,
                  "new-password",
                )}
                {pin.length === 4 && pinConfirmation.length === 4 && pin !== pinConfirmation && (
                  <p className="text-sm text-red-500">PINs do not match</p>
                )}
              </>
            )}

            <div className="flex flex-wrap gap-2">
              <Button type="submit" disabled={busy || !canSubmit()}>
                {busy
                  ? "Saving..."
                  : mode === "set"
                    ? "Set PIN"
                    : mode === "change"
                      ? "Update PIN"
                      : "Remove PIN"}
              </Button>
              <Button type="button" variant="ghost" onClick={resetForm} disabled={busy}>
                Cancel
              </Button>
            </div>
          </form>
        )}
      </CardContent>
    </Card>
  );
}
