package com.lax1819.app.ui.theme

import androidx.compose.material3.Typography
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import com.lax1819.app.R

/** Vazirmatn is bundled in res/font (SIL OFL) so it works fully offline. */
val Vazir = FontFamily(
    Font(R.font.vazirmatn_regular, FontWeight.Normal),
    Font(R.font.vazirmatn_medium, FontWeight.Medium),
    Font(R.font.vazirmatn_bold, FontWeight.Bold),
    Font(R.font.vazirmatn_bold, FontWeight.SemiBold),
)

private fun TextStyle.v() = copy(fontFamily = Vazir)

private val base = Typography()

val AppTypography = Typography(
    displayLarge = base.displayLarge.v(),
    displayMedium = base.displayMedium.v(),
    displaySmall = base.displaySmall.v(),
    headlineLarge = base.headlineLarge.v(),
    headlineMedium = base.headlineMedium.v().copy(fontWeight = FontWeight.Bold),
    headlineSmall = base.headlineSmall.v().copy(fontWeight = FontWeight.Bold),
    titleLarge = base.titleLarge.v().copy(fontWeight = FontWeight.Bold, fontSize = 22.sp),
    titleMedium = base.titleMedium.v().copy(fontWeight = FontWeight.Medium, fontSize = 17.sp),
    titleSmall = base.titleSmall.v().copy(fontWeight = FontWeight.Medium),
    bodyLarge = base.bodyLarge.v().copy(fontSize = 17.sp, lineHeight = 28.sp),
    bodyMedium = base.bodyMedium.v().copy(fontSize = 15.sp, lineHeight = 25.sp),
    bodySmall = base.bodySmall.v().copy(fontSize = 13.sp, lineHeight = 21.sp),
    labelLarge = base.labelLarge.v().copy(fontSize = 15.sp),
    labelMedium = base.labelMedium.v().copy(fontSize = 13.sp),
    labelSmall = base.labelSmall.v().copy(fontSize = 12.sp),
)
