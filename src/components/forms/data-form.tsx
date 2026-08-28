"use client";

import { useState, useMemo } from "react";
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
import { dataSchema, type DataInput } from "@/lib/validators";
import { NETWORK_PROVIDERS, DATA_PLANS } from "@/lib/constants";
import { usePurchase } from "@/hooks/use-purchase";
import { useWallet } from "@/hooks/use-wallet";
import { formatCurrency } from "@/lib/utils";
import { Wifi, Zap } from "lucide-react";
import { toast } from "sonner";

export function DataForm() {
  const { buyData, isDataLoading } = usePurchase();
  const { balance } = useWallet();

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors },
  } = useForm<DataInput>({
    resolver: zodResolver(dataSchema),
    defaultValues: {
      network: "",
      phoneNumber: "",
      plan: "",
      pin: "",
    },
  });

  const watchedNetwork = watch("network");
  const watchedPlan = watch("plan");

  const availablePlans = useMemo(
    () => DATA_PLANS.filter((plan) => plan.network === watchedNetwork),
    [watchedNetwork]
  );

  const selectedPlan = DATA_PLANS.find((p) => p.code === watchedPlan);

  const onSubmit = (data: DataInput) => {
    const plan = DATA_PLANS.find((p) => p.code === data.plan);
    if (!plan) return;
    if ((balance?.balance || 0) < plan.amount) {
      toast.error("Insufficient balance. Please fund your wallet.");
      return;
    }
    buyData(data, {
      onSuccess: () => {
        reset();
      },
    });
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Wifi className="h-5 w-5 text-primary" />
          Buy Data
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="space-y-2">
            <Label>Network Provider</Label>
            <Select
              onValueChange={(value) => {
                setValue("network", value);
                setValue("plan", "");
              }}
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

          {watchedNetwork && (
            <div className="space-y-2">
              <Label>Data Plan</Label>
              <div className="grid gap-2">
                {availablePlans.map((plan) => (
                  <Button
                    key={plan.code}
                    type="button"
                    variant={watchedPlan === plan.code ? "default" : "outline"}
                    className="justify-between h-auto py-3"
                    onClick={() => setValue("plan", plan.code)}
                  >
                    <div className="text-left">
                      <p className="font-semibold">{plan.name}</p>
                      <p className="text-xs opacity-70">{plan.validity}</p>
                    </div>
                    <span className="font-bold">{formatCurrency(plan.amount)}</span>
                  </Button>
                ))}
              </div>
              {errors.plan && (
                <p className="text-sm text-red-500">{errors.plan.message}</p>
              )}
            </div>
          )}

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

          {selectedPlan && (
            <div className="rounded-lg bg-muted p-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Network</span>
                <span className="font-medium">
                  {NETWORK_PROVIDERS.find((p) => p.code === watchedNetwork)?.name}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Plan</span>
                <span className="font-medium">{selectedPlan.name}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Validity</span>
                <span className="font-medium">{selectedPlan.validity}</span>
              </div>
              <Separator />
              <div className="flex justify-between font-semibold">
                <span>Total</span>
                <span className="text-primary">{formatCurrency(selectedPlan.amount)}</span>
              </div>
            </div>
          )}

          <Button
            type="submit"
            className="w-full"
            size="lg"
            disabled={isDataLoading || !watchedPlan}
          >
            {isDataLoading ? (
              <div className="flex items-center gap-2">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Processing...
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <Zap className="h-4 w-4" />
                Buy Data
              </div>
            )}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
