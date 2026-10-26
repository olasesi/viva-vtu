"use client";

import { useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  confirmTwoFactor,
  disableTwoFactor,
  getTwoFactorStatus,
  regenerateRecoveryCodes,
  setupTwoFactor,
} from "@/lib/api";
import { ShieldCheck, ShieldOff, Copy, RefreshCw } from "lucide-react";
import { toast } from "sonner";

export default function SecurityPage() {
  const [loading, setLoading] = useState(true);
  const [enabled, setEnabled] = useState(false);
  const [setupData, setSetupData] = useState<{ secret: string; otpauthUrl: string } | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await getTwoFactorStatus();
      setEnabled(Boolean(res.data?.enabled));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const startSetup = async () => {
    setBusy(true);
    try {
      const res = await setupTwoFactor();
      setSetupData(res.data);
      setRecoveryCodes(null);
    } catch (error: any) {
      toast.error(error?.response?.data?.message || "Failed to start setup");
    } finally {
      setBusy(false);
    }
  };

  const confirm = async () => {
    setBusy(true);
    try {
      const res = await confirmTwoFactor(code);
      setRecoveryCodes(res.data?.recoveryCodes || []);
      setSetupData(null);
      setCode("");
      setEnabled(true);
      toast.success("Two-factor authentication enabled");
    } catch (error: any) {
      toast.error(error?.response?.data?.message || "Invalid code");
    } finally {
      setBusy(false);
    }
  };

  const disable = async () => {
    setBusy(true);
    try {
      await disableTwoFactor({ password, code });
      setEnabled(false);
      setPassword("");
      setCode("");
      setRecoveryCodes(null);
      toast.success("Two-factor authentication disabled");
    } catch (error: any) {
      toast.error(error?.response?.data?.message || "Failed to disable");
    } finally {
      setBusy(false);
    }
  };

  const regenerate = async () => {
    setBusy(true);
    try {
      const res = await regenerateRecoveryCodes({ password, code });
      setRecoveryCodes(res.data?.recoveryCodes || []);
      toast.success("Recovery codes regenerated");
    } catch (error: any) {
      toast.error(error?.response?.data?.message || "Failed to regenerate");
    } finally {
      setBusy(false);
    }
  };

  const copy = async (value: string) => {
    await navigator.clipboard.writeText(value);
    toast.success("Copied");
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Security</h1>
        <p className="text-muted-foreground">
          Protect your account with two-factor authentication
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            {enabled ? (
              <ShieldCheck className="h-5 w-5 text-green-600" />
            ) : (
              <ShieldOff className="h-5 w-5 text-muted-foreground" />
            )}
            Two-Factor Authentication
          </CardTitle>
          <CardDescription>
            {enabled
              ? "Your account is protected with an authenticator app."
              : "Add an extra layer of security using Google Authenticator, Authy, or any TOTP app."}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {!enabled && !setupData && (
            <Button onClick={startSetup} disabled={busy || loading}>
              Enable Two-Factor Authentication
            </Button>
          )}

          {setupData && (
            <div className="space-y-4">
              <div className="rounded-md border bg-muted/40 p-4 space-y-2">
                <p className="text-sm font-medium">1. Add this account to your authenticator app</p>
                <p className="text-xs text-muted-foreground break-all">{setupData.otpauthUrl}</p>
                <div className="flex items-center gap-2">
                  <code className="rounded bg-background px-2 py-1 text-sm tracking-widest">
                    {setupData.secret}
                  </code>
                  <Button variant="ghost" size="icon" onClick={() => copy(setupData.secret)}>
                    <Copy className="h-4 w-4" />
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                  Can&apos;t scan? Enter the secret key manually in your authenticator app.
                </p>
              </div>

              <div className="space-y-2">
                <p className="text-sm font-medium">2. Enter the 6-digit code to confirm</p>
                <Input
                  inputMode="numeric"
                  placeholder="123456"
                  maxLength={6}
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                />
                <Button onClick={confirm} disabled={busy || code.trim().length < 6}>
                  Confirm & Enable
                </Button>
              </div>
            </div>
          )}

          {enabled && (
            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <Input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="code">Authentication or recovery code</Label>
                <Input
                  id="code"
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button variant="outline" onClick={regenerate} disabled={busy || !password || !code}>
                  <RefreshCw className="h-4 w-4 mr-2" />
                  New recovery codes
                </Button>
                <Button variant="destructive" onClick={disable} disabled={busy || !password || !code}>
                  Disable two-factor
                </Button>
              </div>
            </div>
          )}

          {recoveryCodes && (
            <div className="rounded-md border border-amber-200 bg-amber-50 p-4 space-y-2">
              <p className="text-sm font-semibold text-amber-900">
                Save these recovery codes somewhere safe. They are shown only once.
              </p>
              <div className="grid grid-cols-2 gap-2">
                {recoveryCodes.map((recoveryCode) => (
                  <code key={recoveryCode} className="rounded bg-white px-2 py-1 text-sm">
                    {recoveryCode}
                  </code>
                ))}
              </div>
              <Button variant="outline" size="sm" onClick={() => copy(recoveryCodes.join("\n"))}>
                <Copy className="h-4 w-4 mr-2" />
                Copy all
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
