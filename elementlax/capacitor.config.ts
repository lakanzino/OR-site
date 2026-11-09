import type { CapacitorConfig } from "@capacitor/cli";

const config: CapacitorConfig = {
  appId: "com.elementlax.app",
  appName: "جدول تناوبی",
  webDir: "dist",
  android: {
    backgroundColor: "#03070a",
    adjustMarginsForEdgeToEdge: "force",
  },
};

export default config;
