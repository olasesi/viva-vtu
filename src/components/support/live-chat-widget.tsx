"use client";

import { useEffect, useRef } from "react";
import { usePublicSettings } from "@/hooks/use-settings";

declare global {
  interface Window {
    Tawk_API?: Record<string, unknown>;
    Tawk_LoadStart?: Date;
    $crisp?: unknown[];
    CRISP_WEBSITE_ID?: string;
  }
}

const ID_PATTERN = /^[A-Za-z0-9_-]+$/;

function readString(value: unknown): string {
  return typeof value === "string" ? value.trim() : "";
}

export function LiveChatWidget() {
  const { data } = usePublicSettings();
  const injectedRef = useRef<string | null>(null);

  const support = data?.data?.support?.fields;
  const enabled = Boolean(support?.live_chat_enabled);
  const provider = readString(support?.live_chat_provider) || "tawkto";
  const propertyId = readString(support?.live_chat_property_id);
  const widgetId = readString(support?.live_chat_widget_id);

  useEffect(() => {
    if (!enabled || typeof document === "undefined") {
      return;
    }

    if (!ID_PATTERN.test(propertyId) || !ID_PATTERN.test(widgetId)) {
      return;
    }

    const signature = `${provider}:${propertyId}:${widgetId}`;

    if (injectedRef.current === signature) {
      return;
    }

    injectedRef.current = signature;

    if (provider === "crisp") {
      window.$crisp = window.$crisp || [];
      window.CRISP_WEBSITE_ID = widgetId;

      if (!document.getElementById("crisp-chat-script")) {
        const script = document.createElement("script");
        script.id = "crisp-chat-script";
        script.src = "https://client.crisp.chat/l.js";
        script.async = true;
        document.head.appendChild(script);
      }

      return;
    }

    if (document.getElementById("tawkto-chat-script")) {
      return;
    }

    window.Tawk_API = window.Tawk_API || {};
    window.Tawk_LoadStart = new Date();

    const script = document.createElement("script");
    script.id = "tawkto-chat-script";
    script.async = true;
    script.src = `https://embed.tawk.to/${propertyId}/${widgetId}`;
    script.charset = "UTF-8";
    script.setAttribute("crossorigin", "*");
    document.head.appendChild(script);
  }, [enabled, provider, propertyId, widgetId]);

  return null;
}
