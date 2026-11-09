package com.lax1819.app.ui.viewmodel

import android.content.Context
import android.net.Uri
import androidx.annotation.StringRes
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.lax1819.app.R
import com.lax1819.app.data.AppSettings
import com.lax1819.app.data.BackupManager
import com.lax1819.app.data.ColKey
import com.lax1819.app.data.ColumnEntity
import com.lax1819.app.data.EntryEntity
import com.lax1819.app.data.QuickItemEntity
import com.lax1819.app.data.QuickKind
import com.lax1819.app.data.Repository
import com.lax1819.app.data.SheetEntity
import com.lax1819.app.data.SheetSummary
import com.lax1819.app.data.TemplateEntity
import com.lax1819.app.util.EntryFormat
import com.lax1819.app.util.Fa
import com.lax1819.app.util.Jalali
import com.lax1819.app.util.Totals
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.flowOn
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import javax.inject.Inject

private val WhileUi = SharingStarted.WhileSubscribed(5_000)

/** Activity-scoped: settings, bootstrap and app lock state. */
@HiltViewModel
class AppViewModel @Inject constructor(private val repo: Repository) : ViewModel() {
    val settings: StateFlow<AppSettings> = repo.settings.flow.stateIn(viewModelScope, SharingStarted.Eagerly, AppSettings())

    /** Unlocked for this process (process death => locked again). */
    var unlocked by mutableStateOf(false)
        private set

    init {
        viewModelScope.launch { repo.bootstrap() }
    }

    fun finishOnboarding() = viewModelScope.launch { repo.settings.setOnboardingDone() }

    fun tryPin(pin: String): Boolean {
        val ok = repo.settings.verifyPin(pin, settings.value)
        if (ok) unlocked = true
        return ok
    }

    fun unlock() { unlocked = true }
    fun lock() { unlocked = false }
}

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val repo: Repository,
    private val saved: SavedStateHandle,
) : ViewModel() {
    private val t = Jalali.today()

    /** year*100+month, survives process death */
    val ym: StateFlow<Int> = saved.getStateFlow("ym", t.y * 100 + t.m)
    val selected: StateFlow<Int> = saved.getStateFlow("selected", Jalali.key(t))

    @OptIn(ExperimentalCoroutinesApi::class)
    val counts: StateFlow<Map<Int, Int>> = ym
        .flatMapLatest { v -> repo.monthCounts(v * 100 + 1, v * 100 + 31) }
        .map { list -> list.associate { it.dateKey to it.cnt } }
        .stateIn(viewModelScope, WhileUi, emptyMap())

    @OptIn(ExperimentalCoroutinesApi::class)
    val sheets: StateFlow<List<SheetSummary>> = selected
        .flatMapLatest { repo.summaries(it) }
        .stateIn(viewModelScope, WhileUi, emptyList())

    val settings: StateFlow<AppSettings> = repo.settings.flow.stateIn(viewModelScope, WhileUi, AppSettings())

    fun setMonth(y: Int, m: Int) { saved["ym"] = y * 100 + m }
    fun select(key: Int) {
        saved["selected"] = key
        val p = Jalali.parse(key)
        saved["ym"] = p.y * 100 + p.m
    }

    fun goToday() = select(Jalali.todayKey())

    fun create(title: String, copyPrevious: Boolean, onCreated: (Long) -> Unit) {
        viewModelScope.launch {
            val id = repo.createSheet(selected.value, title, copyPrevious)
            onCreated(id)
        }
    }

    fun deleteSheet(id: Long) = viewModelScope.launch { repo.deleteSheet(id) }
    fun removeDemo() = viewModelScope.launch { repo.removeDemo() }
}

