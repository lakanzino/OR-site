package com.lax1819.app.ui.components

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.KeyboardDoubleArrowLeft
import androidx.compose.material.icons.filled.KeyboardDoubleArrowRight
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.lax1819.app.R
import com.lax1819.app.ui.theme.Amber
import com.lax1819.app.ui.theme.Gold
import com.lax1819.app.ui.theme.SoftOrange
import com.lax1819.app.util.Fa
import com.lax1819.app.util.Jalali

private val FridayRed = Color(0xFFC0392B)

/**
 * Jalali month calendar (Saturday-first, RTL). Used on the home screen and
 * inside the date picker dialog.
 */
@Composable
fun JalaliCalendar(
    year: Int,
    month: Int,
    selectedKey: Int,
    counts: Map<Int, Int>,
    onMonthChange: (year: Int, month: Int) -> Unit,
    onDayClick: (Int) -> Unit,
    onToday: () -> Unit,
    modifier: Modifier = Modifier,
    flat: Boolean = false,
) {
    val todayKey = remember { Jalali.todayKey() }
    val body: @Composable () -> Unit = {
        Column(Modifier.padding(if (flat) 0.dp else 12.dp)) {
            // Header: year-, month-, title, month+, year+, today
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { onMonthChange(year - 1, month) }) {
                    Icon(Icons.Filled.KeyboardDoubleArrowRight, contentDescription = stringResource(R.string.prev_year))
                }
                IconButton(onClick = { val (y, m) = Jalali.addMonths(year, month, -1); onMonthChange(y, m) }) {
                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, contentDescription = stringResource(R.string.prev_month))
                }
                AnimatedContent(
                    targetState = year * 100 + month,
                    transitionSpec = { fadeIn(tween(220)) togetherWith fadeOut(tween(120)) },
                    modifier = Modifier.weight(1f),
                    label = "monthTitle",
                ) { ym ->
                    Text(
                        Jalali.monthNames[ym % 100 - 1] + " " + Fa.int(ym / 100),
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
                IconButton(onClick = { val (y, m) = Jalali.addMonths(year, month, 1); onMonthChange(y, m) }) {
                    Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, contentDescription = stringResource(R.string.next_month))
                }
                IconButton(onClick = { onMonthChange(year + 1, month) }) {
                    Icon(Icons.Filled.KeyboardDoubleArrowLeft, contentDescription = stringResource(R.string.next_year))
                }
            }
            Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                TextButton(onClick = onToday) { Text(stringResource(R.string.today), fontWeight = FontWeight.Bold) }
            }
            // Weekday names
            Row(Modifier.fillMaxWidth()) {
                Jalali.weekDayShort.forEachIndexed { i, n ->
                    Text(
                        n,
                        modifier = Modifier.weight(1f),
                        textAlign = TextAlign.Center,
                        style = MaterialTheme.typography.labelMedium,
                        color = if (i == 6) FridayRed else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            // Day grid
            val first = remember(year, month) { Jalali.weekDayIndex(year, month, 1) }
            val len = remember(year, month) { Jalali.monthLength(year, month) }
            val rows = (first + len + 6) / 7
            AnimatedContent(
                targetState = year * 100 + month,
                transitionSpec = { fadeIn(tween(220)) togetherWith fadeOut(tween(120)) },
                label = "monthGrid",
            ) { ym ->
                val y = ym / 100
                val m = ym % 100
                Column(Modifier.padding(top = 4.dp)) {
                    for (r in 0 until rows) {
                        Row(Modifier.fillMaxWidth()) {
                            for (c in 0 until 7) {
                                val day = r * 7 + c - first + 1
                                Box(Modifier.weight(1f)) {
                                    if (day in 1..len) {
                                        val key = Jalali.key(y, m, day)
                                        DayCell(
                                            day = day,
                                            selected = key == selectedKey,
                                            isToday = key == todayKey,
                                            count = counts[key] ?: 0,
                                            friday = c == 6,
                                            onClick = { onDayClick(key) },
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if (flat) Box(modifier) { body() } else GlassCard(modifier.fillMaxWidth(), elevation = Elev.Mid) { body() }
}

@Composable
private fun DayCell(
    day: Int,
    selected: Boolean,
    isToday: Boolean,
    count: Int,
    friday: Boolean,
    onClick: () -> Unit,
) {
    val shape = RoundedCornerShape(14.dp)
    val bg = if (selected) Modifier.background(Brush.linearGradient(listOf(Gold, Amber, SoftOrange)), shape) else Modifier
    val ring = if (isToday && !selected) Modifier.border(BorderStroke(1.5.dp, Amber), shape) else Modifier
    Box(
        Modifier
            .padding(2.dp)
            .aspectRatio(1f)
            .clip(shape)
            .then(bg)
            .then(ring)
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Text(
                Fa.int(day),
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = if (selected || isToday) FontWeight.Bold else FontWeight.Normal,
                color = when {
                    selected -> Color(0xFF3E2200)
                    friday -> FridayRed
                    else -> MaterialTheme.colorScheme.onSurface
                },
            )
            Box(
                Modifier
                    .size(if (count > 0) 6.dp else 0.dp)
                    .clip(CircleShape)
                    .background(if (selected) Color(0xFF3E2200) else SoftOrange)
            )
        }
    }
}

/** Jalali date picker dialog. [initialKey] 0 = today. */
@Composable
fun JalaliDatePickerDialog(
    initialKey: Int,
    onPick: (Int) -> Unit,
    onClear: (() -> Unit)?,
    onDismiss: () -> Unit,
) {
    val start = if (initialKey > 0) Jalali.parse(initialKey) else Jalali.today()
    var ym by rememberSaveable { mutableIntStateOf(start.y * 100 + start.m) }
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
        dismissButton = if (onClear != null) {
            { TextButton(onClick = onClear) { Text(stringResource(R.string.clear_value)) } }
        } else null,
        text = {
            JalaliCalendar(
                year = ym / 100,
                month = ym % 100,
                selectedKey = initialKey,
                counts = emptyMap(),
                onMonthChange = { y, m -> ym = y * 100 + m },
                onDayClick = onPick,
                onToday = { onPick(Jalali.todayKey()) },
                flat = true,
            )
        },
    )
}
