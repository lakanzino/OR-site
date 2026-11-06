package com.hamyarj.clinic.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable

private val PastelColorScheme = lightColorScheme(
    primary = SoftBlue,
    onPrimary = AppSurface,
    primaryContainer = SoftBlueContainer,
    onPrimaryContainer = SoftBlueDark,
    secondary = SoftGrey,
    onSecondary = AppSurface,
    secondaryContainer = SoftGreyContainer,
    onSecondaryContainer = TextPrimary,
    background = AppBackground,
    onBackground = TextPrimary,
    surface = AppSurface,
    onSurface = TextPrimary,
    surfaceVariant = AppSurfaceVariant,
    onSurfaceVariant = TextSecondary,
    outline = BorderColor,
    outlineVariant = BorderLight,
    error = SoftRed,
    errorContainer = SoftRedContainer
)

@Composable
fun HamyarTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = PastelColorScheme,
        typography = AppTypography.typography,
        content = content
    )
}
