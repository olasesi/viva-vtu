"use client";

import { useMemo } from "react";
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
import { cableSchema, type CableInput } from "@/lib/validators";
import { CABLE_PROVIDERS } from "@/lib/constants";
import { usePurchase } from "@/hooks/use-purchase";
import { useWallet } from "@/hooks/use-wallet";
import { formatCurrency } from "@/lib/utils";
import { Tv, Zap } from "lucide-react";
import { toast } from "sonner";

export function CableTvForm() {
  const { buyCableTv, isCableLoading } = usePurchase();
  const { balance } = useWallet();

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors },
  } = useForm<CableInput>({
    resolver: zodResolver(cableSchema),
    defaultValues: {
      provider: "",
      smartCardNumber: "",
      plan: "",
      pin: "",
    },
  });

  const watchedProvider = watch("provider");
  const watchedPlan = watch("plan");
  const watchedSmartCard = watch("smartCardNumber");

  const availablePackages = useMemo(
    () => CABLE_PROVIDERS.find((p) => p.code === watchedProvider)?.packages || [],
    [watchedProvider]
  );

  const selectedPackage = availablePackages.find((p) => p.code === watchedPlan);

  const onSubmit = (data: CableInput) => {
    if (!selectedPackage) return;
    if ((balance?.balance || 0) < selectedPackage.price) {
      toast.error("Insufficient balance. Please fund your wallet.");
      return;
    }
    buyCableTv(data, {
      onSuccess: () => {
        reset();
      },
    });
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Tv className="h-5 w-5 text-primary" />
          Cable TV Subscription
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="space-y-2">
            <Label>Cable Provider</Label>
            <Select
              onValueChange={(value) => {
                setValue("provider", value);
                setValue("plan", "");
              }}
              value={watchedProvider}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select provider" />
              </SelectTrigger>
              <SelectContent>
                {CABLE_PROVIDERS.map((provider) => (
                  <SelectItem key={provider.code} value={provider.code}>
                    {provider.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.provider && (
              <p className="text-sm text-red-500">{errors.provider.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Smart Card Number</Label>
            <Input
              placeholder="Enter smart card number"
              {...register("smartCardNumber")}
              maxLength={15}
            />
            {errors.smartCardNumber && (
              <p className="text-sm text-red-500">{errors.smartCardNumber.message}</p>
            )}
          </div>

          {watchedProvider && (
            <div className="space-y-2">
              <Label>Select Plan</Label>
              <div className="grid gap-2">
                {availablePackages.map((pkg) => (
                  <Button
                    key={pkg.code}
                    type="button"
                    variant={watchedPlan === pkg.code ? "default" : "outline"}
                    className="justify-between h-auto py-3"
                    onClick={() => setValue("plan", pkg.code)}
                  >
                    <div className="text-left">
                      <p className="font-semibold">{pkg.name}</p>
                      <p className="text-xs opacity-70">{pkg.validity}</p>
                    </div>
                    <span className="font-bold">{formatCurrency(pkg.price)}</span>
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

          {selectedPackage && (
            <div className="rounded-lg bg-muted p-4 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Provider</span>
                <span className="font-medium">
                  {CABLE_PROVIDERS.find((p) => p.code === watchedProvider)?.name}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Smart Card</span>
                <span className="font-medium">{watchedSmartCard || "—"}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-muted-foreground">Plan</span>
                <span className="font-medium">{selectedPackage.name}</span>
              </div>
              <Separator />
              <div className="flex justify-between font-semibold">
                <span>Total</span>
                <span className="text-primary">{formatCurrency(selectedPackage.price)}</span>
              </div>
            </div>
          )}

          <Button
            type="submit"
            className="w-full"
            size="lg"
            disabled={isCableLoading || !watchedPlan}
          >
            {isCableLoading ? (
              <div className="flex items-center gap-2">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Processing...
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <Zap className="h-4 w-4" />
                Subscribe to Cable TV
              </div>
            )}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
