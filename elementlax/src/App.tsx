import { useCallback, useEffect, useState } from "react";
import { BottomNav } from "./components/BottomNav";
import { ToastHost } from "./components/Toast";
import { useRoute } from "./lib/router";
import { useStore } from "./lib/store";
import { About } from "./screens/About";
import { ElementScreen } from "./screens/ElementScreen";
import { LockScreen, Onboarding, Splash } from "./screens/Gates";
import { Home } from "./screens/Home";
import { Library } from "./screens/Library";
import { Settings } from "./screens/Settings";
import { Study } from "./screens/Study";

export default function App() {
  const route = useRoute();
  const { onboarded, pin } = useStore();
  const [splash, setSplash] = useState(true);
  const [unlocked, setUnlocked] = useState(false);
  const endSplash = useCallback(() => setSplash(false), []);
  const unlock = useCallback(() => setUnlocked(true), []);

  useEffect(() => {
    document.title = "جدول تناوبی | Element.Lax";
    const isNative = Boolean(
      (
        window as unknown as {
          Capacitor?: { isNativePlatform?: () => boolean };
        }
      ).Capacitor?.isNativePlatform?.()
    );
    if (!isNative && "serviceWorker" in navigator && location.protocol.startsWith("http")) {
      navigator.serviceWorker.register("./sw.js").catch(() => undefined);
    }

    let disposeBackListener: (() => void) | undefined;
    if (isNative) {
      import("@capacitor/app")
        .then(({ App: CapApp }) => {
          const listenerPromise = CapApp.addListener("backButton", ({ canGoBack }) => {
            const currentHash = window.location.hash.replace(/^#\/?/, "");
            if (currentHash && canGoBack) {
              window.history.back();
            } else if (currentHash) {
              window.location.hash = "#/";
            } else {
              CapApp.exitApp();
            }
          });
          disposeBackListener = () => {
            listenerPromise.then((handle) => handle.remove()).catch(() => undefined);
          };
        })
        .catch(() => undefined);
    }

    return () => {
      disposeBackListener?.();
    };
  }, []);

  const [screen, arg] = route;
  const tab = screen === undefined || screen === "" ? "" : screen;
  const showNav = ["", "study", "library", "settings"].includes(tab);

  if (splash) return <Splash onDone={endSplash} />;
  if (pin && !unlocked) return <LockScreen pin={pin} onUnlock={unlock} />;
  if (!onboarded) return <Onboarding />;

  return (
    <div className="h-dvh w-full flex flex-col overflow-hidden bg-bg text-fg">
      <main className="flex-1 min-h-0 relative">
        {/* Home stays mounted so scroll position, search and filters are preserved */}
        <div className={"absolute inset-0 " + (tab === "" ? "" : "hidden")}>
          <Home />
        </div>
        {tab === "e" && <div className="absolute inset-0 z-10 bg-bg"><ElementScreen z={Number(arg)} /></div>}
        {tab === "study" && <div className="absolute inset-0"><Study /></div>}
        {tab === "library" && <div className="absolute inset-0"><Library /></div>}
        {tab === "settings" && <div className="absolute inset-0"><Settings /></div>}
        {tab === "about" && <div className="absolute inset-0 z-10 bg-bg"><About /></div>}
      </main>
      {showNav && <BottomNav active={tab} />}
      <ToastHost />
    </div>
  );
}
