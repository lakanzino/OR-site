import { useCallback, useEffect, useState } from "react";
import { BookOpen, Brain, Home as HomeIcon, Map as MapIcon, Moon, Settings as Gear, Sun, Trophy } from "lucide-react";
import { Logo } from "@/components/Logo";
import { LockScreen } from "@/components/Lock";
import { ToastProvider } from "@/components/ui";
import Onboarding from "@/components/Onboarding";
import Home from "@/pages/Home";
import Detail from "@/pages/Detail";
import Flashcards from "@/pages/Flashcards";
import Quiz from "@/pages/Quiz";
import MapPage from "@/pages/MapPage";
import Saved from "@/pages/Saved";
import Settings from "@/pages/Settings";
import About from "@/pages/About";
import { go, useRoute } from "@/lib/router";
import { actions, useAppState } from "@/lib/store";

const TABS = [
  { id: "", fa: "خانه", icon: HomeIcon },
  { id: "map", fa: "نقشه", icon: MapIcon },
  { id: "quiz", fa: "آزمون", icon: Brain },
  { id: "flash", fa: "فلش‌کارت", icon: BookOpen },
  { id: "saved", fa: "پیشرفت", icon: Trophy },
];

export default function App() {
  const { settings } = useAppState();
  const route = useRoute();
  const [splash, setSplash] = useState(true);
  const [locked, setLocked] = useState(!!settings.pinHash);
  const unlock = useCallback(() => setLocked(false), []);

  // theme + font scaling
  useEffect(() => {
    document.documentElement.dataset.theme = settings.theme;
    document.documentElement.style.fontSize = `${16 * settings.fontScale}px`;
    document.querySelector('meta[name="theme-color"]')?.setAttribute("content", settings.theme === "dark" ? "#040c24" : "#f7f5ee");
  }, [settings.theme, settings.fontScale]);

  useEffect(() => {
    const t = setTimeout(() => setSplash(false), 1100);
    return () => clearTimeout(t);
  }, []);

  const page = route.path[0] ?? "";
  const isTab = TABS.some((t) => t.id === page);
  const showShell = isTab;

  let view;
  switch (page) {
    case "country": view = <Detail iso={route.path[1] ?? ""} />; break;
    case "map": view = <MapPage />; break;
    case "quiz": view = <Quiz route={route} />; break;
    case "flash": view = <Flashcards route={route} />; break;
    case "saved": view = <Saved />; break;
    case "settings": view = <Settings />; break;
    case "about": view = <About />; break;
    default: view = <Home />;
  }

  return (
    <ToastProvider>
      {showShell && (
        <header className="sticky top-0 z-30 backdrop-blur-xl" style={{ background: "color-mix(in srgb, var(--bg) 78%, transparent)", paddingTop: "env(safe-area-inset-top)" }}>
          <div className="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4">
            <button className="flex items-center gap-2" onClick={() => go("")} aria-label="Globe.Lax">
              <Logo size={40} className="drop-shadow-lg" />
              <span className="text-xl font-black tracking-tight">Globe<span style={{ color: "var(--red)" }}>.</span><span style={{ color: "var(--accent-text)" }}>Lax</span></span>
            </button>
            <div className="flex-1" />
            <button className="btn btn-ghost !min-h-11 !w-11 !px-0" onClick={() => actions.setSettings({ theme: settings.theme === "dark" ? "light" : "dark" })} aria-label="تغییر پوسته">
              {settings.theme === "dark" ? <Sun size={20} /> : <Moon size={20} />}
            </button>
            <button className="btn btn-ghost !min-h-11 !w-11 !px-0" onClick={() => go("settings")} aria-label="تنظیمات"><Gear size={20} /></button>
          </div>
        </header>
      )}

      <main className={`mx-auto max-w-6xl px-4 ${showShell ? "pb-32 pt-3" : "pt-4"}`} style={{ paddingTop: showShell ? undefined : "max(16px, env(safe-area-inset-top))" }}>
        <div key={page + (route.path[1] ?? "")} className={page === "country" ? "" : "anim-up"}>{view}</div>
      </main>

      {showShell && (
        <nav className="fixed inset-x-0 bottom-0 z-40 flex justify-center px-3" style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }} aria-label="ناوبری اصلی">
          <div className="bottom-nav grid w-full max-w-xl grid-cols-5 gap-1 rounded-3xl p-1.5">
            {TABS.map((t) => {
              const active = page === t.id;
              return (
                <button
                  key={t.id}
                  onClick={() => go(t.id)}
                  aria-current={active ? "page" : undefined}
                  className="flex min-h-[58px] flex-col items-center justify-center gap-0.5 rounded-2xl text-[11px] font-extrabold transition-all"
                  style={{
                    background: active ? "linear-gradient(180deg,#ffe066,var(--yellow))" : "transparent",
                    color: active ? "#0b1f4b" : "var(--muted)",
                    boxShadow: active ? "0 4px 0 var(--yellow-deep), 0 10px 18px -6px var(--glow)" : "none",
                    transform: active ? "translateY(-4px) scale(1.04)" : "none",
                  }}
                >
                  <t.icon size={22} strokeWidth={active ? 2.8 : 2} />
                  {t.fa}
                </button>
              );
            })}
          </div>
        </nav>
      )}

      {!settings.onboarded && !splash && <Onboarding />}
      {locked && settings.pinHash && !splash && <LockScreen hash={settings.pinHash} onUnlock={unlock} />}

      {splash && (
        <div className="fixed inset-0 z-[120] flex flex-col items-center justify-center gap-4" style={{ background: "radial-gradient(circle at 50% 40%, #14307f, #040c24 70%)", animation: "fadeOut 0.4s ease 0.75s forwards" }}>
          <Logo size={140} className="anim-pop drop-shadow-2xl" />
          <div className="anim-up text-3xl font-black text-white">Globe<span style={{ color: "#ef2b3a" }}>.</span><span style={{ color: "#ffc400" }}>Lax</span></div>
          <div className="anim-up text-sm font-bold text-white/70" style={{ animationDelay: "150ms" }}>کشورها · پایتخت‌ها · پرچم‌ها</div>
        </div>
      )}
    </ToastProvider>
  );
}
