"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { fundWalletSchema, type FundWalletInput } from "@/lib/validators";
import { useWallet } from "@/hooks/use-wallet";
import { formatCurrency } from "@/lib/utils";
import { Wallet, CreditCard, Shield } from "lucide-react";

const PAYMENT_AMOUNTS = [500, 1000, 2000, 5000, 10000, 20000];

export function FundWalletForm() {
  const { fundWallet, isFunding } = useWallet();

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors },
  } = useForm<FundWalletInput>({
    resolver: zodResolver(fundWalletSchema),
    defaultValues: {
      amount: 0,
      paymentMethod: undefined,
    },
  });

  const watchedAmount = watch("amount");
  const watchedMethod = watch("paymentMethod");

  const onSubmit = (data: FundWalletInput) => {
    fundWallet(data);
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Wallet className="h-5 w-5 text-primary" />
          Fund Wallet
        </CardTitle>
        <CardDescription>
          Add money to your wallet using Paystack or Flutterwave
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="space-y-2">
            <Label>Quick Amount</Label>
            <div className="grid grid-cols-3 gap-2">
              {PAYMENT_AMOUNTS.map((amount) => (
                <Button
                  key={amount}
                  type="button"
                  variant={watchedAmount === amount ? "default" : "outline"}
                  size="sm"
                  onClick={() => setValue("amount", amount)}
                >
                  {formatCurrency(amount)}
                </Button>
              ))}
            </div>
          </div>

          <div className="space-y-2">
            <Label>Or Enter Amount</Label>
            <Input
              type="number"
              placeholder="Enter amount (min ₦100)"
              {...register("amount", { valueAsNumber: true })}
            />
            {errors.amount && (
              <p className="text-sm text-red-500">{errors.amount.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Payment Method</Label>
            <div className="grid grid-cols-2 gap-3">
              <Button
                type="button"
                variant={watchedMethod === "paystack" ? "default" : "outline"}
                className="h-16 flex-col gap-1"
                onClick={() => setValue("paymentMethod", "paystack")}
              >
                <CreditCard className="h-5 w-5" />
                <span className="text-xs">Paystack</span>
              </Button>
              <Button
                type="button"
                variant={watchedMethod === "flutterwave" ? "default" : "outline"}
                className="h-16 flex-col gap-1"
                onClick={() => setValue("paymentMethod", "flutterwave")}
              >
                <CreditCard className="h-5 w-5" />
                <span className="text-xs">Flutterwave</span>
              </Button>
            </div>
            {errors.paymentMethod && (
              <p className="text-sm text-red-500">{errors.paymentMethod.message}</p>
            )}
          </div>

          <Separator />

          <div className="rounded-lg bg-muted p-4 space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Amount</span>
              <span className="font-medium">
                {watchedAmount > 0 ? formatCurrency(watchedAmount) : "—"}
              </span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Payment Method</span>
              <span className="font-medium capitalize">{watchedMethod || "—"}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Fee</span>
              <span className="font-medium text-emerald-600">Free</span>
            </div>
            <Separator />
            <div className="flex justify-between font-semibold">
              <span>You Pay</span>
              <span className="text-primary">
                {watchedAmount > 0 ? formatCurrency(watchedAmount) : "—"}
              </span>
            </div>
          </div>

          <Button
            type="submit"
            className="w-full"
            size="lg"
            disabled={isFunding || !watchedMethod || watchedAmount < 100}
          >
            {isFunding ? (
              <div className="flex items-center gap-2">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Redirecting to payment...
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <Shield className="h-4 w-4" />
                Proceed to Payment
              </div>
            )}
          </Button>

          <p className="text-center text-xs text-muted-foreground">
            Secured by {watchedMethod === "paystack" ? "Paystack" : watchedMethod === "flutterwave" ? "Flutterwave" : "our payment partners"}. Your payment details are encrypted.
          </p>
        </form>
      </CardContent>
    </Card>
  );
}
