import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "@fontsource-variable/vazirmatn"; // bundled Vazirmatn (offline)
import "./index.css";
import App from "./App";

// Tell the boot watchdog in index.html that we made it; it removes the splash
// layer and stops the "app did not start" timeout.
declare global {
  interface Window {
    __GLOBELAX_BOOTED__?: () => void;
  }
}

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <App />
  </StrictMode>
);

// Remove the native boot layer after the first commit so the user never sees
// a flash of the loading placeholder on top of the real UI.
requestAnimationFrame(() => window.__GLOBELAX_BOOTED__?.());
