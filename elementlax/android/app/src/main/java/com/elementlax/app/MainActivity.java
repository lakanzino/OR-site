package com.elementlax.app;

import android.content.Intent;
import android.graphics.Color;
import android.os.Bundle;
import android.view.Window;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        applySystemBarTheme(true);
        if (this.bridge != null && this.bridge.getWebView() != null) {
            WebView webView = this.bridge.getWebView();
            webView.addJavascriptInterface(new NativeBridge(), "ElementLaxNative");
        }
    }

    @SuppressWarnings("deprecation")
    private void applySystemBarTheme(boolean isDark) {
        Window window = getWindow();
        if (window == null) {
            return;
        }
        int bg = Color.parseColor(isDark ? "#03070A" : "#F1F5FB");
        window.getDecorView().setBackgroundColor(bg);
        window.setStatusBarColor(bg);
        window.setNavigationBarColor(bg);
        WindowInsetsControllerCompat controller = WindowCompat.getInsetsController(window, window.getDecorView());
        if (controller != null) {
            controller.setAppearanceLightStatusBars(!isDark);
            controller.setAppearanceLightNavigationBars(!isDark);
        }
    }

    public final class NativeBridge {
        @JavascriptInterface
        public boolean shareText(String title, String text) {
            if (text == null || text.trim().isEmpty()) {
                return false;
            }
            runOnUiThread(() -> {
                Intent sendIntent = new Intent(Intent.ACTION_SEND);
                sendIntent.setType("text/plain");
                if (title != null && !title.trim().isEmpty()) {
                    sendIntent.putExtra(Intent.EXTRA_SUBJECT, title);
                }
                sendIntent.putExtra(Intent.EXTRA_TEXT, text);
                String chooserTitle = (title != null && !title.trim().isEmpty())
                        ? title
                        : getString(R.string.app_name);
                startActivity(Intent.createChooser(sendIntent, chooserTitle));
            });
            return true;
        }

        @JavascriptInterface
        public void setThemeMode(boolean isDark) {
            runOnUiThread(() -> applySystemBarTheme(isDark));
        }
    }
}