@HiltViewModel
class SheetViewModel @Inject constructor(
    private val repo: Repository,
    saved: SavedStateHandle,
) : ViewModel() {
    val sheetId: Long = saved.get<Long>("sheetId") ?: 0L

    val sheet: StateFlow<SheetEntity?> = repo.sheetById(sheetId).stateIn(viewModelScope, WhileUi, null)
    val columns: StateFlow<List<ColumnEntity>> = repo.columns.stateIn(viewModelScope, WhileUi, emptyList())
    val settings: StateFlow<AppSettings> = repo.settings.flow.stateIn(viewModelScope, WhileUi, AppSettings())
    val templates: StateFlow<List<TemplateEntity>> = repo.templateList.stateIn(viewModelScope, WhileUi, emptyList())

    private val queryState = MutableStateFlow("")
    val query: StateFlow<String> = queryState

    private val all: Flow<List<EntryEntity>> = repo.entriesOf(sheetId)

    val entries: StateFlow<List<EntryEntity>> = combine(all, queryState) { list, q ->
        val needle = q.trim().lowercase()
        if (needle.isEmpty()) list else {
            val digits = Fa.onlyDigits(needle)
            list.filter { e ->
                val hay = buildString {
                    append(e.fullName).append(' ').append(e.product).append(' ').append(e.service).append(' ')
                    append(e.note).append(' ').append(e.custom).append(' ').append(e.cost).append(' ').append(e.deposit)
                }.lowercase()
                hay.contains(needle) || (digits.isNotEmpty() && hay.contains(digits))
            }
        }
    }.flowOn(Dispatchers.Default).stateIn(viewModelScope, WhileUi, emptyList())

    val totals: StateFlow<Totals> = all.map { Totals.of(it) }.flowOn(Dispatchers.Default)
        .stateIn(viewModelScope, WhileUi, Totals())

    fun setQuery(q: String) { queryState.value = q }

    fun quick(kind: String): Flow<List<QuickItemEntity>> = repo.quickItems(kind)

    fun save(e: EntryEntity) = viewModelScope.launch { repo.saveEntry(e.copy(sheetId = sheetId)) }
    fun update(e: EntryEntity) = viewModelScope.launch { repo.updateEntry(e) }
    fun delete(e: EntryEntity) = viewModelScope.launch { repo.deleteEntry(e.id) }
    fun restore(e: EntryEntity) = viewModelScope.launch { repo.restoreEntry(e) }
    fun duplicate(e: EntryEntity) = viewModelScope.launch { repo.duplicateEntry(e) }

    /** Creates a new row with one field prefilled from a quick list (name / product / service). */
    fun addQuickEntry(kind: String, text: String) = viewModelScope.launch {
        val base = EntryEntity(sheetId = sheetId)
        val e = when (kind) {
            QuickKind.NAME -> base.copy(fullName = text)
            QuickKind.PRODUCT -> base.copy(product = text)
            else -> base.copy(service = text)
        }
        repo.saveEntry(e)
    }

    fun addQuick(kind: String, text: String) = viewModelScope.launch { repo.addQuick(kind, text, favorite = true) }
    fun toggleFavorite(item: QuickItemEntity) = viewModelScope.launch { repo.toggleFavorite(item) }
    fun deleteQuick(item: QuickItemEntity) = viewModelScope.launch { repo.deleteQuick(item) }

    fun rename(title: String) = viewModelScope.launch { sheet.value?.let { repo.renameSheet(it, title) } }
    fun deleteSheet(onDone: () -> Unit) = viewModelScope.launch { repo.deleteSheet(sheetId); onDone() }
    fun saveTemplate(name: String) = viewModelScope.launch { repo.saveTemplate(sheetId, name) }
    fun applyTemplate(t: TemplateEntity) = viewModelScope.launch { repo.applyTemplate(sheetId, t) }
    fun deleteTemplate(t: TemplateEntity) = viewModelScope.launch { repo.deleteTemplate(t.id) }
    fun copyPrevious(onResult: (Boolean) -> Unit) = viewModelScope.launch { onResult(repo.copyFromPrevious(sheetId)) }

    fun shareText(): String {
        val sh = sheet.value ?: return ""
        val s = settings.value
        return EntryFormat.shareText(sh.title, sh.dateKey, entries.value, columns.value, s.quantityUnit, s.currencyLabel)
    }
}

@HiltViewModel
class ColumnsViewModel @Inject constructor(private val repo: Repository) : ViewModel() {
    val columns: StateFlow<List<ColumnEntity>> = repo.columns.stateIn(viewModelScope, WhileUi, emptyList())
    val settings: StateFlow<AppSettings> = repo.settings.flow.stateIn(viewModelScope, WhileUi, AppSettings())

    fun save(c: ColumnEntity) = viewModelScope.launch { repo.saveColumn(c) }
    fun saveOrder(list: List<ColumnEntity>) = viewModelScope.launch { repo.saveColumnOrder(list) }
    fun add(title: String, type: String) = viewModelScope.launch { repo.addCustomColumn(title, type) }
    fun delete(c: ColumnEntity) = viewModelScope.launch { repo.deleteColumn(c.key) }
    fun applyMode(mode: String) = viewModelScope.launch { repo.applyMode(mode) }
    fun setUnit(unit: String) = viewModelScope.launch { repo.settings.setUnit(unit) }
}

@HiltViewModel
class SettingsViewModel @Inject constructor(
    @ApplicationContext private val context: Context,
    private val repo: Repository,
    private val backup: BackupManager,
) : ViewModel() {
    val settings: StateFlow<AppSettings> = repo.settings.flow.stateIn(viewModelScope, WhileUi, AppSettings())

    private val _messages = MutableSharedFlow<Int>(extraBufferCapacity = 4)
    val messages: SharedFlow<Int> = _messages

    fun setTheme(v: Int) = viewModelScope.launch { repo.settings.setTheme(v) }
    fun setUnit(v: String) = viewModelScope.launch { repo.settings.setUnit(v) }
    fun changeCurrency(c: String, convert: Boolean) = viewModelScope.launch { repo.changeCurrency(c, convert) }
    fun applyMode(m: String) = viewModelScope.launch { repo.applyMode(m); _messages.emit(R.string.msg_mode_applied) }
    fun setPin(pin: String?) = viewModelScope.launch { repo.settings.setPin(pin); _messages.emit(R.string.msg_saved) }
    fun setBiometric(v: Boolean) = viewModelScope.launch { repo.settings.setBiometric(v) }

    private fun run(@StringRes ok: Int, block: suspend () -> Unit) {
        viewModelScope.launch {
            try {
                block()
                _messages.emit(ok)
            } catch (e: Exception) {
                _messages.emit(R.string.msg_error)
            }
        }
    }

    private fun out(uri: Uri) = context.contentResolver.openOutputStream(uri, "wt") ?: error("no stream")
    private fun inp(uri: Uri) = context.contentResolver.openInputStream(uri) ?: error("no stream")

    fun exportJson(uri: Uri) = run(R.string.msg_backup_done) { backup.exportJson(out(uri)) }
    fun exportZip(uri: Uri) = run(R.string.msg_backup_done) { backup.exportZip(out(uri)) }
    fun exportCsv(uri: Uri) = run(R.string.msg_csv_done) { backup.exportCsv(out(uri)) }
    fun restore(uri: Uri) = run(R.string.msg_restore_done) { backup.restore(inp(uri)) }
    fun clearAll() = run(R.string.msg_cleared) { repo.clearAllData() }
}
