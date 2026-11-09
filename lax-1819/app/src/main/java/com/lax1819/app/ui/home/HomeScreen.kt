package com.lax1819.app.ui.home

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.EditNote
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Checkbox
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.lax1819.app.R
import com.lax1819.app.data.SheetSummary
import com.lax1819.app.ui.components.ConfirmDialog
import com.lax1819.app.ui.components.EmptyState
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.components.GlassFab
import com.lax1819.app.ui.components.IconBadge
import com.lax1819.app.ui.components.JalaliCalendar
import com.lax1819.app.ui.components.MainBottomBar
import com.lax1819.app.ui.components.TextInput
import com.lax1819.app.ui.nav.Routes
import com.lax1819.app.ui.theme.accentText
import com.lax1819.app.ui.viewmodel.HomeViewModel
import com.lax1819.app.util.Fa

@Composable
fun HomeScreen(
    onOpenSheet: (Long) -> Unit,
    onNavigate: (String) -> Unit,
    vm: HomeViewModel = hiltViewModel(),
) {
    val ym by vm.ym.collectAsStateWithLifecycle()
    val selected by vm.selected.collectAsStateWithLifecycle()
    val counts by vm.counts.collectAsStateWithLifecycle()
    val sheets by vm.sheets.collectAsStateWithLifecycle()
    val settings by vm.settings.collectAsStateWithLifecycle()

    var showNew by rememberSaveable { mutableStateOf(false) }
    var toDelete by remember { mutableStateOf<SheetSummary?>(null) }

    Scaffold(
        containerColor = Color.Transparent,
        bottomBar = { MainBottomBar(Routes.HOME, onNavigate) },
        floatingActionButton = {
            GlassFab(stringResource(R.string.new_sheet), Icons.Filled.Add, onClick = { showNew = true })
        },
    ) { padding ->
        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(
                start = 16.dp, end = 16.dp,
                top = padding.calculateTopPadding() + 12.dp,
                bottom = padding.calculateBottomPadding() + 96.dp,
            ),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item(key = "title", contentType = "title") {
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(horizontal = 4.dp)) {
                    Image(painterResource(R.drawable.app_logo), contentDescription = null, modifier = Modifier.size(48.dp))
                    Column(Modifier.padding(start = 12.dp)) {
                        Text(stringResource(R.string.app_name), style = MaterialTheme.typography.titleLarge)
                        Text(
                            stringResource(R.string.app_tagline),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
            item(key = "calendar", contentType = "calendar") {
                JalaliCalendar(
                    year = ym / 100,
                    month = ym % 100,
                    selectedKey = selected,
                    counts = counts,
                    onMonthChange = vm::setMonth,
                    onDayClick = vm::select,
                    onToday = vm::goToday,
                )
            }
            if (settings.demoActive) {
                item(key = "demo", contentType = "demo") {
                    GlassCard(Modifier.fillMaxWidth(), alpha = 0.8f) {
                        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                            Text(stringResource(R.string.demo_banner), Modifier.weight(1f), style = MaterialTheme.typography.bodyMedium)
                            TextButton(onClick = vm::removeDemo) { Text(stringResource(R.string.demo_remove)) }
                        }
                    }
                }
            }
            item(key = "dayHeader", contentType = "header") {
                Text(
                    stringResource(R.string.sheets_of) + " " + Fa.dateLong(selected),
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.accentText,
                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 4.dp),
                )
            }
            if (sheets.isEmpty()) {
                item(key = "empty", contentType = "empty") {
                    EmptyState(
                        icon = Icons.Filled.EventAvailable,
                        title = stringResource(R.string.empty_day_title),
                        body = stringResource(R.string.empty_day_body),
                    )
                }
            } else {
                items(sheets, key = { it.id }, contentType = { "sheet" }) { s ->
                    SheetRow(s, settings.currencyLabel, onClick = { onOpenSheet(s.id) }, onDelete = { toDelete = s }, modifier = Modifier.animateItem())
                }
            }
        }
    }

    if (showNew) {
        NewSheetDialog(
            onCreate = { title, copy ->
                showNew = false
                vm.create(title, copy, onOpenSheet)
            },
            onDismiss = { showNew = false },
        )
    }
    toDelete?.let { s ->
        ConfirmDialog(
            title = stringResource(R.string.delete_sheet),
            text = stringResource(R.string.delete_sheet_confirm),
            confirmText = stringResource(R.string.delete),
            onConfirm = { vm.deleteSheet(s.id); toDelete = null },
            onDismiss = { toDelete = null },
        )
    }
}

@Composable
private fun SheetRow(
    s: SheetSummary,
    currency: String,
    onClick: () -> Unit,
    onDelete: () -> Unit,
    modifier: Modifier = Modifier,
) {
    GlassCard(modifier.fillMaxWidth(), onClick = onClick) {
        Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            IconBadge(Icons.Filled.EditNote)
            Column(Modifier.weight(1f).padding(horizontal = 12.dp)) {
                Text(
                    s.title.ifBlank { stringResource(R.string.sheet) },
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    maxLines = 1,
                )
                val sub = Fa.int(s.entryCount) + " " + stringResource(R.string.rows_unit) +
                    if (s.totalCost > 0) " • " + Fa.group(s.totalCost) + " " + currency else ""
                Text(sub, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            IconButton(onClick = onDelete) {
                Icon(Icons.Filled.Delete, stringResource(R.string.delete), tint = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun NewSheetDialog(onCreate: (String, Boolean) -> Unit, onDismiss: () -> Unit) {
    var title by rememberSaveable { mutableStateOf("") }
    var copy by rememberSaveable { mutableStateOf(false) }
    AlertDialog(
        onDismissRequest = onDismiss,
        shape = RoundedCornerShape(28.dp),
        title = { Text(stringResource(R.string.new_sheet)) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                TextInput(stringResource(R.string.sheet_title_hint), title, { title = it })
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(checked = copy, onCheckedChange = { copy = it })
                    Text(stringResource(R.string.copy_previous_option), Modifier.width(240.dp), style = MaterialTheme.typography.bodyMedium)
                }
            }
        },
        confirmButton = { TextButton(onClick = { onCreate(title, copy) }) { Text(stringResource(R.string.create)) } },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
    )
}
