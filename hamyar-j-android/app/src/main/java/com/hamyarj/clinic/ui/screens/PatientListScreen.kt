package com.hamyarj.clinic.ui.screens

import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Clear
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Checkbox
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import com.hamyarj.clinic.data.Patient
import com.hamyarj.clinic.ui.components.AppCard
import com.hamyarj.clinic.ui.components.AutoSizeText
import com.hamyarj.clinic.ui.components.CompactTextField
import com.hamyarj.clinic.ui.components.StatCard
import com.hamyarj.clinic.ui.theme.AppTypography
import com.hamyarj.clinic.viewmodel.ListColumns
import com.hamyarj.clinic.viewmodel.PatientViewModel
import com.hamyarj.clinic.util.formatMoney
import com.hamyarj.clinic.util.formatThousands

@Composable
fun PatientListScreen(vm: PatientViewModel, onEdit: (Patient) -> Unit) {
    val patients by vm.patients.collectAsState()
    val query by vm.searchQuery.collectAsState()
    val columns by vm.columns.collectAsState()
    val settings by vm.settings.collectAsState()
    var toDelete by remember { mutableStateOf<Patient?>(null) }

    Column(
        Modifier
            .fillMaxSize()
            .padding(horizontal = 12.dp, vertical = 8.dp)
    ) {
        // ---- Search by name ----
        CompactTextField(
            value = query,
            onValueChange = vm::setSearch,
            placeholder = "جستجوی بیمار بر اساس نام…",
            modifier = Modifier.fillMaxWidth(),
            leading = { Icon(Icons.Filled.Search, null, tint = MaterialTheme.colorScheme.onSurfaceVariant) },
            trailing = {
                if (query.isNotEmpty()) {
                    Icon(
                        Icons.Filled.Clear, "پاک کردن",
                        modifier = Modifier
                            .size(20.dp)
                            .clickable { vm.setSearch("") },
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        )

        // ---- Column visibility checkboxes ----
        Row(
            Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState()),
            verticalAlignment = Alignment.CenterVertically
        ) {
            ColumnToggle("نام", columns.name) { c -> vm.setColumns { it.copy(name = c) } }
            ColumnToggle("بیمه", columns.insurance) { c -> vm.setColumns { it.copy(insurance = c) } }
            ColumnToggle("مکمل", columns.supplemental) { c -> vm.setColumns { it.copy(supplemental = c) } }
            ColumnToggle("دلیل", columns.reason) { c -> vm.setColumns { it.copy(reason = c) } }
            ColumnToggle("ساعت", columns.time) { c -> vm.setColumns { it.copy(time = c) } }
        }

        // ---- Summary bar (reflects the currently listed/filtered patients) ----
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            StatCard(
                label = "تعداد کل بیماران",
                value = patients.size.toLong().formatThousands(),
                modifier = Modifier.weight(1f)
            )
            StatCard(
                label = "مجموع درآمد",
                value = formatMoney(patients.sumOf { it.visitFee }, settings.currencyUnit),
                modifier = Modifier.weight(1.4f)
            )
        }
        Spacer(Modifier.height(8.dp))

        // ---- List ----
        if (patients.isEmpty()) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Text(
                    if (query.isEmpty()) "هنوز بیماری ثبت نشده است" else "بیماری با این نام یافت نشد",
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        } else {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(patients, key = { it.id }) { patient ->
                    PatientCard(
                        patient = patient,
                        columns = columns,
                        unit = settings.currencyUnit,
                        onEdit = { onEdit(patient) },
                        onDelete = { toDelete = patient }
                    )
                }
                item { Spacer(Modifier.height(8.dp)) }
            }
        }
    }

    toDelete?.let { p ->
        AlertDialog(
            onDismissRequest = { toDelete = null },
            title = { Text("حذف بیمار", style = MaterialTheme.typography.titleMedium) },
            text = { Text("آیا از حذف «${p.name}» مطمئن هستید؟") },
            confirmButton = {
                TextButton(onClick = { vm.delete(p); toDelete = null }) {
                    Text("حذف", color = MaterialTheme.colorScheme.error)
                }
            },
            dismissButton = { TextButton(onClick = { toDelete = null }) { Text("انصراف") } }
        )
    }
}

@Composable
private fun ColumnToggle(label: String, checked: Boolean, onChange: (Boolean) -> Unit) {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .clickable { onChange(!checked) }
            .padding(end = 10.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Checkbox(checked = checked, onCheckedChange = onChange)
        Text(label, style = MaterialTheme.typography.labelMedium, maxLines = 1)
    }
}

@Composable
private fun PatientCard(
    patient: Patient,
    columns: ListColumns,
    unit: String,
    onEdit: () -> Unit,
    onDelete: () -> Unit
) {
    AppCard(Modifier.fillMaxWidth()) {
        Row(
            Modifier.padding(start = 12.dp, top = 8.dp, bottom = 8.dp, end = 4.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                if (columns.name) {
                    Text(
                        patient.name,
                        style = MaterialTheme.typography.titleMedium,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
                if (columns.insurance) {
                    InfoLine("بیمه", patient.basicInsurance.ifEmpty { "—" })
                }
                if (columns.supplemental) {
                    InfoLine(
                        "مکمل",
                        if (patient.hasSupplement) patient.supplementalInsurance.ifEmpty { "دارد" } else "ندارد"
                    )
                }
                if (columns.reason && patient.reason.isNotBlank()) {
                    InfoLine("دلیل", patient.reason, maxLines = 2)
                }
                if (columns.time) {
                    InfoLine("ساعت", patient.visitTime.ifEmpty { "—" })
                }
                // The fee is always visible, big and bold
                AutoSizeText(
                    text = formatMoney(patient.visitFee, unit),
                    style = AppTypography.numberStyle.copy(
                        fontSize = MaterialTheme.typography.titleSmall.fontSize
                    ),
                    color = MaterialTheme.colorScheme.primary
                )
            }
            Column {
                IconButton(onClick = onEdit) {
                    Icon(Icons.Filled.Edit, "ویرایش", tint = MaterialTheme.colorScheme.primary)
                }
                IconButton(onClick = onDelete) {
                    Icon(Icons.Filled.Delete, "حذف", tint = MaterialTheme.colorScheme.error)
                }
            }
        }
    }
}

@Composable
private fun InfoLine(label: String, value: String, maxLines: Int = 1) {
    Row {
        Text(
            "$label: ",
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 1
        )
        Text(
            value,
            style = MaterialTheme.typography.bodySmall,
            maxLines = maxLines,
            overflow = TextOverflow.Ellipsis
        )
    }
}
