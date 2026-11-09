package com.lax1819.app.data

import android.content.Context
import androidx.room.withTransaction
import com.lax1819.app.R
import com.lax1819.app.util.Jalali
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.serialization.builtins.ListSerializer
import javax.inject.Inject
import javax.inject.Singleton

/** Quick modes that pre-configure useful columns. */
object Modes {
    const val GENERAL = "general"
    const val SHOP = "shop"
    const val SERVICE = "service"
    const val PERSONAL = "personal"
    const val INSTALLMENT = "installment"

    val all = listOf(GENERAL, SHOP, SERVICE, PERSONAL, INSTALLMENT)

    fun visibleKeys(mode: String): Set<String> = when (mode) {
        SHOP -> setOf(ColKey.PRODUCT, ColKey.QUANTITY, ColKey.COST, ColKey.DEPOSIT, ColKey.PROD_DATE, ColKey.EXP_DATE, ColKey.NOTE)
        SERVICE -> setOf(ColKey.FULL_NAME, ColKey.SERVICE, ColKey.ARRIVE, ColKey.END, ColKey.COST, ColKey.DEPOSIT, ColKey.STATUS, ColKey.NOTE)
        PERSONAL -> setOf(ColKey.PRODUCT, ColKey.COST, ColKey.NOTE, ColKey.STATUS)
        INSTALLMENT -> setOf(ColKey.FULL_NAME, ColKey.PRODUCT, ColKey.COST, ColKey.DEPOSIT, ColKey.INSTALLMENT, ColKey.NOTE)
        else -> setOf(ColKey.FULL_NAME, ColKey.PRODUCT, ColKey.QUANTITY, ColKey.COST, ColKey.DEPOSIT, ColKey.NOTE)
    }

    fun title(mode: String): String = when (mode) {
        SHOP -> "مغازه / فروشگاه"
        SERVICE -> "خدمات"
        PERSONAL -> "شخصی"
        INSTALLMENT -> "فروش اقساطی"
        else -> "عمومی"
    }
}

/**
 * Single source of truth for all local data. Every operation runs on Room's
 * background executors (suspend / Flow), never on the main thread.
 */
