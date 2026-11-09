package com.lax1819.app.ui.settings

import android.content.Intent
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.biometric.BiometricManager
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.BugReport
import androidx.compose.material.icons.filled.DeleteForever
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.TableChart
import androidx.compose.material.icons.filled.ViewColumn
import androidx.compose.material.icons.filled.Backup
import androidx.compose.material.icons.filled.Restore
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.lax1819.app.AppInfo
import com.lax1819.app.BuildConfig
import com.lax1819.app.R
import com.lax1819.app.data.Currency
import com.lax1819.app.data.ThemeMode
import com.lax1819.app.ui.components.ConfirmDialog
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.components.MainBottomBar
import com.lax1819.app.ui.nav.Routes
import com.lax1819.app.ui.theme.accentText
import com.lax1819.app.ui.viewmodel.SettingsViewModel
import com.lax1819.app.util.Fa
import com.lax1819.app.util.Jalali

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun SettingsScreen(
    onNavigate: (String) -> Unit,
    onOpenColumns: () -> Unit,
    onOpenAbout: () -> Unit,
    vm: SettingsViewModel = hiltViewModel(),
) {
    val ctx = LocalContext.current
    val settings by vm.settings.collectAsStateWithLifecycle()
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(vm) { vm.messages.collect { snackbar.showSnackbar(ctx.getString(it)) } }

    var pendingCurrency by remember { mutableStateOf<String?>(null) }
    var showPin by rememberSaveable { mutableStateOf(false) }
    var showRestoreConfirm by rememberSaveable { mutableStateOf(false) }
    var showClear by rememberSaveable { mutableStateOf(false) }

    val stamp = remember {
        val t = Jalali.today()
        "${t.y}${Fa.two(t.m)}${Fa.two(t.d)}"
    }
    val jsonSaver = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("application/json")) { it?.let(vm::exportJson) }
    val zipSaver = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("application/zip")) { it?.let(vm::exportZip) }
    val csvSaver = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("text/csv")) { it?.let(vm::exportCsv) }
    val opener = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { it?.let(vm::restore) }

    val biometricAvailable = remember {
        BiometricManager.from(ctx).canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK) == BiometricManager.BIOMETRIC_SUCCESS
    }

    fun open(url: String) {
        try { ctx.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)) } catch (_: Exception) {}
    }

    Scaffold(
        containerColor = Color.Transparent,
        snackbarHost = { SnackbarHost(snackbar) },
        bottomBar = { MainBottomBar(Routes.SETTINGS, onNavigate) },
    ) { padding ->
        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(
                start = 16.dp, end = 16.dp,
                top = padding.calculateTopPadding() + 16.dp,
                bottom = padding.calculateBottomPadding() + 24.dp,
            ),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            item {
                Text(stringResource(R.string.nav_settings), style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(horizontal = 4.dp))
            }

            item {
                Section(stringResource(R.string.appearance)) {
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf(
                            ThemeMode.SYSTEM to R.string.theme_system,
                            ThemeMode.LIGHT to R.string.theme_light,
                            ThemeMode.DARK to R.string.theme_dark,
                        ).forEach { (m, label) ->
                            FilterChip(selected = settings.theme == m, onClick = { vm.setTheme(m) }, label = { Text(stringResource(label)) })
                        }
                    }
                }
            }

            item {
                Section(stringResource(R.string.currency)) {
                    Text(stringResource(R.string.currency_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf(Currency.TOMAN, Currency.RIAL).forEach { c ->
                            FilterChip(
                                selected = settings.currency == c,
                                onClick = { if (settings.currency != c) pendingCurrency = c },
                                label = { Text(Currency.label(c)) },
                            )
                        }
                    }
                }
            }

            item {
                Section(stringResource(R.string.columns_and_modes)) {
                    Text(stringResource(R.string.columns_and_modes_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    ActionRow(Icons.Filled.ViewColumn, stringResource(R.string.column_manager), onClick = onOpenColumns)
                }
            }

            item {
                Section(stringResource(R.string.app_lock)) {
                    Text(
                        stringResource(if (settings.hasPin) R.string.lock_enabled else R.string.lock_disabled),
                        style = MaterialTheme.typography.bodyMedium,
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(onClick = { showPin = true }) {
                            Text(stringResource(if (settings.hasPin) R.string.change_pin else R.string.set_pin))
                        }
                        if (settings.hasPin) {
                            TextButton(onClick = { vm.setPin(null) }) { Text(stringResource(R.string.remove_pin), color = MaterialTheme.colorScheme.error) }
                        }
                    }
                    if (settings.hasPin && biometricAvailable) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(stringResource(R.string.use_biometric), Modifier.weight(1f))
                            Switch(checked = settings.biometric, onCheckedChange = vm::setBiometric)
                        }
                    }
                }
            }

            item {
                Section(stringResource(R.string.backup_restore)) {
                    Text(stringResource(R.string.backup_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    ActionRow(Icons.Filled.Backup, stringResource(R.string.backup_json)) { jsonSaver.launch("lax1819-backup-$stamp.json") }
                    ActionRow(Icons.Filled.Backup, stringResource(R.string.backup_zip)) { zipSaver.launch("lax1819-backup-$stamp.zip") }
                    ActionRow(Icons.Filled.Restore, stringResource(R.string.restore)) { showRestoreConfirm = true }
                    ActionRow(Icons.Filled.TableChart, stringResource(R.string.export_csv)) { csvSaver.launch("lax1819-export-$stamp.csv") }
                }
            }

            item {
                Section(stringResource(R.string.support)) {
                    ActionRow(Icons.Filled.Email, stringResource(R.string.contact_email)) { open("mailto:${AppInfo.SUPPORT_EMAIL}") }
                    ActionRow(Icons.Filled.Send, stringResource(R.string.contact_telegram)) { open(AppInfo.TELEGRAM_URL) }
                    ActionRow(Icons.Filled.Star, stringResource(R.string.rate_app)) { open("market://details?id=${ctx.packageName.removeSuffix(".debug")}") }
                    ActionRow(Icons.Filled.BugReport, stringResource(R.string.report_issue)) { open(AppInfo.GITHUB_URL + "/issues") }
                }
            }

            item {
                Section(stringResource(R.string.about_and_license)) {
                    ActionRow(Icons.Filled.Info, stringResource(R.string.about), onClick = onOpenAbout)
                    Text(
                        stringResource(R.string.license_short),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.accentText,
                    )
                    Text(
                        stringResource(R.string.version_label) + " " + Fa.digits(BuildConfig.VERSION_NAME.removeSuffix("-debug")) +
                            " • " + stringResource(R.string.designed_by),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }

            item {
                Section(stringResource(R.string.danger_zone)) {
                    OutlinedButton(onClick = { showClear = true }) {
                        Icon(Icons.Filled.DeleteForever, null, tint = MaterialTheme.colorScheme.error)
                        Text("  " + stringResource(R.string.clear_all_data), color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }

    // ---- dialogs ----
    pendingCurrency?.let { target ->
        AlertDialog(
            onDismissRequest = { pendingCurrency = null },
            title = { Text(stringResource(R.string.currency_change_title)) },
            text = {
                Text(
                    stringResource(
                        if (target == Currency.RIAL) R.string.currency_convert_to_rial else R.string.currency_convert_to_toman
                    )
                )
            },
            confirmButton = {
                TextButton(onClick = { vm.changeCurrency(target, true); pendingCurrency = null }) { Text(stringResource(R.string.convert_values)) }
            },
            dismissButton = {
                Row {
                    TextButton(onClick = { vm.changeCurrency(target, false); pendingCurrency = null }) { Text(stringResource(R.string.label_only)) }
                    TextButton(onClick = { pendingCurrency = null }) { Text(stringResource(R.string.cancel)) }
                }
            },
        )
    }
    if (showPin) {
        PinSetupDialog(onConfirm = { vm.setPin(it); showPin = false }, onDismiss = { showPin = false })
    }
    if (showRestoreConfirm) {
        ConfirmDialog(
            title = stringResource(R.string.restore),
            text = stringResource(R.string.restore_confirm),
            confirmText = stringResource(R.string.restore_pick_file),
            onConfirm = { showRestoreConfirm = false; opener.launch(arrayOf("application/json", "application/zip", "application/octet-stream", "*/*")) },
            onDismiss = { showRestoreConfirm = false },
        )
    }
    if (showClear) {
        ClearDataDialog(
            onBackup = { jsonSaver.launch("lax1819-backup-$stamp.json") },
            onConfirm = { showClear = false; vm.clearAll() },
            onDismiss = { showClear = false },
        )
    }
}

@Composable
private fun Section(title: String, content: @Composable () -> Unit) {
    GlassCard(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text(title, style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.accentText)
            content()
        }
    }
}

@Composable
private fun ActionRow(icon: ImageVector, label: String, onClick: () -> Unit) {
    TextButton(onClick = onClick, modifier = Modifier.fillMaxWidth()) {
        Icon(icon, null)
        Text(label, Modifier.weight(1f).padding(start = 12.dp), style = MaterialTheme.typography.bodyLarge)
    }
}

@Composable
private fun PinSetupDialog(onConfirm: (String) -> Unit, onDismiss: () -> Unit) {
    var a by rememberSaveable { mutableStateOf("") }
    var b by rememberSaveable { mutableStateOf("") }
    val valid = a.length in 4..8 && a == b
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(R.string.set_pin)) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(stringResource(R.string.pin_rules), style = MaterialTheme.typography.bodySmall)
                OutlinedTextField(
                    value = a, onValueChange = { a = Fa.onlyDigits(it).take(8) },
                    label = { Text(stringResource(R.string.pin)) }, singleLine = true,
                    visualTransformation = PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                )
                OutlinedTextField(
                    value = b, onValueChange = { b = Fa.onlyDigits(it).take(8) },
                    label = { Text(stringResource(R.string.pin_repeat)) }, singleLine = true,
                    visualTransformation = PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                )
            }
        },
        confirmButton = { TextButton(onClick = { onConfirm(a) }, enabled = valid) { Text(stringResource(R.string.save)) } },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
    )
}

/** Strong confirmation: backup reminder + the user must type the confirmation word. */
@Composable
private fun ClearDataDialog(onBackup: () -> Unit, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    var typed by rememberSaveable { mutableStateOf("") }
    val word = stringResource(R.string.clear_word)
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(stringResource(R.string.clear_all_data), color = MaterialTheme.colorScheme.error) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(stringResource(R.string.clear_warning))
                OutlinedButton(onClick = onBackup) { Text(stringResource(R.string.backup_first)) }
                Text(stringResource(R.string.clear_type_hint, word), style = MaterialTheme.typography.bodySmall)
                OutlinedTextField(value = typed, onValueChange = { typed = it }, singleLine = true)
            }
        },
        confirmButton = {
            TextButton(onClick = onConfirm, enabled = typed.trim() == word) {
                Text(stringResource(R.string.delete_everything), color = MaterialTheme.colorScheme.error)
            }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text(stringResource(R.string.cancel)) } },
    )
}
