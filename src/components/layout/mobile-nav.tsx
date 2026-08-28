"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useState } from "react";
import { useAuthStore } from "@/stores/auth-store";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { Menu, X, Wallet } from "lucide-react";
import { NAV_LINKS } from "@/lib/constants";
import { useWallet } from "@/hooks/use-wallet";
import { formatCurrency } from "@/lib/utils";

export function MobileNav() {
  const pathname = usePathname();
  const router = useRouter();
  const { user, isAuthenticated, logout } = useAuthStore();
  const { balance } = useWallet();
  const [open, setOpen] = useState(false);

  if (pathname.startsWith("/dashboard")) return null;

  return (
    <div className="md:hidden">
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetTrigger asChild>
          <Button variant="ghost" size="icon" className="lg:hidden">
            <Menu className="h-5 w-5" />
          </Button>
        </SheetTrigger>
        <SheetContent side="right" className="w-[280px]">
          <div className="flex flex-col h-full">
            <div className="flex items-center justify-between mb-8">
              <Link href="/" className="flex items-center gap-2" onClick={() => setOpen(false)}>
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-white font-bold text-sm">
                  V
                </div>
                <span className="text-lg font-bold text-primary">VivaVTU</span>
              </Link>
            </div>

            <nav className="flex-1 space-y-2">
              {NAV_LINKS.map((link) => (
                <Link
                  key={link.href}
                  href={link.href}
                  className="block px-3 py-2 text-sm font-medium rounded-lg hover:bg-muted"
                  onClick={() => setOpen(false)}
                >
                  {link.label}
                </Link>
              ))}
            </nav>

            <div className="space-y-3 pt-4 border-t">
              {isAuthenticated ? (
                <>
                  <div className="px-3">
                    <Badge variant="outline" className="gap-1 py-1 px-3">
                      <Wallet className="h-3.5 w-3.5 text-primary" />
                      <span className="font-semibold text-primary">{formatCurrency(balance?.balance || 0)}</span>
                    </Badge>
                  </div>
                  <Button
                    variant="outline"
                    className="w-full"
                    onClick={() => { router.push("/dashboard"); setOpen(false); }}
                  >
                    Dashboard
                  </Button>
                  <Button
                    variant="ghost"
                    className="w-full text-red-600"
                    onClick={() => { logout(); router.push("/"); setOpen(false); }}
                  >
                    Log out
                  </Button>
                </>
              ) : (
                <>
                  <Button
                    variant="outline"
                    className="w-full"
                    onClick={() => { router.push("/login"); setOpen(false); }}
                  >
                    Log in
                  </Button>
                  <Button
                    className="w-full"
                    onClick={() => { router.push("/register"); setOpen(false); }}
                  >
                    Get Started
                  </Button>
                </>
              )}
            </div>
          </div>
        </SheetContent>
      </Sheet>
    </div>
  );
}
