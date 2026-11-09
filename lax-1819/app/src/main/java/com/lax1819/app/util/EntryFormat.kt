package com.lax1819.app.util

import com.lax1819.app.data.ColKey
import com.lax1819.app.data.ColType
import com.lax1819.app.data.ColumnEntity
import com.lax1819.app.data.EntryEntity

/** Converts entry fields into display / export text. */
object EntryFormat {
    /** index 0 = no status */
    val statusNames = listOf("بدون وضعیت", "در انتظار", "انجام شد", "لغو شد", "پیگیری")

    /** Value of a column for an entry as text ("" if empty). */
    fun value(
        e: EntryEntity,
        c: ColumnEntity,
        qtyUnit: String,
        currencyLabel: String,
        persian: Boolean = true,
    ): String {
        fun money(v: Long) = Fa.group(v, persian) + " " + currencyLabel
        return when (c.key) {
            ColKey.FULL_NAME -> e.fullName
            ColKey.PRODUCT -> e.product
            ColKey.SERVICE -> e.service
            ColKey.PROD_DATE -> Fa.date(e.prodDate, persian)
            ColKey.EXP_DATE -> Fa.date(e.expDate, persian)
            ColKey.ARRIVE -> Fa.time(e.arrive, persian)
            ColKey.END -> Fa.time(e.end, persian)
            ColKey.VISIT -> Fa.time(e.visit, persian)
            ColKey.QUANTITY -> if (e.quantity > 0) Fa.qty(e.quantity, persian) + " " + qtyUnit else ""
            ColKey.COST -> if (e.cost > 0) money(e.cost) else ""
            ColKey.DEPOSIT -> if (e.deposit > 0) money(e.deposit) else ""
            ColKey.INSTALLMENT -> if (e.instCount > 0 || e.instAmount > 0) {
                val cnt = if (persian) Fa.int(e.instCount) else e.instCount.toString()
                val paid = if (persian) Fa.int(e.instPaid) else e.instPaid.toString()
                "$paid/$cnt قسط × ${money(e.instAmount)} | مانده: ${money(e.instRemaining)}"
            } else ""
            ColKey.NOTE -> e.note
            ColKey.STATUS -> if (e.status in 1 until statusNames.size) statusNames[e.status] else ""
            else -> {
                val v = e.customValues()[c.key].orEmpty()
                if (persian && c.type == ColType.NUMBER) Fa.digits(v) else v
            }
        }
    }

    /** Plain-text summary of one sheet (for sharing). */
    fun shareText(
        title: String,
        dateKey: Int,
        entries: List<EntryEntity>,
        columns: List<ColumnEntity>,
        qtyUnit: String,
        currencyLabel: String,
    ): String {
        val visible = columns.filter { it.visible }
        val sb = StringBuilder()
        sb.append("📒 Lax-1819 | ").append(Fa.dateLong(dateKey))
        if (title.isNotBlank()) sb.append("\n").append(title)
        sb.append("\n──────────\n")
        entries.forEachIndexed { i, e ->
            sb.append(Fa.int(i + 1)).append(") ")
            val parts = visible.mapNotNull { c ->
                val v = value(e, c, qtyUnit, currencyLabel)
                if (v.isBlank()) null else "${c.title}: $v"
            }
            sb.append(parts.joinToString(" | ")).append("\n")
        }
        sb.append("──────────\n")
        val totals = Totals.of(entries)
        sb.append("تعداد: ").append(Fa.int(totals.count)).append("\n")
        if (totals.cost > 0) sb.append("جمع هزینه: ").append(Fa.group(totals.cost)).append(" ").append(currencyLabel).append("\n")
        if (totals.deposit > 0) sb.append("جمع بیعانه: ").append(Fa.group(totals.deposit)).append(" ").append(currencyLabel).append("\n")
        if (totals.instRemaining > 0) sb.append("مانده اقساط: ").append(Fa.group(totals.instRemaining)).append(" ").append(currencyLabel).append("\n")
        return sb.toString()
    }
}

/** Aggregates for the sticky summary bar. */
data class Totals(
    val count: Int = 0,
    val cost: Long = 0,
    val deposit: Long = 0,
    val instRemaining: Long = 0,
    val quantity: Double = 0.0,
) {
    companion object {
        fun of(list: List<EntryEntity>): Totals {
            var cost = 0L
            var dep = 0L
            var inst = 0L
            var qty = 0.0
            for (e in list) {
                cost += e.cost
                dep += e.deposit
                inst += e.instRemaining
                qty += e.quantity
            }
            return Totals(list.size, cost, dep, inst, qty)
        }
    }
}
