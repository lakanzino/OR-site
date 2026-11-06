package com.hamyarj.clinic.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.hamyarj.clinic.ui.components.AppCard
import com.hamyarj.clinic.ui.components.AppDatePickerDialog
import com.hamyarj.clinic.ui.components.AutoSizeText
import com.hamyarj.clinic.ui.components.PickerField
import com.hamyarj.clinic.ui.components.StatCard
import com.hamyarj.clinic.ui.theme.AppTypography
import com.hamyarj.clinic.util.DateUtils
import com.hamyarj.clinic.util.formatMoney
import com.hamyarj.clinic.util.formatThousands
import com.hamyarj.clinic.viewmodel.ReportsViewModel

@Composable
fun ReportsScreen(vm: ReportsViewModel) {
    val start by vm.startMillis.collectAsState()
    val end by vm.endMillis.collectAsState()
    val report by vm.report.collectAsState()
    val settings by vm.settings.collectAsState()
    var pickStart by remember { mutableStateOf(false) }
    var pickEnd by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text("گزارشات", style = MaterialTheme.typography.titleLarge)

        // ---- Date range ----
        AppCard(Modifier.fillMaxWidth()) {
            Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                PickerField(
                    label = "از تاریخ",
                    valueText = DateUtils.format(start),
                    icon = Icons.Filled.CalendarMonth,
                    onClick = { pickStart = true },
                    modifier = Modifier.weight(1f)
                )
                PickerField(
                    label = "تا تاریخ",
                    valueText = DateUtils.format(end),
                    icon = Icons.Filled.CalendarMonth,
                    onClick = { pickEnd = true },
                    modifier = Modifier.weight(1f)
                )
            }
        }

        // ---- Summary ----
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            StatCard(
                label = "تعداد بیماران",
                value = report.patientCount.toLong().formatThousands(),
                modifier = Modifier.weight(1f)
            )
            StatCard(
                label = "مجموع درآمد",
                value = formatMoney(report.totalIncome, settings.currencyUnit),
                modifier = Modifier.weight(1.4f)
            )
        }

        // ---- Breakdown per basic insurance ----
        AppCard(Modifier.fillMaxWidth()) {
            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("تفکیک بر اساس بیمه پایه", style = MaterialTheme.typography.titleMedium)
                HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)

                if (report.breakdown.isEmpty()) {
                    Text(
                        "در این بازه بیماری ثبت نشده است",
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        style = MaterialTheme.typography.bodyMedium
                    )
                } else {
                    val max = report.breakdown.maxOf { it.count }.coerceAtLeast(1)
                    report.breakdown.forEach { row ->
                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(
                                    row.name,
                                    modifier = Modifier.weight(1f),
                                    style = MaterialTheme.typography.bodyMedium,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis
                                )
                                AutoSizeText(
                                    text = "${row.count.toLong().formatThousands()} نفر",
                                    style = AppTypography.numberStyle.copy(
                                        fontSize = MaterialTheme.typography.titleSmall.fontSize
                                    ),
                                    color = MaterialTheme.colorScheme.primary
                                )
                            }
                            // proportional bar
                            Box(
                                Modifier
                                    .fillMaxWidth()
                                    .height(6.dp)
                                    .clip(RoundedCornerShape(3.dp))
                                    .background(MaterialTheme.colorScheme.surfaceVariant)
                            ) {
                                Box(
                                    Modifier
                                        .fillMaxWidth(row.count / max.toFloat())
                                        .fillMaxHeight()
                                        .clip(RoundedCornerShape(3.dp))
                                        .background(MaterialTheme.colorScheme.primary)
                                )
                            }
                            Text(
                                "درآمد: " + formatMoney(row.income, settings.currencyUnit),
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                maxLines = 1
                            )
                        }
                    }
                }
            }
        }
    }

    if (pickStart) {
        AppDatePickerDialog(
            initialMillis = start,
            onDismiss = { pickStart = false },
            onConfirm = vm::setStart
        )
    }
    if (pickEnd) {
        AppDatePickerDialog(
            initialMillis = end,
            onDismiss = { pickEnd = false },
            onConfirm = vm::setEnd
        )
    }
}
