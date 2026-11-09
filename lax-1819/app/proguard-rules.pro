# Lax-1819 R8 rules
# kotlinx.serialization (backup JSON)
-keepattributes *Annotation*, InnerClasses
-dontnote kotlinx.serialization.AnnotationsKt
-keepclassmembers class kotlinx.serialization.json.** { *** Companion; }
-keepclasseswithmembers class kotlinx.serialization.json.** { kotlinx.serialization.KSerializer serializer(...); }
-keep,includedescriptorclasses class com.lax1819.app.**$$serializer { *; }
-keepclassmembers class com.lax1819.app.** { *** Companion; }
-keepclasseswithmembers class com.lax1819.app.** { kotlinx.serialization.KSerializer serializer(...); }

# Keep line numbers for readable crash reports
-keepattributes SourceFile,LineNumberTable
-renamesourcefileattribute SourceFile
