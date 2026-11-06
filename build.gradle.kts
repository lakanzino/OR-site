// Top-level build file. Plugin versions are declared once here.
plugins {
    id("com.android.application") version "8.5.2" apply false
    id("org.jetbrains.kotlin.android") version "1.9.24" apply false
    // KSP version must match the Kotlin version (1.9.24-1.0.20)
    id("com.google.devtools.ksp") version "1.9.24-1.0.20" apply false
}
