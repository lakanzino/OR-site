package com.lax1819.app.data

import android.content.Context
import androidx.room.withTransaction
import com.lax1819.app.util.Fa
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import java.io.File
import java.io.InputStream
import java.io.OutputStream
import java.util.zip.ZipEntry
import java.util.zip.ZipInputStream
import java.util.zip.ZipOutputStream
import javax.inject.Inject
import javax.inject.Singleton

@Serializable
data class BackupData(
    val app: String = "Lax-1819",
    val version: Int = 1,
    val exportedAt: Long = System.currentTimeMillis(),
    val currency: String = Currency.TOMAN,
    val quantityUnit: String = "عدد",
    val sheets: List<SheetEntity> = emptyList(),
    val entries: List<EntryEntity> = emptyList(),
    val columns: List<ColumnEntity> = emptyList(),
    val quickItems: List<QuickItemEntity> = emptyList(),
    val templates: List<TemplateEntity> = emptyList(),
)

/** Full JSON / ZIP backup, restore and CSV export (via Storage Access Framework streams). */
@Singleton
class BackupManager @Inject constructor(
    @ApplicationContext private val context: Context,
    private val db: AppDatabase,
    private val repo: Repository,
    private val settings: SettingsStore,
) {
    private suspend fun snapshot(): BackupData {
        val s = settings.current()
        return BackupData(
            currency = s.currency,
            quantityUnit = s.quantityUnit,
            sheets = db.sheetDao().all(),
            entries = db.entryDao().all(),
            columns = db.columnDao().all(),
            quickItems = db.quickDao().all(),
            templates = db.templateDao().all(),
        )
    }

    private suspend fun json(): String =
        AppJson.encodeToString(BackupData.serializer(), snapshot())

    suspend fun exportJson(out: OutputStream) = withContext(Dispatchers.IO) {
        val text = json()
        out.use { it.write(text.toByteArray(Charsets.UTF_8)) }
    }

    /** ZIP containing backup.json (+ a raw copy of the SQLite database file). */
    suspend fun exportZip(out: OutputStream) = withContext(Dispatchers.IO) {
        val text = json()
        // Flush WAL into the main db file so the copy is consistent
        runCatching { db.openHelper.writableDatabase.query("PRAGMA wal_checkpoint(FULL)").close() }
        ZipOutputStream(out.buffered()).use { zip ->
            zip.putNextEntry(ZipEntry("backup.json"))
            zip.write(text.toByteArray(Charsets.UTF_8))
            zip.closeEntry()
            val file: File = context.getDatabasePath(AppDatabase.NAME)
            if (file.exists()) {
                zip.putNextEntry(ZipEntry(AppDatabase.NAME))
                file.inputStream().use { it.copyTo(zip) }
                zip.closeEntry()
            }
        }
    }

    /** Restores from a .json or .zip backup. Returns the number of restored sheets. */
    suspend fun restore(input: InputStream): Int = withContext(Dispatchers.IO) {
        val bytes = input.use { it.readBytes() }
        val text = if (bytes.size > 2 && bytes[0] == 'P'.code.toByte() && bytes[1] == 'K'.code.toByte()) {
            var found: String? = null
            ZipInputStream(bytes.inputStream()).use { zin ->
                var e = zin.nextEntry
                while (e != null) {
                    if (e.name == "backup.json") found = zin.readBytes().toString(Charsets.UTF_8)
                    e = zin.nextEntry
                }
            }
            found ?: throw IllegalArgumentException("backup.json not found")
        } else bytes.toString(Charsets.UTF_8)

        val data = AppJson.decodeFromString(BackupData.serializer(), text)
        require(data.app == "Lax-1819") { "Not a Lax-1819 backup" }
        db.withTransaction {
            db.sheetDao().deleteAll()
            db.entryDao().deleteAll()
            db.columnDao().deleteAll()
            db.quickDao().deleteAll()
            db.templateDao().deleteAll()
            db.sheetDao().insertAll(data.sheets)
            db.entryDao().insertAll(data.entries)
            db.columnDao().upsertAll(data.columns)
            db.quickDao().upsertAll(data.quickItems)
            db.templateDao().insertAll(data.templates)
        }
        settings.setCurrency(data.currency)
        settings.setUnit(data.quantityUnit)
        settings.setDemo(seeded = true, active = false)
        repo.bootstrap()
        data.sheets.size
    }

    /** Simple CSV (opens in Excel) of all sheets using the currently visible columns. UTF-8 with BOM. */
    suspend fun exportCsv(out: OutputStream) = withContext(Dispatchers.IO) {
        val s = settings.current()
        val cols = db.columnDao().all().filter { it.visible }
        val sheets = db.sheetDao().all()
        val entries = db.entryDao().all().groupBy { it.sheetId }
        fun esc(v: String) = "\"" + v.replace("\"", "\"\"") + "\""
        val sb = StringBuilder()
        sb.append('\uFEFF')
        sb.append((listOf("تاریخ", "برگه") + cols.map { it.title } + listOf("واحد پول: " + s.currencyLabel)).joinToString(",") { esc(it) }).append("\r\n")
        for (sh in sheets) {
            for (e in entries[sh.id].orEmpty()) {
                val row = mutableListOf(Fa.date(sh.dateKey, persian = false), sh.title)
                for (c in cols) {
                    row.add(
                        when (c.key) {
                            ColKey.COST -> if (e.cost > 0) e.cost.toString() else ""
                            ColKey.DEPOSIT -> if (e.deposit > 0) e.deposit.toString() else ""
                            ColKey.QUANTITY -> if (e.quantity > 0) Fa.qty(e.quantity, false) else ""
                            else -> com.lax1819.app.util.EntryFormat.value(e, c, s.quantityUnit, s.currencyLabel, persian = false)
                        }
                    )
                }
                row.add("")
                sb.append(row.joinToString(",") { esc(it) }).append("\r\n")
            }
        }
        out.use { it.write(sb.toString().toByteArray(Charsets.UTF_8)) }
    }
}
