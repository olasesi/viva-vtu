"use client";

import { createContext, useCallback, useContext, useRef, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Lock } from "lucide-react";

interface TransactionPinContextValue {
  requestTransactionPin: () => Promise<string | null>;
}

const TransactionPinContext = createContext<TransactionPinContextValue | null>(null);

export function TransactionPinProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  const [pin, setPin] = useState("");
  const resolverRef = useRef<((value: string | null) => void) | null>(null);

  const requestTransactionPin = useCallback(() => {
    return new Promise<string | null>((resolve) => {
      resolverRef.current = resolve;
      setPin("");
      setOpen(true);
    });
  }, []);

  const finish = (value: string | null) => {
    setOpen(false);
    const resolver = resolverRef.current;
    resolverRef.current = null;
    resolver?.(value);
  };

  return (
    <TransactionPinContext.Provider value={{ requestTransactionPin }}>
      {children}
      <Dialog
        open={open}
        onOpenChange={(next) => {
          if (!next) {
            finish(null);
          }
        }}
      >
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Lock className="h-4 w-4" />
              Enter Transaction PIN
            </DialogTitle>
            <DialogDescription>
              Your transaction PIN is required to complete this operation.
            </DialogDescription>
          </DialogHeader>
          <form
            onSubmit={(event) => {
              event.preventDefault();
              finish(pin.trim());
            }}
            className="space-y-4"
          >
            <div className="space-y-2">
              <Label htmlFor="transaction-pin">4-digit PIN</Label>
              <Input
                id="transaction-pin"
                type="password"
                inputMode="numeric"
                maxLength={4}
                autoFocus
                value={pin}
                onChange={(event) => setPin(event.target.value.replace(/\D/g, ""))}
                placeholder="••••"
              />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => finish(null)}>
                Cancel
              </Button>
              <Button type="submit" disabled={pin.trim().length !== 4}>
                Confirm
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </TransactionPinContext.Provider>
  );
}

export function useTransactionPinPrompt() {
  const context = useContext(TransactionPinContext);

  if (!context) {
    throw new Error("useTransactionPinPrompt must be used within a TransactionPinProvider");
  }

  return context;
}
