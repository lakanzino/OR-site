package com.lax1819.app.data

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey
import kotlinx.serialization.Serializable
import kotlinx.serialization.builtins.MapSerializer
import kotlinx.serialization.builtins.serializer
import kotlinx.serialization.json.Json

/** Shared JSON config (backup files + custom column values). */
val AppJson = Json {
    ignoreUnknownKeys = true
    encodeDefaults = true
}

/** Column data types. Stored as plain strings in the database. */
object ColType {
    const val TEXT = "text"
    const val NUMBER = "number"
    const val DATE = "date"
    const val TIME = "time"
    const val QUANTITY = "quantity"
    const val MONEY = "money"
    const val INSTALLMENT = "installment"
    const val NOTE = "note"
    const val STATUS = "status"
}

/** Keys of the built-in columns. Custom columns use keys like "c_<timestamp>". */
object ColKey {
    const val FULL_NAME = "fullName"
    const val PRODUCT = "product"
    const val SERVICE = "service"
    const val PROD_DATE = "prodDate"
    const val EXP_DATE = "expDate"
    const val ARRIVE = "arrive"
    const val END = "end"
    const val VISIT = "visit"
    const val QUANTITY = "quantity"
    const val COST = "cost"
    const val DEPOSIT = "deposit"
    const val INSTALLMENT = "installment"
    const val NOTE = "note"
    const val STATUS = "status"
}

/** Quick item kinds (favorites / recents lists). */
object QuickKind {
    const val NAME = "name"
    const val PRODUCT = "product"
    const val SERVICE = "service"
}

/** One sheet (برگه) belonging to a Jalali day. dateKey = yyyymmdd (Jalali). */
@Serializable
@Entity(tableName = "sheets", indices = [Index("dateKey")])
data class SheetEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val dateKey: Int,
    val title: String = "",
    val createdAt: Long = System.currentTimeMillis(),
)

/**
 * One row of a sheet. Every field is optional (empty / 0 = not set).
 * Dates are Jalali yyyymmdd ints, times are "HH:mm" strings, money is stored
 * as a whole number in the user's current currency unit.
 */
@Serializable
@Entity(
    tableName = "entries",
    foreignKeys = [
        ForeignKey(
            entity = SheetEntity::class,
            parentColumns = ["id"],
            childColumns = ["sheetId"],
            onDelete = ForeignKey.CASCADE,
        )
    ],
    indices = [Index("sheetId")],
)
data class EntryEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val sheetId: Long,
    val position: Long = System.currentTimeMillis(),
    val fullName: String = "",
    val product: String = "",
    val service: String = "",
    val prodDate: Int = 0,
    val expDate: Int = 0,
    val arrive: String = "",
    val end: String = "",
    val visit: String = "",
    val quantity: Double = 0.0,
    val cost: Long = 0,
    val deposit: Long = 0,
    val instCount: Int = 0,
    val instAmount: Long = 0,
    val instPaid: Int = 0,
    val note: String = "",
    val status: Int = 0,
    /** JSON map: custom column key -> value */
    val custom: String = "{}",
) {
    /** Remaining installments amount (automatic). */
    val instRemaining: Long
        get() = (instCount - instPaid).coerceAtLeast(0) * instAmount

    fun customValues(): Map<String, String> = try {
        AppJson.decodeFromString(MapSerializer(String.serializer(), String.serializer()), custom)
    } catch (e: Exception) {
        emptyMap()
    }

    fun withCustom(key: String, value: String): EntryEntity {
        val m = customValues().toMutableMap()
        if (value.isEmpty()) m.remove(key) else m[key] = value
        return copy(custom = AppJson.encodeToString(MapSerializer(String.serializer(), String.serializer()), m))
    }
}

/** Column configuration: visibility, lock, display name and order. */
@Serializable
@Entity(tableName = "columns")
data class ColumnEntity(
    @PrimaryKey val key: String,
    val title: String,
    val type: String,
    val visible: Boolean,
    val locked: Boolean = false,
    val position: Int,
    val custom: Boolean = false,
)

/** Saved favorites / recent names, products and services. */
@Serializable
@Entity(tableName = "quick_items", indices = [Index(value = ["kind", "text"], unique = true)])
data class QuickItemEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val kind: String,
    val text: String,
    val useCount: Int = 1,
    val lastUsed: Long = System.currentTimeMillis(),
    val favorite: Boolean = false,
)

/** A reusable template: a list of entries serialized as JSON. */
@Serializable
@Entity(tableName = "templates")
data class TemplateEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val name: String,
    val json: String,
)

/** Home list projection: a sheet with small aggregates. */
data class SheetSummary(
    val id: Long,
    val dateKey: Int,
    val title: String,
    val createdAt: Long,
    val entryCount: Int,
    val totalCost: Long,
)

/** Calendar projection: number of sheets in a day. */
data class DayCount(val dateKey: Int, val cnt: Int)
