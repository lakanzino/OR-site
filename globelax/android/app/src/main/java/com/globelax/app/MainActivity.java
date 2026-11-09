package com.globelax.app;

import android.content.Intent;
import android.graphics.Color;
import android.os.Build;
import android.os.Bundle;
import android.view.Window;
import android.view.WindowInsetsController;
import android.view.WindowManager;
import android.webkit.JavascriptInterface;

import androidx.core.view.WindowCompat;

import com.getcapacitor.BridgeActivity;

/**
 * Globe.Lax host activity.
 *
 * <p>Everything the app needs is local, so this class only wires up the few
 * genuinely native behaviours: a share sheet, status-bar tinting, and a
 * JS bridge ({@code GlobeNative}) the web layer can call.
 */
public class MainActivity extends BridgeActivity {

    /** Theme colours shared with the web layer (must match src/index.css tokens). */
    private static final String NAVY = "#040C24";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        Window window = getWindow();
        window.addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS);
        window.setStatusBarColor(Color.parseColor(NAVY));
        window.setNavigationBarColor(Color.parseColor(NAVY));
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            // Draw behind the notch, matching the CSS safe-area insets.
            window.getAttributes().layoutInDisplayCutoutMode =
                    WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES;
        }
        WindowCompat.setDecorFitsSystemWindows(window, true);

        if (getBridge() != null && getBridge().getWebView() != null) {
            getBridge().getWebView().addJavascriptInterface(new NativeBridge(), "GlobeNative");
            getBridge().getWebView().setBackgroundColor(Color.parseColor(NAVY));
        }
    }

    /** Small, explicit native surface exposed to the offline web layer. */
    public class NativeBridge {

        /** Opens the system share sheet with the given text. */
        @JavascriptInterface
        public void share(String text, String title) {
            runOnUiThread(() -> {
                Intent send = new Intent(Intent.ACTION_SEND);
                send.setType("text/plain");
                send.putExtra(Intent.EXTRA_TEXT, text);
                send.putExtra(Intent.EXTRA_SUBJECT, title);
                startActivity(Intent.createChooser(send, title));
            });
        }

        /** Keeps the system bars in sync with the in-app dark/light theme. */
        @JavascriptInterface
        public void setTheme(final boolean dark) {
            runOnUiThread(() -> {
                Window window = getWindow();
                String barColor = dark ? NAVY : "#F7F5EE";
                window.setStatusBarColor(Color.parseColor(barColor));
                window.setNavigationBarColor(Color.parseColor(barColor));

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                    WindowInsetsController controller = window.getInsetsController();
                    if (controller != null) {
                        int mask = WindowInsetsController.APPEARANCE_LIGHT_STATUS_BARS
                                | WindowInsetsController.APPEARANCE_LIGHT_NAVIGATION_BARS;
                        controller.setSystemBarsAppearance(dark ? 0 : mask, mask);
                    }
                }
            });
        }

        /** Reports the app version, used on the About screen. */
        @JavascriptInterface
        public String appVersion() {
            try {
                return getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
            } catch (Exception e) {
                return "1.0.0";
            }
        }

        /** True on any native shell, so the web layer can hide install prompts. */
        @JavascriptInterface
        public boolean isNative() {
            return true;
        }
    }
}
