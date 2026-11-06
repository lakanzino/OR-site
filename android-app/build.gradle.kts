// Root build file: only declares plugin versions (applied in :app).
plugins {
    id("com.android.application") version "8.5.2" apply false
    id("org.jetbrains.kotlin.android") version "1.9.24" apply false
    // KSP is the annotation processor used by Room (replaces kapt)
    id("com.google.devtools.ksp") version "1.9.24-1.0.20" apply false
}
