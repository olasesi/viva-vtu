/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "**",
      },
    ],
  },
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || "http://localhost:3000",
    NEXT_PUBLIC_PAYSTACK_KEY: process.env.NEXT_PUBLIC_PAYSTACK_KEY || "",
    NEXT_PUBLIC_FLUTTERWAVE_KEY: process.env.NEXT_PUBLIC_FLUTTERWAVE_KEY || "",
  },
};

module.exports = nextConfig;
