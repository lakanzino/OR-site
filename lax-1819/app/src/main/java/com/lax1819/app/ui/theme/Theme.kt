package com.lax1819.app.ui.theme

import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.ColorScheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.dynamicDarkColorScheme
import androidx.compose.material3.dynamicLightColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.unit.LayoutDirection

private val LightScheme = lightColorScheme(
    primary = Amber,
    onPrimary = Color(0xFF3E2200),
    primaryContainer = Color(0xFFFFE0B2),
    onPrimaryContainer = Color(0xFF3E2200),
    secondary = SoftOrange,
    onSecondary = Color(0xFF3B1100),
    secondaryContainer = Color(0xFFFFDBCF),
    onSecondaryContainer = Color(0xFF3B1100),
    tertiary = Gold,
    onTertiary = Color(0xFF3A2E00),
    tertiaryContainer = Color(0xFFFFF0B3),
    onTertiaryContainer = Color(0xFF3A2E00),
    background = Cream,
    onBackground = TextBrown,
    surface = CardLight,
    onSurface = TextBrown,
    surfaceVariant = Beige,
    onSurfaceVariant = TextBrownSoft,
    outline = OutlineLight,
    outlineVariant = Color(0xFFEBDCC3),
    error = Color(0xFFBA3B2B),
)

private val DarkScheme = darkColorScheme(
    primary = Amber,
    onPrimary = Color(0xFF3E2200),
    primaryContainer = Color(0xFF5C3A08),
    onPrimaryContainer = Color(0xFFFFE0B2),
    secondary = SoftOrange,
    onSecondary = Color(0xFF3B1100),
    secondaryContainer = Color(0xFF5A2A1A),
    onSecondaryContainer = Color(0xFFFFDBCF),
    tertiary = Gold,
    onTertiary = Color(0xFF3A2E00),
    tertiaryContainer = Color(0xFF584400),
    onTertiaryContainer = Color(0xFFFFF0B3),
    background = DarkBg,
    onBackground = TextCream,
    surface = CardDark,
    onSurface = TextCream,
    surfaceVariant = DarkBg2,
    onSurfaceVariant = TextCreamSoft,
    outline = OutlineDark,
    outlineVariant = Color(0xFF3A2C1E),
    error = Color(0xFFFF8A80),
)

/** True when the dark palette is active (used by glass components). */
val LocalIsDark = staticCompositionLocalOf { false }

/** Text accent color with good contrast on both themes. */
val ColorScheme.accentText: Color
    get() = if (LightScheme.background == background) DeepAmber else Amber

@Composable
fun Lax1819Theme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    dynamicColor: Boolean = false, // warm custom scheme is locked by default
    content: @Composable () -> Unit,
) {
    val context = LocalContext.current
    val scheme = when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S ->
            if (darkTheme) dynamicDarkColorScheme(context) else dynamicLightColorScheme(context)
        darkTheme -> DarkScheme
        else -> LightScheme
    }
    // Whole app is RTL (Persian)
    CompositionLocalProvider(
        LocalLayoutDirection provides LayoutDirection.Rtl,
        LocalIsDark provides darkTheme,
    ) {
        MaterialTheme(colorScheme = scheme, typography = AppTypography, content = content)
    }
}
