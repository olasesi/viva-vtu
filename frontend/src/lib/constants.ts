export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:3000";

export interface NetworkProvider {
  code: string;
  name: string;
  color: string;
  logo: string;
}

export const NETWORK_PROVIDERS: NetworkProvider[] = [
  {
    code: "mtn",
    name: "MTN",
    color: "#FFCC00",
    logo: "/providers/mtn.svg",
  },
  {
    code: "airtel",
    name: "Airtel",
    color: "#ED1C24",
    logo: "/providers/airtel.svg",
  },
  {
    code: "glo",
    name: "Glo",
    color: "#00A651",
    logo: "/providers/glo.svg",
  },
  {
    code: "9mobile",
    name: "9mobile",
    color: "#006B3F",
    logo: "/providers/9mobile.svg",
  },
];

export interface ElectricityDistributor {
  code: string;
  name: string;
  state: string;
}

export const ELECTRICITY_DISTRIBUTORS: ElectricityDistributor[] = [
  { code: "ikedc", name: "Ikeja Electric", state: "Lagos" },
  { code: "ekedc", name: "Eko Electricity", state: "Lagos" },
  { code: "ibedc", name: "Ibadan Electricity", state: "Oyo" },
  { code: "bedc", name: "Benin Electricity", state: "Edo" },
  { code: "aedc", name: "Abuja Electricity", state: "FCT" },
  { code: "kedco", name: "Kano Electricity", state: "Kano" },
  { code: "jcedc", name: "Jos Electricity", state: "Plateau" },
  { code: "yedc", name: "Yola Electricity", state: "Adamawa" },
  { code: "phedc", name: "Port Harcourt Electric", state: "Rivers" },
  { code: "eedc", name: "Enugu Electricity", state: "Enugu" },
];

export interface CableProvider {
  code: string;
  name: string;
  packages: CablePackage[];
}

export interface CablePackage {
  code: string;
  name: string;
  price: number;
  validity: string;
}

export const CABLE_PROVIDERS: CableProvider[] = [
  {
    code: "dstv",
    name: "DStv",
    packages: [
      { code: "dstv-padi", name: "DStv Padi", price: 2150, validity: "1 month" },
      { code: "dstv-yanga", name: "DStv Yanga", price: 3600, validity: "1 month" },
      { code: "dstv-confam", name: "DStv Confam", price: 5400, validity: "1 month" },
      { code: "dstv-premium", name: "DStv Premium", price: 21000, validity: "1 month" },
      { code: "dstv-compact", name: "DStv Compact", price: 7500, validity: "1 month" },
    ],
  },
  {
    code: "gotv",
    name: "GOtv",
    packages: [
      { code: "gotv-lite", name: "GOtv Lite", price: 1100, validity: "1 month" },
      { code: "gotv-plus", name: "GOtv Plus", price: 2600, validity: "1 month" },
      { code: "gotv-max", name: "GOtv Max", price: 4850, validity: "1 month" },
      { code: "gotv-supa", name: "GOtv Supa", price: 6200, validity: "1 month" },
    ],
  },
  {
    code: "startimes",
    name: "StarTimes",
    packages: [
      { code: "st-nova", name: "Nova", price: 1200, validity: "1 month" },
      { code: "st-basic", name: "Basic", price: 2000, validity: "1 month" },
      { code: "st-classic", name: "Classic", price: 2800, validity: "1 month" },
      { code: "st-premium", name: "Premium", price: 4500, validity: "1 month" },
      { code: "st-sports", name: "Sports", price: 5500, validity: "1 month" },
    ],
  },
];

export interface DataPlan {
  code: string;
  name: string;
  network: string;
  amount: number;
  validity: string;
  description: string;
}

export const DATA_PLANS: DataPlan[] = [
  { code: "mtn-1gb", name: "1GB", network: "mtn", amount: 350, validity: "30 days", description: "1GB 30 days" },
  { code: "mtn-2gb", name: "2GB", network: "mtn", amount: 700, validity: "30 days", description: "2GB 30 days" },
  { code: "mtn-3gb", name: "3GB", network: "mtn", amount: 1000, validity: "30 days", description: "3GB 30 days" },
  { code: "mtn-5gb", name: "5GB", network: "mtn", amount: 1500, validity: "30 days", description: "5GB 30 days" },
  { code: "mtn-10gb", name: "10GB", network: "mtn", amount: 3000, validity: "30 days", description: "10GB 30 days" },

  { code: "airtel-1gb", name: "1GB", network: "airtel", amount: 350, validity: "30 days", description: "1GB 30 days" },
  { code: "airtel-2gb", name: "2GB", network: "airtel", amount: 700, validity: "30 days", description: "2GB 30 days" },
  { code: "airtel-3gb", name: "3GB", network: "airtel", amount: 1000, validity: "30 days", description: "3GB 30 days" },
  { code: "airtel-5gb", name: "5GB", network: "airtel", amount: 1500, validity: "30 days", description: "5GB 30 days" },
  { code: "airtel-10gb", name: "10GB", network: "airtel", amount: 3000, validity: "30 days", description: "10GB 30 days" },

  { code: "glo-1gb", name: "1GB", network: "glo", amount: 350, validity: "30 days", description: "1GB 30 days" },
  { code: "glo-2gb", name: "2GB", network: "glo", amount: 700, validity: "30 days", description: "2GB 30 days" },
  { code: "glo-3gb", name: "3GB", network: "glo", amount: 1000, validity: "30 days", description: "3GB 30 days" },
  { code: "glo-5gb", name: "5GB", network: "glo", amount: 1500, validity: "30 days", description: "5GB 30 days" },
  { code: "glo-10gb", name: "10GB", network: "glo", amount: 3000, validity: "30 days", description: "10GB 30 days" },

  { code: "9mobile-1gb", name: "1GB", network: "9mobile", amount: 350, validity: "30 days", description: "1GB 30 days" },
  { code: "9mobile-2gb", name: "2GB", network: "9mobile", amount: 700, validity: "30 days", description: "2GB 30 days" },
  { code: "9mobile-3gb", name: "3GB", network: "9mobile", amount: 1000, validity: "30 days", description: "3GB 30 days" },
  { code: "9mobile-5gb", name: "5GB", network: "9mobile", amount: 1500, validity: "30 days", description: "5GB 30 days" },
  { code: "9mobile-10gb", name: "10GB", network: "9mobile", amount: 3000, validity: "30 days", description: "10GB 30 days" },
];

export const AIRTIME_AMOUNTS = [100, 200, 500, 1000, 2000, 3000, 5000];

export const NAV_LINKS = [
  { label: "Home", href: "/" },
  { label: "About", href: "/#about" },
  { label: "Services", href: "/#services" },
  { label: "Pricing", href: "/#pricing" },
];
