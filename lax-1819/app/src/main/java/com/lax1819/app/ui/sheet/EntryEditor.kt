package com.lax1819.app.ui.sheet

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.StarBorder
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalIconButton
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.lax1819.app.R
import com.lax1819.app.data.AppSettings
import com.lax1819.app.data.ColKey
import com.lax1819.app.data.ColType
import com.lax1819.app.data.ColumnEntity
import com.lax1819.app.data.EntryEntity
import com.lax1819.app.data.QuickItemEntity
import com.lax1819.app.data.QuickKind
import com.lax1819.app.ui.components.DateField
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.components.GlassFab
import com.lax1819.app.ui.components.MoneyField
import com.lax1819.app.ui.components.NumberField
import com.lax1819.app.ui.components.QuantityStepper
import com.lax1819.app.ui.components.QuickPickerDialog
import com.lax1819.app.ui.components.TextInput
import com.lax1819.app.ui.components.TimeField
import com.lax1819.app.ui.theme.StatusCancel
import com.lax1819.app.ui.theme.StatusDone
import com.lax1819.app.ui.theme.StatusFollow
import com.lax1819.app.ui.theme.StatusPending
import com.lax1819.app.util.EntryFormat
import com.lax1819.app.util.Fa
import kotlinx.coroutines.flow.Flow

/** Bridge to the favorites / recents storage so composables stay ViewModel-agnostic. */
class QuickActions(
    val items: (String) -> Flow<List<QuickItemEntity>>,
    val add: (String, String) -> Unit,
    val toggle: (QuickItemEntity) -> Unit,
    val delete: (QuickItemEntity) -> Unit,
)

