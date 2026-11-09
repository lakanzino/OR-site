package com.lax1819.app.ui.sheet

import android.content.Intent
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.EditNote
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.SearchOff
import androidx.compose.material.icons.filled.ViewColumn
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.AssistChip
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.SnackbarResult
import androidx.compose.material3.SwipeToDismissBox
import androidx.compose.material3.SwipeToDismissBoxValue
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.rememberSwipeToDismissBoxState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.saveable.Saver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.lax1819.app.R
import com.lax1819.app.data.AppJson
import com.lax1819.app.data.ColKey
import com.lax1819.app.data.ColType
import com.lax1819.app.data.ColumnEntity
import com.lax1819.app.data.EntryEntity
import com.lax1819.app.data.QuickKind
import com.lax1819.app.ui.components.ConfirmDialog
import com.lax1819.app.ui.components.EmptyState
import com.lax1819.app.ui.components.GlassBar
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.components.GlassFab
import com.lax1819.app.ui.components.QuantityStepper
import com.lax1819.app.ui.components.QuickPickerDialog
import com.lax1819.app.ui.components.TextPromptDialog
import com.lax1819.app.ui.viewmodel.SheetViewModel
import com.lax1819.app.util.EntryFormat
import com.lax1819.app.util.Fa
import com.lax1819.app.util.Totals
import kotlinx.coroutines.launch

