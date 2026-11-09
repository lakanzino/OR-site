package com.lax1819.app.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Event
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.StarBorder
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.lax1819.app.R
import com.lax1819.app.data.QuickItemEntity
import com.lax1819.app.ui.theme.Amber
import com.lax1819.app.util.Fa

@Composable
fun TextPromptDialog(
    title: String,
    label: String,
    initial: String = "",
    confirmText: String = stringResource(R.string.save),
    onConfirm: (String) -> Unit,
    onDismiss: () -> Unit,
) {
    var text by rememberSaveable { mutableStateOf(initial) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        text = { TextInput(label = label, value = text, onValueChange = { text = it }) },
        confirmButton = { TextButton(onClick = { onConfirm(text) }) { Text(confirmText) } },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
        shape = RoundedCornerShape(28.dp),
    )
}

@Composable
fun ConfirmDialog(
    title: String,
    text: String,
    confirmText: String,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    dismissText: String = stringResource(R.string.cancel),
    extraAction: (@Composable () -> Unit)? = null,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        text = {
            Column {
                Text(text, style = MaterialTheme.typography.bodyMedium)
                extraAction?.invoke()
            }
        },
        confirmButton = { TextButton(onClick = onConfirm) { Text(confirmText, color = MaterialTheme.colorScheme.error) } },
        dismissButton = { TextButton(onClick = onDismiss) { Text(dismissText) } },
        shape = RoundedCornerShape(28.dp),
    )
}

/** Tappable read-only field used for date / time pickers. */
@Composable
fun PickerField(
    label: String,
    valueText: String,
    icon: ImageVector,
    onClick: () -> Unit,
    onClear: (() -> Unit)?,
    modifier: Modifier = Modifier,
) {
    Surface(
        onClick = onClick,
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        color = MaterialTheme.colorScheme.surface.copy(alpha = 0.6f),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
    ) {
        Row(Modifier.padding(horizontal = 16.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(label, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(
                    valueText.ifEmpty { "—" },
                    style = MaterialTheme.typography.bodyLarge,
                    color = if (valueText.isEmpty()) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurface,
                )
            }
            if (onClear != null && valueText.isNotEmpty()) {
                IconButton(onClick = onClear) { Icon(Icons.Filled.Close, contentDescription = stringResource(R.string.clear_value)) }
            }
            Icon(icon, contentDescription = null, tint = Amber)
        }
    }
}

@Composable
fun DateField(label: String, key: Int, onPick: (Int) -> Unit) {
    var open by rememberSaveable { mutableStateOf(false) }
    PickerField(
        label = label,
        valueText = Fa.date(key),
        icon = Icons.Filled.Event,
        onClick = { open = true },
        onClear = { onPick(0) },
    )
    if (open) {
        JalaliDatePickerDialog(
            initialKey = key,
            onPick = { onPick(it); open = false },
            onClear = if (key > 0) ({ onPick(0); open = false }) else null,
            onDismiss = { open = false },
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TimeField(label: String, value: String, onChange: (String) -> Unit) {
    var open by rememberSaveable { mutableStateOf(false) }
    PickerField(
        label = label,
        valueText = Fa.time(value),
        icon = Icons.Filled.Schedule,
        onClick = { open = true },
        onClear = { onChange("") },
    )
    if (open) {
        val parts = value.split(":")
        val state = rememberTimePickerState(
            initialHour = parts.getOrNull(0)?.toIntOrNull() ?: 9,
            initialMinute = parts.getOrNull(1)?.toIntOrNull() ?: 0,
            is24Hour = true,
        )
        AlertDialog(
            onDismissRequest = { open = false },
            confirmButton = {
                TextButton(onClick = { onChange(Fa.two(state.hour) + ":" + Fa.two(state.minute)); open = false }) {
                    Text(stringResource(R.string.confirm))
                }
            },
            dismissButton = { TextButton(onClick = { open = false }) { Text(stringResource(R.string.cancel)) } },
            text = { TimePicker(state = state) },
        )
    }
}

/** Favorites / recents list with add, star and delete. Used for name, product and service. */
@Composable
fun QuickPickerDialog(
    title: String,
    items: List<QuickItemEntity>,
    onPick: (String) -> Unit,
    onAdd: (String) -> Unit,
    onToggleFavorite: (QuickItemEntity) -> Unit,
    onDelete: (QuickItemEntity) -> Unit,
    onDismiss: () -> Unit,
) {
    var newText by rememberSaveable { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        shape = RoundedCornerShape(28.dp),
        text = {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    OutlinedTextField(
                        value = newText,
                        onValueChange = { newText = it },
                        modifier = Modifier.weight(1f),
                        label = { Text(stringResource(R.string.quick_new)) },
                        singleLine = true,
                        shape = RoundedCornerShape(16.dp),
                    )
                    TextButton(onClick = {
                        if (newText.isNotBlank()) { onAdd(newText.trim()); onPick(newText.trim()); newText = "" }
                    }) { Text(stringResource(R.string.add)) }
                }
                if (items.isEmpty()) {
                    Text(
                        stringResource(R.string.quick_empty),
                        modifier = Modifier.padding(vertical = 16.dp),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                } else {
                    LazyColumn(Modifier.heightIn(max = 320.dp).padding(top = 8.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                        items(items, key = { it.id }, contentType = { "quick" }) { item ->
                            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                                IconButton(onClick = { onToggleFavorite(item) }, modifier = Modifier.size(40.dp)) {
                                    Icon(
                                        if (item.favorite) Icons.Filled.Star else Icons.Filled.StarBorder,
                                        contentDescription = null,
                                        tint = Amber,
                                    )
                                }
                                TextButton(onClick = { onPick(item.text) }, modifier = Modifier.weight(1f)) {
                                    Text(item.text, modifier = Modifier.fillMaxWidth(), style = MaterialTheme.typography.bodyLarge)
                                }
                                IconButton(onClick = { onDelete(item) }, modifier = Modifier.size(40.dp)) {
                                    Icon(Icons.Filled.Delete, contentDescription = stringResource(R.string.delete), tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                }
                            }
                        }
                    }
                }
            }
        },
        confirmButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.close)) } },
    )
}