fun statusColor(status: Int): Color = when (status) {
    1 -> StatusPending
    2 -> StatusDone
    3 -> StatusCancel
    4 -> StatusFollow
    else -> Color.Transparent
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EntryEditorSheet(
    entry: EntryEntity,
    columns: List<ColumnEntity>,
    settings: AppSettings,
    quick: QuickActions,
    onChange: (EntryEntity) -> Unit,
    onSave: () -> Unit,
    onDismiss: () -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
        containerColor = MaterialTheme.colorScheme.background,
    ) {
        Column(
            Modifier
                .verticalScroll(rememberScrollState())
                .imePadding()
                .navigationBarsPadding()
                .padding(horizontal = 20.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text(
                stringResource(if (entry.id == 0L) R.string.new_row else R.string.edit_row),
                style = MaterialTheme.typography.titleLarge,
            )
            if (columns.isEmpty()) {
                Text(stringResource(R.string.no_visible_columns), color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            for (col in columns) {
                key(col.key) { ColumnField(col, entry, settings, quick, onChange) }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                GlassFab(stringResource(R.string.save), Icons.Filled.Check, onSave)
                TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) }
            }
            Spacer(Modifier.size(24.dp))
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ColumnField(
    col: ColumnEntity,
    e: EntryEntity,
    settings: AppSettings,
    quick: QuickActions,
    onChange: (EntryEntity) -> Unit,
) {
    when (col.key) {
        ColKey.FULL_NAME -> QuickTextField(col.title, e.fullName, QuickKind.NAME, quick) { onChange(e.copy(fullName = it)) }
        ColKey.PRODUCT -> QuickTextField(col.title, e.product, QuickKind.PRODUCT, quick) { onChange(e.copy(product = it)) }
        ColKey.SERVICE -> QuickTextField(col.title, e.service, QuickKind.SERVICE, quick) { onChange(e.copy(service = it)) }
        ColKey.PROD_DATE -> DateField(col.title, e.prodDate) { onChange(e.copy(prodDate = it)) }
        ColKey.EXP_DATE -> DateField(col.title, e.expDate) { onChange(e.copy(expDate = it)) }
        ColKey.ARRIVE -> TimeField(col.title, e.arrive) { onChange(e.copy(arrive = it)) }
        ColKey.END -> TimeField(col.title, e.end) { onChange(e.copy(end = it)) }
        ColKey.VISIT -> TimeField(col.title, e.visit) { onChange(e.copy(visit = it)) }
        ColKey.QUANTITY -> {
            var txt by remember { mutableStateOf(if (e.quantity > 0) Fa.qty(e.quantity, false) else "") }
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                NumberField(
                    label = col.title,
                    value = txt,
                    onValueChange = { txt = it; onChange(e.copy(quantity = it.toDoubleOrNull() ?: 0.0)) },
                    modifier = Modifier.fillMaxWidth(),
                    decimal = true,
                    suffix = settings.quantityUnit,
                )
                QuantityStepper(e.quantity, settings.quantityUnit, {
                    txt = if (it > 0) Fa.qty(it, false) else ""
                    onChange(e.copy(quantity = it))
                })
            }
        }
        ColKey.COST -> MoneyField(col.title, e.cost, settings.currencyLabel, { onChange(e.copy(cost = it)) })
        ColKey.DEPOSIT -> MoneyField(col.title, e.deposit, settings.currencyLabel, { onChange(e.copy(deposit = it)) })
        ColKey.INSTALLMENT -> GlassCard(Modifier.fillMaxWidth(), alpha = 0.7f) {
            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(col.title, style = MaterialTheme.typography.titleSmall)
                NumberField(
                    stringResource(R.string.inst_count),
                    if (e.instCount > 0) e.instCount.toString() else "",
                    { onChange(e.copy(instCount = it.toIntOrNull() ?: 0)) },
                    Modifier.fillMaxWidth(),
                )
                MoneyField(stringResource(R.string.inst_amount), e.instAmount, settings.currencyLabel, { onChange(e.copy(instAmount = it)) })
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(stringResource(R.string.inst_paid), modifier = Modifier.weight(1f))
                    FilledTonalIconButton(
                        onClick = { onChange(e.copy(instPaid = (e.instPaid - 1).coerceAtLeast(0))) },
                        modifier = Modifier.size(40.dp),
                    ) { Text("−", style = MaterialTheme.typography.titleMedium) }
                    Text(Fa.int(e.instPaid) + " / " + Fa.int(e.instCount), style = MaterialTheme.typography.titleMedium)
                    FilledTonalIconButton(
                        onClick = { onChange(e.copy(instPaid = (e.instPaid + 1).coerceAtMost(e.instCount))) },
                        modifier = Modifier.size(40.dp),
                    ) { Text("+", style = MaterialTheme.typography.titleMedium) }
                }
                Text(
                    stringResource(R.string.inst_remaining) + ": " + Fa.group(e.instRemaining) + " " + settings.currencyLabel,
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.onSurface,
                    fontWeight = FontWeight.Bold,
                )
            }
        }
        ColKey.NOTE -> TextInput(col.title, e.note, { onChange(e.copy(note = it)) }, singleLine = false)
        ColKey.STATUS -> Column {
            Text(col.title, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                EntryFormat.statusNames.forEachIndexed { i, name ->
                    FilterChip(
                        selected = e.status == i,
                        onClick = { onChange(e.copy(status = i)) },
                        label = { Text(name) },
                        leadingIcon = if (i > 0) {
                            { Box(Modifier.size(10.dp).clip(CircleShape).background(statusColor(i))) }
                        } else null,
                    )
                }
            }
        }
        else -> {
            // custom column
            val v = e.customValues()[col.key].orEmpty()
            if (col.type == ColType.NUMBER) {
                NumberField(col.title, v, { onChange(e.withCustom(col.key, it)) }, Modifier.fillMaxWidth(), decimal = true)
            } else {
                TextInput(col.title, v, { onChange(e.withCustom(col.key, it)) })
            }
        }
    }
}

@Composable
private fun QuickTextField(
    label: String,
    value: String,
    kind: String,
    quick: QuickActions,
    onValue: (String) -> Unit,
) {
    var open by remember { mutableStateOf(false) }
    TextInput(
        label = label,
        value = value,
        onValueChange = onValue,
        trailing = {
            IconButton(onClick = { open = true }) {
                Icon(Icons.Filled.StarBorder, contentDescription = stringResource(R.string.quick_pick))
            }
        },
    )
    if (open) {
        val flow = remember(kind) { quick.items(kind) }
        val items by flow.collectAsState(initial = emptyList())
        QuickPickerDialog(
            title = label,
            items = items,
            onPick = { onValue(it); open = false },
            onAdd = { quick.add(kind, it) },
            onToggleFavorite = quick.toggle,
            onDelete = quick.delete,
            onDismiss = { open = false },
        )
    }
}
