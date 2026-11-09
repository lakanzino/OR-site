package com.lax1819.app.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.MenuBook
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.ripple
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.lax1819.app.R
import com.lax1819.app.ui.nav.Routes
import com.lax1819.app.ui.theme.Amber
import com.lax1819.app.ui.theme.Gold

private data class Tab(val route: String, val label: Int, val icon: ImageVector)

private val tabs = listOf(
    Tab(Routes.HOME, R.string.nav_calendar, Icons.Filled.CalendarMonth),
    Tab(Routes.GUIDE, R.string.nav_guide, Icons.Filled.MenuBook),
    Tab(Routes.SETTINGS, R.string.nav_settings, Icons.Filled.Settings),
)

/** Elevated glass bottom navigation for the three main destinations. */
@Composable
fun MainBottomBar(current: String, onNavigate: (String) -> Unit) {
    GlassBar {
        androidx.compose.foundation.layout.Row(
            Modifier.navigationBarsPadding().padding(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceEvenly,
        ) {
            tabs.forEach { t ->
                val selected = t.route == current
                val src = remember { MutableInteractionSource() }
                Column(
                    Modifier
                        .weight(1f)
                        .pressScale(src)
                        .clip(RoundedCornerShape(18.dp))
                        .then(
                            if (selected) Modifier.background(Brush.linearGradient(listOf(Gold.copy(alpha = 0.9f), Amber.copy(alpha = 0.9f))))
                            else Modifier
                        )
                        .clickable(interactionSource = src, indication = ripple()) { if (!selected) onNavigate(t.route) }
                        .padding(vertical = 8.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Icon(
                        t.icon, null,
                        modifier = Modifier.size(24.dp),
                        tint = if (selected) Color(0xFF3E2200) else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Text(
                        stringResource(t.label),
                        style = MaterialTheme.typography.labelMedium,
                        fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal,
                        color = if (selected) Color(0xFF3E2200) else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
    }
}
