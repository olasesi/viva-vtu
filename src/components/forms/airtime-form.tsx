"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
import { Badge } from "@/components/ui/badge";
import { airtimeSchema, type AirtimeInput } from "@/lib/validators";
import { NETWORK_PROVIDERS, AIRTIME_AMOUNTS } from "@/lib/constants";
import { usePurchase } from "@/hooks/use-purchase";
import { useWallet } from "@/hooks/use-wallet";
import { formatCurrency } from "@/lib/utils";
import { Phone, Zap, Shield } from "lucide-react";
import { toast } from "sonner";

export function AirtimeForm() {
  const [selectedAmount, setSelectedAmount] = useState<number | null>(null);
  const { buyAirtime, isAirtimeLoading } = usePurchase();
  const { balance } = useWallet();

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors },
  } = useForm<AirtimeInput>({
    resolver: zodResolver(airtimeSchema),
    defaultValues: {
      network: "",
      phoneNumber: "",
      amount: 0,
      pin: "",
    },
  });

  const watchedNetwork = watch("network");
  const watchedAmount = watch("amount");
  const watchedPhone = watch("phoneNumber");

  const onSubmit = (data: AirtimeInput) => {
    if ((balance?.balance || 0) < data.amount) {
      toast.error("Insufficient balance. Please fund your wallet.");
      return;
    }
    buyAirtime(data, {
      onSuccess: () => {
        reset();
        setSelectedAmount(null);
      },
    });
  };

  const handleAmountSelect = (amount: number) => {
    setSelectedAmount(amount);
    setValue("amount", amount);
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Phone className="h-5 w-5 text-primary" />
          Buy Airtime
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="space-y-2">
            <Label htmlFor="network">Network Provider</Label>
            <Select
              onValueChange={(value) => setValue("network", value)}
              value={watchedNetwork}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select network" />
              </SelectTrigger>
              <SelectContent>
                {NETWORK_PROVIDERS.map((provider) => (
                  <SelectItem key={provider.code} value={provider.code}>
                    <div className="flex items-center gap-2">
                      <div
                        className="h-6 w-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white"
                        style={{ backgroundColor: provider.color }}
                      >
                        {provider.name[0]}
                      </div>
                      {provider.name}
                    </div>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.network && (
              <p className="text-sm text-red-500">{errors.network.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="phoneNumber">Phone Number</Label>
            <Input
              id="phoneNumber"
              placeholder="e.g. 08012345678"
              {...register("phoneNumber")}
              maxLength={11}
            />
            {errors.phoneNumber && (
              <p className="text-sm text-red-500">{errors.phoneNumber.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Amount</Label>
            <div className="grid grid-cols-4 gap-2">
              {AIRTIME_AMOUNTS.map((amount) => (
                <Button
                  key={amount}
                  type="button"
                  variant={selectedAmount === amount ? "default" : "outline"}
                  size="sm"
                  onClick={() => handleAmountSelect(amount)}
                >
                  {formatCurrency(amount)}
                </Button>
              ))}
            </div>
            <Input
              type="number"
              placeholder="Or enter custom amount"
              {...register("amount", { valueAsNumber: true })}
              className="mt-2"
            />
            {errors.amount && (
              <p className="text-sm text-red-500">{errors.amount.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="pin">Transaction PIN (Optional)</Label>
            <Input
              id="pin"
              type="password"
              placeholder="Enter 4-digit PIN"
              maxLength={4}
              {...register("pin")}
            />
          </div>

          <Separator />

          <div className="rounded-lg bg-muted p-4 space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Network</span>
              <span className="font-medium">
                {NETWORK_PROVIDERS.find((p) => p.code === watchedNetwork)?.name || "—"}
              </span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Phone Number</span>
              <span className="font-medium">{watchedPhone || "—"}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Amount</span>
              <span className="font-medium">
                {watchedAmount > 0 ? formatCurrency(watchedAmount) : "—"}
              </span>
            </div>
            <Separator />
            <div className="flex justify-between font-semibold">
              <span>Total</span>
              <span className="text-primary">
                {watchedAmount > 0 ? formatCurrency(watchedAmount) : "—"}
              </span>
            </div>
          </div>

          <Button
            type="submit"
            className="w-full"
            size="lg"
            disabled={isAirtimeLoading}
          >
            {isAirtimeLoading ? (
              <div className="flex items-center gap-2">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Processing...
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <Zap className="h-4 w-4" />
                Buy Airtime
              </div>
            )}
          </Button>

          {(balance?.balance || 0) < (watchedAmount || 0) && (
            <Badge variant="destructive" className="w-full justify-center py-1">
              Insufficient balance. Please fund your wallet.
            </Badge>
          )}
        </form>
      </CardContent>
    </Card>
  );
}
