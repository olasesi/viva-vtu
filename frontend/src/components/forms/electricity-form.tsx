"use client";

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
import { electricitySchema, type ElectricityInput } from "@/lib/validators";
import { ELECTRICITY_DISTRIBUTORS } from "@/lib/constants";
import { usePurchase } from "@/hooks/use-purchase";
import { useWallet } from "@/hooks/use-wallet";
import { formatCurrency } from "@/lib/utils";
import { Zap, Lightbulb } from "lucide-react";
import { toast } from "sonner";

export function ElectricityForm() {
  const { buyElectricity, isElectricityLoading } = usePurchase();
  const { balance } = useWallet();

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors },
  } = useForm<ElectricityInput>({
    resolver: zodResolver(electricitySchema),
    defaultValues: {
      distributor: "",
      meterNumber: "",
      amount: 0,
      meterType: undefined,
      pin: "",
    },
  });

  const watchedDistributor = watch("distributor");
  const watchedMeterNumber = watch("meterNumber");
  const watchedAmount = watch("amount");
  const watchedMeterType = watch("meterType");

  const onSubmit = (data: ElectricityInput) => {
    if ((balance?.balance || 0) < data.amount) {
      toast.error("Insufficient balance. Please fund your wallet.");
      return;
    }
    buyElectricity(data, {
      onSuccess: () => {
        reset();
      },
    });
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Lightbulb className="h-5 w-5 text-primary" />
          Pay Electricity Bill
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="space-y-2">
            <Label>Electricity Distributor</Label>
            <Select
              onValueChange={(value) => setValue("distributor", value)}
              value={watchedDistributor}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select distributor" />
              </SelectTrigger>
              <SelectContent>
                {ELECTRICITY_DISTRIBUTORS.map((dist) => (
                  <SelectItem key={dist.code} value={dist.code}>
                    <div>
                      <span className="font-medium">{dist.name}</span>
                      <span className="text-xs text-muted-foreground ml-2">({dist.state})</span>
                    </div>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.distributor && (
              <p className="text-sm text-red-500">{errors.distributor.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Meter Number</Label>
            <Input
              placeholder="Enter meter number"
              {...register("meterNumber")}
              maxLength={15}
            />
            {errors.meterNumber && (
              <p className="text-sm text-red-500">{errors.meterNumber.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Meter Type</Label>
            <div className="grid grid-cols-2 gap-3">
              <Button
                type="button"
                variant={watchedMeterType === "prepaid" ? "default" : "outline"}
                className="h-12"
                onClick={() => setValue("meterType", "prepaid")}
              >
                Prepaid
              </Button>
              <Button
                type="button"
                variant={watchedMeterType === "postpaid" ? "default" : "outline"}
                className="h-12"
                onClick={() => setValue("meterType", "postpaid")}
              >
                Postpaid
              </Button>
            </div>
            {errors.meterType && (
              <p className="text-sm text-red-500">{errors.meterType.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Amount</Label>
            <Input
              type="number"
              placeholder="Enter amount (₦500 - ₦100,000)"
              {...register("amount", { valueAsNumber: true })}
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
              <span className="text-muted-foreground">Distributor</span>
              <span className="font-medium">
                {ELECTRICITY_DISTRIBUTORS.find((d) => d.code === watchedDistributor)?.name || "—"}
              </span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Meter Number</span>
              <span className="font-medium">{watchedMeterNumber || "—"}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Meter Type</span>
              <span className="font-medium capitalize">{watchedMeterType || "—"}</span>
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
            disabled={isElectricityLoading}
          >
            {isElectricityLoading ? (
              <div className="flex items-center gap-2">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Processing...
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <Zap className="h-4 w-4" />
                Pay Electricity Bill
              </div>
            )}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
