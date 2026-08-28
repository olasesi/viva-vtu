"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Navbar } from "@/components/layout/navbar";
import { Footer } from "@/components/layout/footer";
import { MobileNav } from "@/components/layout/mobile-nav";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { useAuthStore } from "@/stores/auth-store";
import { initializeAuth } from "@/stores/auth-store";
import {
  Wifi,
  Phone,
  Zap,
  Tv,
  Shield,
  Clock,
  CreditCard,
  HeadphonesIcon,
  ArrowRight,
  Check,
  Star,
  Smartphone,
  Zap as ZapIcon,
} from "lucide-react";

export default function HomePage() {
  const router = useRouter();
  const { isAuthenticated } = useAuthStore();

  useEffect(() => {
    initializeAuth();
  }, []);

  const features = [
    {
      icon: Wifi,
      title: "Data & Airtime",
      description:
        "Buy data and airtime for all Nigerian networks at the cheapest rates. Instant delivery to your phone.",
      color: "bg-blue-100 text-blue-600",
    },
    {
      icon: Zap,
      title: "Electricity Bills",
      description:
        "Pay your electricity bills instantly. Supports all distribution companies across Nigeria.",
      color: "bg-amber-100 text-amber-600",
    },
    {
      icon: Tv,
      title: "Cable TV",
      description:
        "Subscribe to DStv, GOtv, and StarTimes. All packages available at competitive prices.",
      color: "bg-purple-100 text-purple-600",
    },
    {
      icon: CreditCard,
      title: "Instant Wallet",
      description:
        "Fund your wallet securely via Paystack or Flutterwave. Use your balance for all services.",
      color: "bg-emerald-100 text-emerald-600",
    },
  ];

  const steps = [
    {
      step: "01",
      title: "Create Account",
      description: "Sign up for free in less than a minute. No hidden charges.",
    },
    {
      step: "02",
      title: "Fund Wallet",
      description: "Add funds to your wallet via Paystack or Flutterwave.",
    },
    {
      step: "03",
      title: "Start Transacting",
      description: "Buy data, airtime, pay bills and subscribe to cable TV.",
    },
  ];

  const plans = [
    { network: "MTN", data: "1GB", price: 350, validity: "30 days" },
    { network: "MTN", data: "2GB", price: 700, validity: "30 days" },
    { network: "Airtel", data: "1GB", price: 350, validity: "30 days" },
    { network: "Airtel", data: "3GB", price: 1000, validity: "30 days" },
    { network: "Glo", data: "2GB", price: 700, validity: "30 days" },
    { network: "9mobile", data: "1GB", price: 350, validity: "30 days" },
  ];

  const testimonials = [
    {
      name: "Adebayo O.",
      location: "Lagos",
      rating: 5,
      comment: "Best VTU platform in Nigeria! Instant data delivery and great customer support.",
    },
    {
      name: "Chioma N.",
      location: "Abuja",
      rating: 5,
      comment: "I love how easy it is to pay my electricity bill. No more queuing at offices!",
    },
    {
      name: "Ibrahim K.",
      location: "Kano",
      rating: 5,
      comment: "Very affordable rates. I always use VivaVTU for all my subscriptions.",
    },
  ];

  return (
    <div className="min-h-screen flex flex-col">
      <div className="flex items-center justify-center">
        <Navbar />
      </div>

      {/* Hero Section */}
      <section className="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-primary to-emerald-800 text-white">
        <div className="absolute inset-0 bg-[url('data:image/svg+xml,...')] opacity-5" />
        <div className="container relative py-20 lg:py-32">
          <div className="max-w-3xl mx-auto text-center">
            <Badge className="mb-6 bg-white/20 text-white border-white/30 hover:bg-white/30">
              Nigeria&apos;s #1 VTU Platform
            </Badge>
            <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight">
              Buy Data, Airtime & Pay Bills
              <span className="block text-emerald-200">Instantly</span>
            </h1>
            <p className="text-lg md:text-xl text-emerald-100 mb-8 max-w-2xl mx-auto">
              The fastest and most reliable platform to buy data, airtime, pay electricity bills, and subscribe to cable TV in Nigeria.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button
                size="lg"
                className="bg-white text-primary hover:bg-emerald-50 text-lg px-8"
                onClick={() => router.push(isAuthenticated ? "/dashboard" : "/register")}
              >
                Get Started Free
                <ArrowRight className="ml-2 h-5 w-5" />
              </Button>
              <Button
                size="lg"
                variant="outline"
                className="border-white/30 text-white hover:bg-white/10 text-lg px-8"
                onClick={() => router.push(isAuthenticated ? "/dashboard" : "/login")}
              >
                {isAuthenticated ? "Go to Dashboard" : "Sign In"}
              </Button>
            </div>
            <div className="flex items-center justify-center gap-8 mt-12 text-emerald-200 text-sm">
              <div className="flex items-center gap-2">
                <Check className="h-4 w-4" />
                <span>Instant Delivery</span>
              </div>
              <div className="flex items-center gap-2">
                <Check className="h-4 w-4" />
                <span>24/7 Support</span>
              </div>
              <div className="flex items-center gap-2">
                <Check className="h-4 w-4" />
                <span>Best Rates</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="services" className="py-20 bg-gray-50">
        <div className="container">
          <div className="text-center mb-12">
            <Badge variant="outline" className="mb-4">Our Services</Badge>
            <h2 className="text-3xl md:text-4xl font-bold mb-4">
              Everything You Need
            </h2>
            <p className="text-muted-foreground max-w-2xl mx-auto">
              From data bundles to electricity payments, we&apos;ve got you covered with the best rates and instant delivery.
            </p>
          </div>
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            {features.map((feature) => (
              <Card key={feature.title} className="border-0 shadow-md hover:shadow-lg transition-shadow">
                <CardContent className="p-6">
                  <div className={`h-12 w-12 rounded-xl ${feature.color} flex items-center justify-center mb-4`}>
                    <feature.icon className="h-6 w-6" />
                  </div>
                  <h3 className="font-semibold text-lg mb-2">{feature.title}</h3>
                  <p className="text-sm text-muted-foreground">{feature.description}</p>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* How It Works */}
      <section className="py-20">
        <div className="container">
          <div className="text-center mb-12">
            <Badge variant="outline" className="mb-4">How It Works</Badge>
            <h2 className="text-3xl md:text-4xl font-bold mb-4">
              Get Started in 3 Simple Steps
            </h2>
            <p className="text-muted-foreground max-w-2xl mx-auto">
              Our platform is designed to be simple and easy to use.
            </p>
          </div>
          <div className="grid gap-8 md:grid-cols-3">
            {steps.map((step) => (
              <div key={step.step} className="text-center">
                <div className="h-16 w-16 rounded-full bg-primary text-white flex items-center justify-center text-2xl font-bold mx-auto mb-4">
                  {step.step}
                </div>
                <h3 className="font-semibold text-lg mb-2">{step.title}</h3>
                <p className="text-sm text-muted-foreground">{step.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Popular Plans */}
      <section id="pricing" className="py-20 bg-gray-50">
        <div className="container">
          <div className="text-center mb-12">
            <Badge variant="outline" className="mb-4">Popular Plans</Badge>
            <h2 className="text-3xl md:text-4xl font-bold mb-4">
              Affordable Data Plans
            </h2>
            <p className="text-muted-foreground max-w-2xl mx-auto">
              We offer the most competitive data prices across all networks.
            </p>
          </div>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {plans.map((plan, i) => (
              <Card key={i} className="hover:shadow-md transition-shadow">
                <CardContent className="p-6 flex items-center justify-between">
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <Smartphone className="h-4 w-4 text-primary" />
                      <span className="font-semibold">{plan.network}</span>
                    </div>
                    <p className="text-2xl font-bold text-primary">{plan.data}</p>
                    <p className="text-xs text-muted-foreground">{plan.validity}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-2xl font-bold">₦{plan.price}</p>
                    <Button
                      size="sm"
                      className="mt-2"
                      onClick={() => router.push(isAuthenticated ? "/dashboard/buy/data" : "/register")}
                    >
                      Buy Now
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Testimonials */}
      <section className="py-20">
        <div className="container">
          <div className="text-center mb-12">
            <Badge variant="outline" className="mb-4">Testimonials</Badge>
            <h2 className="text-3xl md:text-4xl font-bold mb-4">
              What Our Users Say
            </h2>
          </div>
          <div className="grid gap-6 md:grid-cols-3">
            {testimonials.map((testimonial, i) => (
              <Card key={i} className="border-0 shadow-md">
                <CardContent className="p-6">
                  <div className="flex gap-1 mb-3">
                    {[...Array(testimonial.rating)].map((_, j) => (
                      <Star key={j} className="h-4 w-4 fill-amber-400 text-amber-400" />
                    ))}
                  </div>
                  <p className="text-sm text-muted-foreground mb-4">
                    &ldquo;{testimonial.comment}&rdquo;
                  </p>
                  <div>
                    <p className="font-semibold text-sm">{testimonial.name}</p>
                    <p className="text-xs text-muted-foreground">{testimonial.location}</p>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 bg-gradient-to-r from-primary to-emerald-700 text-white">
        <div className="container text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-4">
            Ready to Start?
          </h2>
          <p className="text-emerald-100 text-lg mb-8 max-w-2xl mx-auto">
            Join thousands of Nigerians who trust VivaVTU for their daily transactions.
            Create an account today and enjoy the best rates.
          </p>
          <Button
            size="lg"
            className="bg-white text-primary hover:bg-emerald-50 text-lg px-8"
            onClick={() => router.push(isAuthenticated ? "/dashboard" : "/register")}
          >
            {isAuthenticated ? "Go to Dashboard" : "Create Free Account"}
            <ArrowRight className="ml-2 h-5 w-5" />
          </Button>
        </div>
      </section>

      <Footer />
    </div>
  );
}
