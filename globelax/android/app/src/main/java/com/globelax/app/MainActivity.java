package com.globelax.app;

import android.content.Intent;
import android.graphics.Color;
import android.os.Bundle;
import android.view.Window;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;

import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.getcapacitor.BridgeActivity;

/**
 * Globe.Lax host activity.
 *
 * <p>Deliberately minimal — it mirrors the shell that is known to launch
 * reliably on a wide range of devices. Everything visual lives in the web
 * layer, so the Java side only does three things: paint the window navy so
 * there is no white flash, expose a tiny share/version bridge, and keep the
 * system bars in sync with the in-app theme.
 */
public class MainActivity extends BridgeActivity {

    /** Brand navy — must match src/index.css (--navy). */
    private static final String NAVY = "#040C24";
    private static final String CREAM = "#F7F5EE";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        applySystemBarTheme(true);

        if (this.bridge != null && this.bridge.getWebView() != null) {
            WebView webView = this.bridge.getWebView();
            webView.addJavascriptInterface(new NativeBridge(), "GlobeNative");
            webView.setBackgroundColor(Color.parseColor(NAVY));
        }
    }

    @SuppressWarnings("deprecation")
    private void applySystemBarTheme(boolean isDark) {
        Window window = getWindow();
        if (window == null) {
            return;
        }
        int bg = Color.parseColor(isDark ? NAVY : CREAM);
        window.getDecorView().setBackgroundColor(bg);
        window.setStatusBarColor(bg);
        window.setNavigationBarColor(bg);

        WindowInsetsControllerCompat controller =
                WindowCompat.getInsetsController(window, window.getDecorView());
        if (controller != null) {
            controller.setAppearanceLightStatusBars(!isDark);
            controller.setAppearanceLightNavigationBars(!isDark);
        }
    }

    /** Small, explicit native surface exposed to the offline web layer. */
    public final class NativeBridge {

        /** Opens the system share sheet with the given text. */
        @JavascriptInterface
        public boolean share(String text, String title) {
            if (text == null || text.trim().isEmpty()) {
                return false;
            }
            runOnUiThread(() -> {
                Intent send = new Intent(Intent.ACTION_SEND);
                send.setType("text/plain");
                if (title != null && !title.trim().isEmpty()) {
                    send.putExtra(Intent.EXTRA_SUBJECT, title);
                }
                send.putExtra(Intent.EXTRA_TEXT, text);
                String chooser = (title != null && !title.trim().isEmpty())
                        ? title
                        : getString(R.string.app_name);
                startActivity(Intent.createChooser(send, chooser));
            });
            return true;
        }

        /** Keeps the system bars in sync with the in-app dark/light theme. */
        @JavascriptInterface
        public void setTheme(final boolean dark) {
            runOnUiThread(() -> applySystemBarTheme(dark));
        }

        /** Reports the app version, used on the About screen. */
        @JavascriptInterface
        public String appVersion() {
            try {
                return getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
            } catch (Exception e) {
                return "1.0.1";
            }
        }

        /** True on any native shell, so the web layer can hide install prompts. */
        @JavascriptInterface
        public boolean isNative() {
            return true;
        }
    }
}