@Singleton
class Repository @Inject constructor(
    @ApplicationContext private val context: Context,
    val db: AppDatabase,
    val settings: SettingsStore,
) {
    private val sheets = db.sheetDao()
    private val entries = db.entryDao()
    private val columnDao = db.columnDao()
    private val quick = db.quickDao()
    private val templates = db.templateDao()

    // ---------- observation ----------
    val columns: Flow<List<ColumnEntity>> = columnDao.observeAll()
    val templateList: Flow<List<TemplateEntity>> = templates.observeAll()
    fun summaries(dateKey: Int) = sheets.observeSummaries(dateKey)
    fun monthCounts(from: Int, to: Int) = sheets.observeMonthCounts(from, to)
    fun sheetById(id: Long) = sheets.observe(id)
    fun entriesOf(sheetId: Long) = entries.observeBySheet(sheetId)
    fun quickItems(kind: String) = quick.observe(kind)

    // ---------- bootstrap ----------
    private fun col(key: String, res: Int, type: String, pos: Int, visible: Boolean) =
        ColumnEntity(key, context.getString(res), type, visible, locked = false, position = pos)

    private fun defaultColumns(mode: String): List<ColumnEntity> {
        val v = Modes.visibleKeys(mode)
        fun vis(k: String) = k in v
        return listOf(
            col(ColKey.FULL_NAME, R.string.col_full_name, ColType.TEXT, 0, vis(ColKey.FULL_NAME)),
            col(ColKey.PRODUCT, R.string.col_product, ColType.TEXT, 1, vis(ColKey.PRODUCT)),
            col(ColKey.SERVICE, R.string.col_service, ColType.TEXT, 2, vis(ColKey.SERVICE)),
            col(ColKey.PROD_DATE, R.string.col_prod_date, ColType.DATE, 3, vis(ColKey.PROD_DATE)),
            col(ColKey.EXP_DATE, R.string.col_exp_date, ColType.DATE, 4, vis(ColKey.EXP_DATE)),
            col(ColKey.ARRIVE, R.string.col_arrive, ColType.TIME, 5, vis(ColKey.ARRIVE)),
            col(ColKey.END, R.string.col_end, ColType.TIME, 6, vis(ColKey.END)),
            col(ColKey.VISIT, R.string.col_visit, ColType.TIME, 7, vis(ColKey.VISIT)),
            col(ColKey.QUANTITY, R.string.col_quantity, ColType.QUANTITY, 8, vis(ColKey.QUANTITY)),
            col(ColKey.COST, R.string.col_cost, ColType.MONEY, 9, vis(ColKey.COST)),
            col(ColKey.DEPOSIT, R.string.col_deposit, ColType.MONEY, 10, vis(ColKey.DEPOSIT)),
            col(ColKey.INSTALLMENT, R.string.col_installment, ColType.INSTALLMENT, 11, vis(ColKey.INSTALLMENT)),
            col(ColKey.NOTE, R.string.col_note, ColType.NOTE, 12, vis(ColKey.NOTE)),
            col(ColKey.STATUS, R.string.col_status, ColType.STATUS, 13, vis(ColKey.STATUS)),
        )
    }

    /** Called at every launch: creates default columns (once) and demo data (once). */
    suspend fun bootstrap() {
        val s = settings.current()
        if (columnDao.count() == 0) columnDao.upsertAll(defaultColumns(s.mode))
        if (!s.demoSeeded) {
            seedDemo()
            settings.setDemo(seeded = true, active = true)
        }
    }

    private suspend fun seedDemo() {
        val today = Jalali.todayKey()
        val shop = sheets.insert(SheetEntity(dateKey = today, title = "★ نمونه: فروشگاه"))
        val t = System.currentTimeMillis()
        entries.insertAll(
            listOf(
                EntryEntity(sheetId = shop, position = t, fullName = "آقای رضایی", product = "برنج ایرانی", quantity = 5.0, cost = 1_250_000, deposit = 500_000, note = "تحویل فردا"),
                EntryEntity(sheetId = shop, position = t + 1, fullName = "خانم احمدی", product = "روغن مایع", quantity = 2.0, cost = 480_000, note = "نقدی"),
                EntryEntity(sheetId = shop, position = t + 2, product = "شکر", quantity = 10.0, cost = 700_000, expDate = Jalali.key(Jalali.today().y + 1, 3, 15)),
            )
        )
        val salon = sheets.insert(SheetEntity(dateKey = today, title = "★ نمونه: آرایشگاه"))
        entries.insertAll(
            listOf(
                EntryEntity(sheetId = salon, position = t, fullName = "علی محمدی", service = "اصلاح سر و صورت", arrive = "10:30", cost = 250_000, status = 2),
                EntryEntity(sheetId = salon, position = t + 1, fullName = "حسین کریمی", service = "کوتاهی مو", arrive = "11:15", cost = 200_000, deposit = 50_000, status = 1),
            )
        )
    }

    suspend fun removeDemo() {
        sheets.deleteDemo()
        settings.setDemo(seeded = true, active = false)
    }

    // ---------- sheets ----------
    suspend fun createSheet(dateKey: Int, title: String, copyPrevious: Boolean): Long = db.withTransaction {
        val id = sheets.insert(SheetEntity(dateKey = dateKey, title = title.trim()))
        if (copyPrevious) {
            val prev = sheets.previousSheet(dateKey)
            if (prev != null) {
                val base = System.currentTimeMillis()
                val copy = entries.listBySheet(prev.id).mapIndexed { i, e ->
                    e.copy(id = 0, sheetId = id, position = base + i, instPaid = 0)
                }
                entries.insertAll(copy)
            }
        }
        id
    }

    /** Duplicates the rows of the most recent earlier sheet into [sheetId]. */
    suspend fun copyFromPrevious(sheetId: Long): Boolean {
        val sh = sheets.get(sheetId) ?: return false
        val prev = sheets.previousSheet(sh.dateKey) ?: return false
        val base = System.currentTimeMillis()
        entries.insertAll(entries.listBySheet(prev.id).mapIndexed { i, e ->
            e.copy(id = 0, sheetId = sheetId, position = base + i, instPaid = 0)
        })
        return true
    }

    suspend fun renameSheet(sheet: SheetEntity, title: String) = sheets.update(sheet.copy(title = title.trim()))
    suspend fun deleteSheet(id: Long) = sheets.delete(id)

    // ---------- entries ----------
    suspend fun saveEntry(e: EntryEntity): Long {
        val id = if (e.id == 0L) entries.insert(e.copy(position = System.currentTimeMillis()))
        else { entries.update(e); e.id }
        recordQuick(QuickKind.NAME, e.fullName)
        recordQuick(QuickKind.PRODUCT, e.product)
        recordQuick(QuickKind.SERVICE, e.service)
        return id
    }

    suspend fun updateEntry(e: EntryEntity) = entries.update(e)
    suspend fun deleteEntry(id: Long) = entries.delete(id)
    suspend fun restoreEntry(e: EntryEntity) { entries.insert(e) }
    suspend fun duplicateEntry(e: EntryEntity) { entries.insert(e.copy(id = 0, position = e.position + 1, instPaid = 0)) }

    // ---------- quick items ----------
    private suspend fun recordQuick(kind: String, text: String) {
        val t = text.trim()
        if (t.isEmpty()) return
        val old = quick.find(kind, t)
        quick.upsert(old?.copy(useCount = old.useCount + 1, lastUsed = System.currentTimeMillis()) ?: QuickItemEntity(kind = kind, text = t))
    }

    suspend fun addQuick(kind: String, text: String, favorite: Boolean = false) {
        val t = text.trim()
        if (t.isEmpty()) return
        val old = quick.find(kind, t)
        quick.upsert(old?.copy(favorite = favorite || old.favorite) ?: QuickItemEntity(kind = kind, text = t, useCount = 0, favorite = favorite))
    }

    suspend fun toggleFavorite(item: QuickItemEntity) = quick.upsert(item.copy(favorite = !item.favorite))
    suspend fun deleteQuick(item: QuickItemEntity) = quick.delete(item)

    // ---------- columns ----------
    suspend fun saveColumn(c: ColumnEntity) = columnDao.upsert(c)

    suspend fun saveColumnOrder(list: List<ColumnEntity>) =
        columnDao.upsertAll(list.mapIndexed { i, c -> c.copy(position = i) })

    suspend fun addCustomColumn(title: String, type: String) {
        val all = columnDao.all()
        val key = "c_" + System.currentTimeMillis()
        columnDao.upsert(ColumnEntity(key, title.trim(), type, visible = true, locked = false, position = all.size, custom = true))
    }

    suspend fun deleteColumn(key: String) = columnDao.delete(key)

    /** Applies a quick mode: shows/hides built-in columns (locked columns are untouched). */
    suspend fun applyMode(mode: String) {
        val keys = Modes.visibleKeys(mode)
        val updated = columnDao.all().map { c ->
            if (c.locked || c.custom) c else c.copy(visible = c.key in keys)
        }
        columnDao.upsertAll(updated)
        settings.setMode(mode)
    }

    // ---------- templates ----------
    suspend fun saveTemplate(sheetId: Long, name: String) {
        val list = entries.listBySheet(sheetId).map { it.copy(id = 0, sheetId = 0, instPaid = 0) }
        val json = AppJson.encodeToString(ListSerializer(EntryEntity.serializer()), list)
        templates.insert(TemplateEntity(name = name.trim(), json = json))
    }

    suspend fun applyTemplate(sheetId: Long, t: TemplateEntity) {
        val list = AppJson.decodeFromString(ListSerializer(EntryEntity.serializer()), t.json)
        val base = System.currentTimeMillis()
        entries.insertAll(list.mapIndexed { i, e -> e.copy(id = 0, sheetId = sheetId, position = base + i) })
    }

    suspend fun deleteTemplate(id: Long) = templates.delete(id)

    // ---------- currency & wipe ----------
    suspend fun changeCurrency(newCurrency: String, convertValues: Boolean) {
        val old = settings.current().currency
        if (old == newCurrency) return
        db.withTransaction {
            if (convertValues) {
                if (newCurrency == Currency.RIAL) entries.multiplyMoney() else entries.divideMoney()
            }
        }
        settings.setCurrency(newCurrency)
    }

    suspend fun clearAllData() {
        db.withTransaction {
            sheets.deleteAll()
            entries.deleteAll()
            columnDao.deleteAll()
            quick.deleteAll()
            templates.deleteAll()
        }
        settings.setDemo(seeded = true, active = false)
        columnDao.upsertAll(defaultColumns(settings.current().mode))
    }
}