/** Keeps the entry being edited alive across process death (serialized as JSON). */
private val EntryDraftSaver = Saver<EntryEntity?, String>(
    save = { e -> if (e == null) "" else AppJson.encodeToString(EntryEntity.serializer(), e) },
    restore = { s -> if (s.isEmpty()) null else AppJson.decodeFromString(EntryEntity.serializer(), s) },
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SheetScreen(
    onBack: () -> Unit,
    onOpenColumns: () -> Unit,
    vm: SheetViewModel = hiltViewModel(),
) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    val snackbar = remember { SnackbarHostState() }

    val sheet by vm.sheet.collectAsStateWithLifecycle()
    val columns by vm.columns.collectAsStateWithLifecycle()
    val settings by vm.settings.collectAsStateWithLifecycle()
    val entries by vm.entries.collectAsStateWithLifecycle()
    val totals by vm.totals.collectAsStateWithLifecycle()
    val query by vm.query.collectAsStateWithLifecycle()
    val visible = remember(columns) { columns.filter { it.visible } }

    var editing by rememberSaveable(stateSaver = EntryDraftSaver) { mutableStateOf<EntryEntity?>(null) }
    var searching by rememberSaveable { mutableStateOf(false) }
    var menuOpen by remember { mutableStateOf(false) }
    var showRename by remember { mutableStateOf(false) }
    var showSaveTemplate by remember { mutableStateOf(false) }
    var showTemplates by remember { mutableStateOf(false) }
    var showDelete by remember { mutableStateOf(false) }
    var quickKind by rememberSaveable { mutableStateOf("") }

    val quick = remember(vm) {
        QuickActions(
            items = { vm.quick(it) },
            add = { k, t -> vm.addQuick(k, t) },
            toggle = { vm.toggleFavorite(it) },
            delete = { vm.deleteQuick(it) },
        )
    }

    fun toast(res: Int) { scope.launch { snackbar.showSnackbar(ctx.getString(res)) } }

    val title = sheet?.title.orEmpty().ifBlank { stringResource(R.string.sheet) }
    val deletedMsg = stringResource(R.string.row_deleted)
    val undoLabel = stringResource(R.string.undo)

    Scaffold(
        containerColor = Color.Transparent,
        snackbarHost = { SnackbarHost(snackbar) },
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.Transparent),
                title = {
                    Column {
                        Text(title, style = MaterialTheme.typography.titleMedium, maxLines = 1)
                        sheet?.let { Text(Fa.dateLong(it.dateKey), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, stringResource(R.string.back)) }
                },
                actions = {
                    IconButton(onClick = { searching = !searching; if (!searching) vm.setQuery("") }) {
                        Icon(if (searching) Icons.Filled.SearchOff else Icons.Filled.Search, stringResource(R.string.search))
                    }
                    IconButton(onClick = onOpenColumns) { Icon(Icons.Filled.ViewColumn, stringResource(R.string.columns)) }
                    Box {
                        IconButton(onClick = { menuOpen = true }) { Icon(Icons.Filled.MoreVert, stringResource(R.string.more)) }
                        DropdownMenu(expanded = menuOpen, onDismissRequest = { menuOpen = false }) {
                            DropdownMenuItem(text = { Text(stringResource(R.string.rename_sheet)) }, onClick = { menuOpen = false; showRename = true })
                            DropdownMenuItem(text = { Text(stringResource(R.string.copy_previous)) }, onClick = {
                                menuOpen = false
                                vm.copyPrevious { ok -> toast(if (ok) R.string.msg_copied else R.string.msg_no_previous) }
                            })
                            DropdownMenuItem(text = { Text(stringResource(R.string.save_template)) }, onClick = { menuOpen = false; showSaveTemplate = true })
                            DropdownMenuItem(text = { Text(stringResource(R.string.apply_template)) }, onClick = { menuOpen = false; showTemplates = true })
                            DropdownMenuItem(text = { Text(stringResource(R.string.share_summary)) }, onClick = {
                                menuOpen = false
                                val i = Intent(Intent.ACTION_SEND).apply {
                                    type = "text/plain"
                                    putExtra(Intent.EXTRA_TEXT, vm.shareText())
                                }
                                ctx.startActivity(Intent.createChooser(i, null))
                            })
                            DropdownMenuItem(
                                text = { Text(stringResource(R.string.delete_sheet), color = MaterialTheme.colorScheme.error) },
                                onClick = { menuOpen = false; showDelete = true },
                            )
                        }
                    }
                },
            )
        },
        floatingActionButton = {
            GlassFab(stringResource(R.string.new_row), Icons.Filled.Add, onClick = { editing = EntryEntity(sheetId = vm.sheetId) })
        },
        bottomBar = { SummaryBar(totals, settings.currencyLabel, settings.quantityUnit, visible.any { it.key == ColKey.QUANTITY }) },
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {
            if (searching) {
                OutlinedTextField(
                    value = query,
                    onValueChange = vm::setQuery,
                    modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 4.dp),
                    placeholder = { Text(stringResource(R.string.search_hint)) },
                    singleLine = true,
                    shape = RoundedCornerShape(20.dp),
                    leadingIcon = { Icon(Icons.Filled.Search, null) },
                )
            }
            // Quick add chips (only for visible columns)
            Row(
                Modifier.horizontalScroll(rememberScrollState()).padding(horizontal = 16.dp, vertical = 4.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                if (visible.any { it.key == ColKey.FULL_NAME }) QuickChip(R.string.quick_add_name) { quickKind = QuickKind.NAME }
                if (visible.any { it.key == ColKey.PRODUCT }) QuickChip(R.string.quick_add_product) { quickKind = QuickKind.PRODUCT }
                if (visible.any { it.key == ColKey.SERVICE }) QuickChip(R.string.quick_add_service) { quickKind = QuickKind.SERVICE }
            }
            if (entries.isEmpty()) {
                EmptyState(
                    icon = Icons.Filled.EditNote,
                    title = stringResource(if (query.isBlank()) R.string.empty_sheet_title else R.string.empty_search_title),
                    body = stringResource(if (query.isBlank()) R.string.empty_sheet_body else R.string.empty_search_body),
                )
            } else {
                LazyColumn(
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 110.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    items(entries, key = { it.id }, contentType = { "entry" }) { e ->
                        SwipeEntry(
                            entry = e,
                            visible = visible,
                            qtyUnit = settings.quantityUnit,
                            currency = settings.currencyLabel,
                            onClick = { editing = e },
                            onDelete = {
                                vm.delete(e)
                                scope.launch {
                                    val r = snackbar.showSnackbar(deletedMsg, actionLabel = undoLabel)
                                    if (r == SnackbarResult.ActionPerformed) vm.restore(e)
                                }
                            },
                            onDuplicate = { vm.duplicate(e) },
                            onQty = { vm.update(e.copy(quantity = it)) },
                            modifier = Modifier.animateItem(),
                        )
                    }
                }
            }
        }
    }

    // ---- dialogs ----
    editing?.let { e ->
        EntryEditorSheet(
            entry = e,
            columns = visible,
            settings = settings,
            quick = quick,
            onChange = { editing = it },
            onSave = { vm.save(e); editing = null },
            onDismiss = { editing = null },
        )
    }
    if (quickKind.isNotEmpty()) {
        val flow = remember(quickKind) { vm.quick(quickKind) }
        val items by flow.collectAsState(initial = emptyList())
        QuickPickerDialog(
            title = stringResource(
                when (quickKind) {
                    QuickKind.NAME -> R.string.quick_add_name
                    QuickKind.PRODUCT -> R.string.quick_add_product
                    else -> R.string.quick_add_service
                }
            ),
            items = items,
            onPick = { vm.addQuickEntry(quickKind, it); quickKind = "" },
            onAdd = { vm.addQuick(quickKind, it) },
            onToggleFavorite = vm::toggleFavorite,
            onDelete = vm::deleteQuick,
            onDismiss = { quickKind = "" },
        )
    }
    if (showRename) {
        TextPromptDialog(
            title = stringResource(R.string.rename_sheet),
            label = stringResource(R.string.sheet_title_hint),
            initial = sheet?.title.orEmpty(),
            onConfirm = { vm.rename(it); showRename = false },
            onDismiss = { showRename = false },
        )
    }
    if (showSaveTemplate) {
        TextPromptDialog(
            title = stringResource(R.string.save_template),
            label = stringResource(R.string.template_name),
            onConfirm = { if (it.isNotBlank()) { vm.saveTemplate(it); toast(R.string.msg_saved) }; showSaveTemplate = false },
            onDismiss = { showSaveTemplate = false },
        )
    }
    if (showTemplates) {
        val templates by vm.templates.collectAsStateWithLifecycle()
        AlertDialog(
            onDismissRequest = { showTemplates = false },
            title = { Text(stringResource(R.string.apply_template)) },
            text = {
                if (templates.isEmpty()) Text(stringResource(R.string.no_templates))
                else Column {
                    templates.forEach { t ->
                        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                            TextButton(onClick = { vm.applyTemplate(t); showTemplates = false }, modifier = Modifier.weight(1f)) {
                                Text(t.name, modifier = Modifier.fillMaxWidth())
                            }
                            IconButton(onClick = { vm.deleteTemplate(t) }) { Icon(Icons.Filled.Delete, stringResource(R.string.delete)) }
                        }
                    }
                }
            },
            confirmButton = { TextButton(onClick = { showTemplates = false }) { Text(stringResource(R.string.close)) } },
            shape = RoundedCornerShape(28.dp),
        )
    }
    if (showDelete) {
        ConfirmDialog(
            title = stringResource(R.string.delete_sheet),
            text = stringResource(R.string.delete_sheet_confirm),
            confirmText = stringResource(R.string.delete),
            onConfirm = { showDelete = false; vm.deleteSheet(onBack) },
            onDismiss = { showDelete = false },
        )
    }
}

