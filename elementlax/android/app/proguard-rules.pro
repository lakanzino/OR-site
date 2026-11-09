# Capacitor & WebView JS bridge rules
-keepclassmembers class com.elementlax.app.MainActivity$NativeBridge {
    @android.webkit.JavascriptInterface <methods>;
}

-keep @com.getcapacitor.annotation.CapacitorPlugin public class * {
    @com.getcapacitor.annotation.PermissionCallback <methods>;
    @com.getcapacitor.annotation.ActivityCallback <methods>;
    @com.getcapacitor.annotation.Permission <methods>;
    @com.getcapacitor.PluginMethod public <methods>;
}

-keep public class * extends com.getcapacitor.Plugin { *; }

-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}
