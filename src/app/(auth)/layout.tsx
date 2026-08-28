import Link from "next/link";

export default function AuthLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="min-h-screen flex">
      {/* Left side - branding */}
      <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary to-emerald-700 relative overflow-hidden">
        <div className="absolute inset-0 bg-black/10" />
        <div className="relative z-10 flex flex-col justify-center px-16 text-white">
          <Link href="/" className="flex items-center gap-2 mb-8">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-primary font-bold text-lg">
              V
            </div>
            <span className="text-2xl font-bold">VivaVTU</span>
          </Link>
          <h1 className="text-4xl font-bold mb-4">
            Nigeria&apos;s Most Trusted VTU Platform
          </h1>
          <p className="text-emerald-100 text-lg max-w-md">
            Buy data, airtime, pay electricity bills, and subscribe to cable TV. Fast, reliable, and secure.
          </p>
          <div className="mt-12 space-y-4">
            {[
              "Instant data & airtime delivery",
              "Pay electricity bills instantly",
              "DStv, GOtv & StarTimes subscriptions",
              "24/7 customer support",
            ].map((item, i) => (
              <div key={i} className="flex items-center gap-3">
                <div className="h-6 w-6 rounded-full bg-white/20 flex items-center justify-center">
                  <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <span className="text-emerald-100">{item}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Right side - form */}
      <div className="flex-1 flex items-center justify-center px-4 py-12">
        <div className="w-full max-w-md">
          <div className="lg:hidden mb-8">
            <Link href="/" className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-white font-bold text-sm">
                V
              </div>
              <span className="text-xl font-bold text-primary">VivaVTU</span>
            </Link>
          </div>
          {children}
        </div>
      </div>
    </div>
  );
}
