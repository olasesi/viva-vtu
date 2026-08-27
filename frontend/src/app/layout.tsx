import type { Metadata } from "next";
import { Inter } from "next/font/google";
import { Toaster } from "sonner";
import { QueryProvider } from "@/components/providers/query-provider";
import "./globals.css";

const inter = Inter({ subsets: ["latin"] });

export const metadata: Metadata = {
  title: "VivaVTU - Buy Data, Airtime & Pay Bills in Nigeria",
  description:
    "Nigeria's most trusted VTU platform. Buy data, airtime, pay electricity bills, and subscribe to cable TV instantly. Fast, reliable, and secure.",
  keywords: [
    "VTU Nigeria",
    "buy data Nigeria",
    "buy airtime",
    "electricity bill payment",
    "cable TV subscription",
    "DStv GOtv StarTimes",
    "MTN Airtel Glo 9mobile",
  ],
  openGraph: {
    title: "VivaVTU - Buy Data, Airtime & Pay Bills in Nigeria",
    description:
      "Nigeria's most trusted VTU platform. Buy data, airtime, pay electricity bills, and subscribe to cable TV instantly.",
    type: "website",
    locale: "en_NG",
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body className={inter.className}>
        <QueryProvider>
          {children}
          <Toaster position="top-right" richColors />
        </QueryProvider>
      </body>
    </html>
  );
}
