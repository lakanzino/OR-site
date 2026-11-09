# ─────────────────────────────────────────────────────────────
# Globe.Lax — R8 / ProGuard rules
# ─────────────────────────────────────────────────────────────

# Keep the Capacitor bridge entry points (reflection from JS → Java).
-keep public class com.getcapacitor.** { *; }
-keep @com.getcapacitor.annotation.CapacitorPlugin class * { *; }
-keep class com.getcapacitor.plugin.** { *; }
-keep class * extends com.getcapacitor.Plugin { *; }

# Keep the app's own activity & bridge (instantiated by the framework / JS).
-keep class com.globelax.app.MainActivity { *; }
-keep class com.globelax.app.MainActivity$NativeBridge { *; }

# Cordova compatibility layer used by Capacitor's plugin loader.
-keep class org.apache.cordova.** { *; }

# WebView JS interfaces must keep their annotated members.
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# AndroidX / AppCompat referenced from XML.
-keep public class * extends android.app.Activity
-keep public class * extends android.app.Application
-keep public class * extends android.app.Service
-keep public class * extends android.content.BroadcastReceiver
-keep public class * extends android.content.ContentProvider
-keep public class * extends android.view.View {
    public <init>(android.content.Context);
    public <init>(android.content.Context, android.util.AttributeSet);
    public <init>(android.content.Context, android.util.AttributeSet, int);
}

# Strip noisy debug logs from the release build.
-assumenosideeffects class android.util.Log {
    public static *** d(...);
    public static *** v(...);
    public static *** i(...);
}

# Keep line numbers so Play Console crash reports stay readable.
-keepattributes SourceFile,LineNumberTable
-renamesourcefileattribute SourceFile
