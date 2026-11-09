package com.lax1819.app.ui.columns

import androidx.compose.animation.core.animateDpAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.DragHandle
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.LockOpen
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.lax1819.app.R
import com.lax1819.app.data.ColType
import com.lax1819.app.data.ColumnEntity
import com.lax1819.app.data.Modes
import com.lax1819.app.ui.components.ConfirmDialog
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.components.GlassFab
import com.lax1819.app.ui.components.TextInput
import com.lax1819.app.ui.components.TextPromptDialog
import com.lax1819.app.ui.theme.accentText
import com.lax1819.app.ui.viewmodel.ColumnsViewModel
import sh.calvin.reorderable.ReorderableItem
import sh.calvin.reorderable.rememberReorderableLazyListState

private const val HEADER_COUNT = 2 // items before the draggable rows

private fun typeLabel(type: String): Int = when (type) {
    ColType.TEXT -> R.string.type_text
    ColType.NUMBER -> R.string.type_number
    ColType.DATE -> R.string.type_date
    ColType.TIME -> R.string.type_time
    ColType.QUANTITY -> R.string.type_quantity
    ColType.MONEY -> R.string.type_money
    ColType.INSTALLMENT -> R.string.type_installment
    ColType.NOTE -> R.string.type_note
    else -> R.string.type_status
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
fun ColumnsScreen(onBack: () -> Unit, vm: ColumnsViewModel = hiltViewModel()) {
    val columns by vm.columns.collectAsStateWithLifecycle()
    val settings by vm.settings.collectAsStateWithLifecycle()

    // Local working copy so drag & drop is instant; persisted when the drag ends.
    var local by remember { mutableStateOf(columns) }
    LaunchedEffect(columns) { local = columns }

    var renaming by remember { mutableStateOf<ColumnEntity?>(null) }
    var deleting by remember { mutableStateOf<ColumnEntity?>(null) }
    var showAdd by rememberSaveable { mutableStateOf(false) }
    var showUnit by rememberSaveable { mutableStateOf(false) }

    val listState = rememberLazyListState()
    val reorderState = rememberReorderableLazyListState(listState) { from, to ->
        val f = from.index - HEADER_COUNT
        val t = to.index - HEADER_COUNT
        if (f in local.indices && t in local.indices) {
            local = local.toMutableList().apply { add(t, removeAt(f)) }
        }
    }

    Scaffold(
        containerColor = Color.Transparent,
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.Transparent),
                title = { Text(stringResource(R.string.column_manager)) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, stringResource(R.string.back)) }
                },
            )
        },
        floatingActionButton = {
            GlassFab(stringResource(R.string.add_column), Icons.Filled.Add, onClick = { showAdd = true })
        },
    ) { padding ->
        LazyColumn(
            Modifier.fillMaxSize().padding(padding),
            state = listState,
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 110.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item(key = "modes") {
                GlassCard(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(stringResource(R.string.quick_modes), style = MaterialTheme.typography.titleMedium)
                        Text(
                            stringResource(R.string.quick_modes_hint),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Modes.all.forEach { m ->
                                FilterChip(
                                    selected = settings.mode == m,
                                    onClick = { vm.applyMode(m) },
                                    label = { Text(Modes.title(m)) },
                                )
                            }
                        }
                    }
                }
            }
            item(key = "unit") {
                GlassCard(Modifier.fillMaxWidth()) {
                    Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(stringResource(R.string.quantity_unit), style = MaterialTheme.typography.titleMedium)
                            Text(
                                settings.quantityUnit,
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.accentText,
                                fontWeight = FontWeight.Bold,
                            )
                        }
                        TextButton(onClick = { showUnit = true }) { Text(stringResource(R.string.change)) }
                    }
                }
            }
            items(local, key = { it.key }, contentType = { "column" }) { col ->
                ReorderableItem(reorderState, key = col.key, enabled = !col.locked) { isDragging ->
                    val elevation by animateDpAsState(if (isDragging) 12.dp else 2.dp, label = "dragElev")
                    GlassCard(Modifier.fillMaxWidth(), elevation = elevation) {
                        Row(Modifier.padding(horizontal = 6.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                            IconButton(
                                onClick = {},
                                enabled = !col.locked,
                                modifier = Modifier.draggableHandle(enabled = !col.locked, onDragStopped = { vm.saveOrder(local) }),
                            ) {
                                Icon(Icons.Filled.DragHandle, stringResource(R.string.reorder))
                            }
                            Column(Modifier.weight(1f)) {
                                Text(col.title, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold, maxLines = 1)
                                Text(
                                    stringResource(typeLabel(col.type)) + if (col.custom) " • " + stringResource(R.string.custom) else "",
                                    style = MaterialTheme.typography.labelMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            IconButton(onClick = { renaming = col }, modifier = Modifier.size(40.dp)) {
                                Icon(Icons.Filled.Edit, stringResource(R.string.rename))
                            }
                            IconButton(onClick = { vm.save(col.copy(locked = !col.locked)) }, modifier = Modifier.size(40.dp)) {
                                Icon(
                                    if (col.locked) Icons.Filled.Lock else Icons.Filled.LockOpen,
                                    stringResource(if (col.locked) R.string.unlock else R.string.lock),
                                    tint = if (col.locked) MaterialTheme.colorScheme.accentText else MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            if (col.custom && !col.locked) {
                                IconButton(onClick = { deleting = col }, modifier = Modifier.size(40.dp)) {
                                    Icon(Icons.Filled.Delete, stringResource(R.string.delete), tint = MaterialTheme.colorScheme.error)
                                }
                            }
                            Switch(
                                checked = col.visible,
                                enabled = !col.locked,
                                onCheckedChange = { vm.save(col.copy(visible = it)) },
                                modifier = Modifier.padding(horizontal = 6.dp),
                            )
                        }
                    }
                }
            }
        }
    }

    renaming?.let { c ->
        TextPromptDialog(
            title = stringResource(R.string.rename_column),
            label = stringResource(R.string.column_name),
            initial = c.title,
            onConfirm = { if (it.isNotBlank()) vm.save(c.copy(title = it.trim())); renaming = null },
            onDismiss = { renaming = null },
        )
    }
    deleting?.let { c ->
        ConfirmDialog(
            title = stringResource(R.string.delete_column),
            text = stringResource(R.string.delete_column_confirm),
            confirmText = stringResource(R.string.delete),
            onConfirm = { vm.delete(c); deleting = null },
            onDismiss = { deleting = null },
        )
    }
    if (showUnit) {
        TextPromptDialog(
            title = stringResource(R.string.quantity_unit),
            label = stringResource(R.string.quantity_unit_hint),
            initial = settings.quantityUnit,
            onConfirm = { vm.setUnit(it.trim()); showUnit = false },
            onDismiss = { showUnit = false },
        )
    }
    if (showAdd) AddColumnDialog(onAdd = { n, t -> vm.add(n, t); showAdd = false }, onDismiss = { showAdd = false })
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun AddColumnDialog(onAdd: (String, String) -> Unit, onDismiss: () -> Unit) {
    var name by rememberSaveable { mutableStateOf("") }
    var type by rememberSaveable { mutableStateOf(ColType.TEXT) }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(R.string.add_column)) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                TextInput(stringResource(R.string.column_name), name, { name = it })
                Text(stringResource(R.string.column_type), style = MaterialTheme.typography.labelMedium)
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf(ColType.TEXT, ColType.NUMBER).forEach {
                        FilterChip(selected = type == it, onClick = { type = it }, label = { Text(stringResource(typeLabel(it))) })
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = { if (name.isNotBlank()) onAdd(name, type) }) { Text(stringResource(R.string.add)) }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
    )
}
