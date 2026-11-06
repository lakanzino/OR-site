package ir.clinic.secretary.util

enum class Currency(val code: String, val rialsPerUnit: Int, val label: String) {
    TOMAN("TOMAN", 10, "تومان"),
    RIAL("RIAL", 1, "ریال");

    companion object {
        fun fromCode(code: String?): Currency = entries.firstOrNull { it.code == code } ?: TOMAN
    }
}

object Money {
    /** Accepts Persian/Arabic digits, grouping separators, and at most one decimal digit in Toman. */
    fun parseToRials(input: String?, unit: Currency): Long? {
        val text = Fa.normalize(input.orEmpty()).trim()
            .replace("٬", "").replace(",", "").replace("٫", ".").replace(" ", "")
        if (text.isEmpty()) return null
        val parts = text.split('.')
        if (parts.size > 2 || parts.any { it.isEmpty() || it.any { c -> c !in '0'..'9' } }) return null
        if (parts.size == 2 && (unit == Currency.RIAL || parts[1].length != 1)) return null
        val whole = parts[0].toLongOrNull() ?: return null
        val fraction = if (parts.size == 2) parts[1].toLong() else 0L
        if (whole > Long.MAX_VALUE / unit.rialsPerUnit) return null
        val rials = whole * unit.rialsPerUnit
        return if (Long.MAX_VALUE - rials >= fraction) rials + fraction else null
    }

    fun rialsOf(amount: Long, unit: Currency): Long? =
        if (amount < 0 || amount > Long.MAX_VALUE / unit.rialsPerUnit) null
        else amount * unit.rialsPerUnit

    /** Plain ASCII text for editable fields; never loses a fractional Toman during unit changes. */
    fun inputAmount(rials: Long, unit: Currency): String {
        require(rials >= 0)
        val whole = rials / unit.rialsPerUnit
        val remainder = rials % unit.rialsPerUnit
        return if (remainder == 0L) whole.toString() else "$whole.$remainder"
    }

    fun format(rials: Long, unit: Currency): String {
        val amount = Fa.money(rials / unit.rialsPerUnit)
        val remainder = rials % unit.rialsPerUnit
        return (if (remainder == 0L) amount else "$amount٫${Fa.digits(remainder.toString())}") + " ${unit.label}"
    }

    fun totalRials(values: List<Long>): Long? {
        var total = 0L
        for (value in values) {
            if (value < 0 || total > Long.MAX_VALUE - value) return null
            total += value
        }
        return total
    }
}
