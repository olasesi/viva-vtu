"use client";

import { Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { loginSchema, type LoginInput } from "@/lib/validators";
import { useAuth } from "@/hooks/use-auth";
import { initializeAuth, useAuthStore } from "@/stores/auth-store";
import { Mail, Lock, LogIn, ShieldCheck, KeyRound } from "lucide-react";

function LoginContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const isTwoFactorStep = searchParams.get("step") === "2fa";

  const {
    login,
    isLoginLoading,
    loginError,
    verifyTwoFactor,
    isVerifyTwoFactorLoading,
    verifyTwoFactorError,
  } = useAuth();
  const { isAuthenticated } = useAuthStore();

  const [code, setCode] = useState("");

  useEffect(() => {
    initializeAuth();
  }, []);

  useEffect(() => {
    if (isAuthenticated) {
      router.push("/dashboard");
    }
  }, [isAuthenticated, router]);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginInput>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = (data: LoginInput) => {
    login(data);
  };

  const onVerify = (e: React.FormEvent) => {
    e.preventDefault();

    const challengeToken = sessionStorage.getItem("viva-2fa-challenge");

    if (!challengeToken) {
      router.push("/login");
      return;
    }

    verifyTwoFactor({ challengeToken, code });
  };

  if (isTwoFactorStep) {
    return (
      <Card className="border-0 shadow-lg">
        <CardHeader className="text-center">
          <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
            <ShieldCheck className="h-6 w-6 text-primary" />
          </div>
          <CardTitle className="text-2xl">Two-Factor Authentication</CardTitle>
          <CardDescription>
            Enter the 6-digit code from your authenticator app, or a recovery code.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={onVerify} className="space-y-4">
            {verifyTwoFactorError && (
              <div className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-600">
                {(verifyTwoFactorError as any)?.response?.data?.message ||
                  "Verification failed. Please try again."}
              </div>
            )}

            <div className="space-y-2">
              <Label htmlFor="code">Authentication Code</Label>
              <div className="relative">
                <KeyRound className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                <Input
                  id="code"
                  inputMode="text"
                  autoComplete="one-time-code"
                  placeholder="123456"
                  className="pl-10 tracking-widest"
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                />
              </div>
            </div>

            <Button
              type="submit"
              className="w-full"
              disabled={isVerifyTwoFactorLoading || code.trim().length === 0}
            >
              {isVerifyTwoFactorLoading ? (
                <div className="flex items-center gap-2">
                  <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  Verifying...
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <ShieldCheck className="h-4 w-4" />
                  Verify & Sign In
                </div>
              )}
            </Button>

            <Button
              type="button"
              variant="ghost"
              className="w-full"
              onClick={() => {
                sessionStorage.removeItem("viva-2fa-challenge");
                router.push("/login");
              }}
            >
              Back to login
            </Button>
          </form>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="border-0 shadow-lg">
      <CardHeader className="text-center">
        <CardTitle className="text-2xl">Welcome Back</CardTitle>
        <CardDescription>Sign in to your VivaVTU account</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          {loginError && (
            <div className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-600">
              {(loginError as any)?.response?.data?.message || "Login failed. Please try again."}
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="email">Email</Label>
            <div className="relative">
              <Mail className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
              <Input
                id="email"
                type="email"
                placeholder="your@email.com"
                className="pl-10"
                {...register("email")}
              />
            </div>
            {errors.email && (
              <p className="text-sm text-red-500">{errors.email.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="password">Password</Label>
            <div className="relative">
              <Lock className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
              <Input
                id="password"
                type="password"
                placeholder="Enter your password"
                className="pl-10"
                {...register("password")}
              />
            </div>
            {errors.password && (
              <p className="text-sm text-red-500">{errors.password.message}</p>
            )}
          </div>

          <Button type="submit" className="w-full" disabled={isLoginLoading}>
            {isLoginLoading ? (
              <div className="flex items-center gap-2">
                <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Signing in...
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <LogIn className="h-4 w-4" />
                Sign In
              </div>
            )}
          </Button>

          <div className="relative my-4">
            <Separator />
            <span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-card px-2 text-xs text-muted-foreground">
              OR
            </span>
          </div>

          <p className="text-center text-sm text-muted-foreground">
            Don&apos;t have an account?{" "}
            <Link href="/register" className="text-primary font-semibold hover:underline">
              Sign up
            </Link>
          </p>
        </form>
      </CardContent>
    </Card>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex justify-center py-10">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
      }
    >
      <LoginContent />
    </Suspense>
  );
}
