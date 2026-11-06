package com.hamyarj.clinic.ui.screens

import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Backup
import androidx.compose.material.icons.filled.Restore
import androidx.compose.material.icons.filled.Save
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.hamyarj.clinic.data.AppSettings
import com.hamyarj.clinic.ui.components.AppButton
import com.hamyarj.clinic.ui.components.AppCard
import com.hamyarj.clinic.ui.components.CompactTextField
import com.hamyarj.clinic.ui.components.MoneyTextField
import com.hamyarj.clinic.util.BackupManager
import com.hamyarj.clinic.util.CsvExporter
import com.hamyarj.clinic.util.DateUtils
import com.hamyarj.clinic.util.toMoneyLong
import com.hamyarj.clinic.viewmodel.SettingsViewModel
import kotlinx.coroutines.launch

@Composable
fun SettingsScreen(vm: SettingsViewModel) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val saved by vm.settings.collectAsState()

    // Local editable copies; re-initialised whenever the stored settings change
    var doctorName by remember(saved) { mutableStateOf(saved.doctorName) }
    var specialty by remember(saved) { mutableStateOf(saved.specialty) }
    var fee by remember(saved) { mutableStateOf(if (saved.defaultFee > 0) saved.defaultFee.toString() else "") }
    var currency by remember(saved) { mutableStateOf(saved.currencyUnit) }
    var confirmRestore by remember { mutableStateOf(false) }

    fun toast(msg: String) = Toast.makeText(context, msg, Toast.LENGTH_LONG).show()

    // System file picker: choose where to save the backup
    val backupLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.CreateDocument("application/octet-stream")
    ) { uri ->
        if (uri != null) scope.launch {
            BackupManager.exportDatabase(context, uri)
                .onSuccess { toast("پشتیبان با موفقیت ذخیره شد") }
                .onFailure { toast("خطا در پشتیبان‌گیری: ${it.message}") }
        }
    }

    // System file picker: choose a backup to restore
    val restoreLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.OpenDocument()
    ) { uri ->
        if (uri != null) scope.launch {
            BackupManager.importDatabase(context, uri)
                .onSuccess {
                    toast("بازیابی انجام شد. برنامه دوباره اجرا می‌شود…")
                    BackupManager.restartApp(context)
                }
                .onFailure { toast("فایل پشتیبان معتبر نیست: ${it.message}") }
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text("تنظیمات", style = MaterialTheme.typography.titleLarge)

        // ---- Doctor / clinic info ----
        AppCard(Modifier.fillMaxWidth()) {
            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("اطلاعات پزشک", style = MaterialTheme.typography.titleMedium)
                CompactTextField(
                    value = doctorName, onValueChange = { doctorName = it },
                    label = "نام پزشک", modifier = Modifier.fillMaxWidth()
                )
                CompactTextField(
                    value = specialty, onValueChange = { specialty = it },
                    label = "تخصص پزشک", modifier = Modifier.fillMaxWidth()
                )
                MoneyTextField(
                    value = fee, onValueChange = { fee = it },
                    label = "مبلغ پیش‌فرض ویزیت", modifier = Modifier.fillMaxWidth()
                )
                CompactTextField(
                    value = currency, onValueChange = { currency = it },
                    label = "واحد پول (مثلاً تومان، ریال، درهم)",
                    modifier = Modifier.fillMaxWidth()
                )
                AppButton(
                    text = "ذخیره تنظیمات",
                    icon = Icons.Filled.Save,
                    modifier = Modifier.fillMaxWidth(),
                    onClick = {
                        vm.save(
                            AppSettings(
                                doctorName = doctorName.trim(),
                                specialty = specialty.trim(),
                                defaultFee = fee.toMoneyLong(),
                                currencyUnit = currency.trim().ifEmpty { "تومان" }
                            )
                        )
                        toast("تنظیمات ذخیره شد")
                    }
                )
            }
        }

        // ---- Export & backup ----
        AppCard(Modifier.fillMaxWidth()) {
            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("خروجی و پشتیبان‌گیری", style = MaterialTheme.typography.titleMedium)
                AppButton(
                    text = "خروجی CSV و اشتراک‌گذاری",
                    icon = Icons.Filled.Share,
                    modifier = Modifier.fillMaxWidth(),
                    onClick = {
                        scope.launch {
                            runCatching { CsvExporter.share(context, vm.createCsvUri(context)) }
                                .onFailure { toast("خطا در ساخت فایل: ${it.message}") }
                        }
                    }
                )
                AppButton(
                    text = "پشتیبان‌گیری از دیتابیس",
                    icon = Icons.Filled.Backup,
                    outlined = true,
                    modifier = Modifier.fillMaxWidth(),
                    onClick = { backupLauncher.launch("hamyar_backup_${DateUtils.fileStamp()}.db") }
                )
                AppButton(
                    text = "بازیابی دیتابیس",
                    icon = Icons.Filled.Restore,
                    outlined = true,
                    modifier = Modifier.fillMaxWidth(),
                    onClick = { confirmRestore = true }
                )
            }
        }
    }

    if (confirmRestore) {
        AlertDialog(
            onDismissRequest = { confirmRestore = false },
            title = { Text("بازیابی دیتابیس", style = MaterialTheme.typography.titleMedium) },
            text = { Text("با بازیابی، تمام اطلاعات فعلی با فایل پشتیبان جایگزین می‌شود. ادامه می‌دهید؟") },
            confirmButton = {
                TextButton(onClick = {
                    confirmRestore = false
                    restoreLauncher.launch(arrayOf("*/*"))
                }) { Text("انتخاب فایل") }
            },
            dismissButton = { TextButton(onClick = { confirmRestore = false }) { Text("انصراف") } }
        )
    }
}