@Composable
private fun QuickChip(label: Int, onClick: () -> Unit) {
    AssistChip(
        onClick = onClick,
        label = { Text(stringResource(label)) },
        leadingIcon = { Icon(Icons.Filled.Add, null) },
        shape = RoundedCornerShape(16.dp),
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SwipeEntry(
    entry: EntryEntity,
    visible: List<ColumnEntity>,
    qtyUnit: String,
    currency: String,
    onClick: () -> Unit,
    onDelete: () -> Unit,
    onDuplicate: () -> Unit,
    onQty: (Double) -> Unit,
    modifier: Modifier = Modifier,
) {
    val del by rememberUpdatedState(onDelete)
    val dup by rememberUpdatedState(onDuplicate)
    val state = rememberSwipeToDismissBoxState(
        confirmValueChange = { v ->
            when (v) {
                SwipeToDismissBoxValue.EndToStart -> { del(); true }
                SwipeToDismissBoxValue.StartToEnd -> { dup(); false } // snap back after duplicating
                else -> false
            }
        }
    )
    SwipeToDismissBox(
        state = state,
        modifier = modifier,
        backgroundContent = {
            val dir = state.dismissDirection
            val shape = RoundedCornerShape(22.dp)
            val color = when (dir) {
                SwipeToDismissBoxValue.EndToStart -> MaterialTheme.colorScheme.errorContainer
                SwipeToDismissBoxValue.StartToEnd -> MaterialTheme.colorScheme.primaryContainer
                else -> Color.Transparent
            }
            Box(
                Modifier.fillMaxSize().clip(shape).background(color).padding(horizontal = 24.dp),
                contentAlignment = if (dir == SwipeToDismissBoxValue.StartToEnd) Alignment.CenterStart else Alignment.CenterEnd,
            ) {
                when (dir) {
                    SwipeToDismissBoxValue.EndToStart -> Icon(Icons.Filled.Delete, stringResource(R.string.delete), tint = MaterialTheme.colorScheme.error)
                    SwipeToDismissBoxValue.StartToEnd -> Icon(Icons.Filled.ContentCopy, stringResource(R.string.duplicate))
                    else -> {}
                }
            }
        },
    ) {
        EntryCard(entry, visible, qtyUnit, currency, onClick, onQty)
    }
}

private data class DisplayRow(val title: String, val value: String, val bold: Boolean)

@Composable
private fun EntryCard(
    entry: EntryEntity,
    visible: List<ColumnEntity>,
    qtyUnit: String,
    currency: String,
    onClick: () -> Unit,
    onQty: (Double) -> Unit,
) {
    val rows = remember(entry, visible, qtyUnit, currency) {
        visible.mapNotNull { c ->
            if (c.key == ColKey.QUANTITY || c.key == ColKey.STATUS) null
            else {
                val v = EntryFormat.value(entry, c, qtyUnit, currency)
                if (v.isBlank()) null else DisplayRow(c.title, v, c.type == ColType.MONEY)
            }
        }
    }
    val qtyCol = remember(visible) { visible.firstOrNull { it.key == ColKey.QUANTITY } }
    val statusCol = remember(visible) { visible.firstOrNull { it.key == ColKey.STATUS } }

    GlassCard(Modifier.fillMaxWidth(), onClick = onClick) {
        Row(Modifier.height(IntrinsicSize.Min)) {
            val stripe = if (statusCol != null && entry.status > 0) statusColor(entry.status) else Color.Transparent
            Box(Modifier.width(6.dp).fillMaxHeight().background(stripe))
            Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                rows.forEach { r ->
                    Row(verticalAlignment = Alignment.Top) {
                        Text(
                            r.title,
                            modifier = Modifier.width(92.dp),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Text(
                            r.value,
                            modifier = Modifier.weight(1f),
                            style = MaterialTheme.typography.bodyLarge,
                            fontWeight = if (r.bold) FontWeight.Bold else FontWeight.Normal,
                        )
                    }
                }
                if (qtyCol != null) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            qtyCol.title,
                            modifier = Modifier.weight(1f),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        QuantityStepper(entry.quantity, qtyUnit, onQty)
                    }
                }
                if (statusCol != null && entry.status > 0) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.width(10.dp).height(10.dp).clip(CircleShape).background(statusColor(entry.status)))
                        Text(
                            "  " + EntryFormat.statusNames[entry.status],
                            style = MaterialTheme.typography.labelLarge,
                            fontWeight = FontWeight.Bold,
                        )
                    }
                }
                if (rows.isEmpty() && qtyCol == null && (statusCol == null || entry.status == 0)) {
                    Text(stringResource(R.string.empty_row), color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

/** Sticky bottom summary. Only relevant numbers are shown. */
@Composable
private fun SummaryBar(totals: Totals, currency: String, qtyUnit: String, showQty: Boolean) {
    GlassBar {
        Row(
            Modifier
                .navigationBarsPadding()
                .horizontalScroll(rememberScrollState())
                .padding(horizontal = 8.dp, vertical = 12.dp),
        ) {
            Stat(stringResource(R.string.sum_count), Fa.int(totals.count))
            if (totals.cost > 0) Stat(stringResource(R.string.sum_cost) + " ($currency)", Fa.group(totals.cost))
            if (totals.deposit > 0) Stat(stringResource(R.string.sum_deposit) + " ($currency)", Fa.group(totals.deposit))
            if (totals.instRemaining > 0) Stat(stringResource(R.string.sum_inst_remaining) + " ($currency)", Fa.group(totals.instRemaining))
            if (showQty && totals.quantity > 0) Stat(stringResource(R.string.sum_quantity) + " ($qtyUnit)", Fa.qty(totals.quantity))
        }
    }
}

@Composable
private fun Stat(label: String, value: String) {
    Column(Modifier.padding(horizontal = 14.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
    }
}
