package com.lax1819.app.util

/** Persian number / date formatting helpers. */
object Fa {
    const val SEP = '٬' // Persian thousands separator
    const val DECIMAL = '٫'

    private const val PERSIAN_ZERO = '۰'

    fun digits(s: String): String {
        val sb = StringBuilder(s.length)
        for (ch in s) {
            when (ch) {
                in '0'..'9' -> sb.append(PERSIAN_ZERO + (ch - '0'))
                ',' -> sb.append(SEP)
                else -> sb.append(ch)
            }
        }
        return sb.toString()
    }

    /** Converts Persian/Arabic-Indic digits to ASCII and keeps digits only. */
    fun onlyDigits(s: String): String {
        val sb = StringBuilder(s.length)
        for (ch in s) {
            when (ch) {
                in '0'..'9' -> sb.append(ch)
                in '۰'..'۹' -> sb.append('0' + (ch - '۰'))
                in '٠'..'٩' -> sb.append('0' + (ch - '٠'))
            }
        }
        return sb.toString()
    }

    /** Digits plus a single decimal point (for quantities like 1.5). */
    fun onlyDecimal(s: String): String {
        val sb = StringBuilder()
        var dot = false
        for (ch in s) {
            when (ch) {
                in '0'..'9' -> sb.append(ch)
                in '۰'..'۹' -> sb.append('0' + (ch - '۰'))
                in '٠'..'٩' -> sb.append('0' + (ch - '٠'))
                '.', '٫', ',' -> if (!dot) { dot = true; sb.append('.') }
            }
        }
        return sb.toString()
    }

    fun group(n: Long, persian: Boolean = true): String {
        val raw = Math.abs(n).toString()
        val sb = StringBuilder()
        for (i in raw.indices) {
            if (i > 0 && (raw.length - i) % 3 == 0) sb.append(if (persian) SEP else ',')
            sb.append(raw[i])
        }
        val r = sb.toString()
        val signed = if (n < 0) "-$r" else r
        return if (persian) digits(signed) else signed
    }

    fun int(n: Int): String = digits(n.toString())

    fun qty(d: Double, persian: Boolean = true): String {
        val s = if (d == Math.floor(d) && Math.abs(d) < 1e12) d.toLong().toString()
        else String.format(java.util.Locale.US, "%.2f", d).trimEnd('0').trimEnd('.')
        return if (persian) digits(s).replace('.', DECIMAL) else s
    }

    fun two(n: Int): String = if (n < 10) "0$n" else n.toString()

    /** 1403/07/15 (empty when not set). */
    fun date(key: Int, persian: Boolean = true): String {
        if (key <= 0) return ""
        val p = Jalali.parse(key)
        val s = "${p.y}/${two(p.m)}/${two(p.d)}"
        return if (persian) digits(s) else s
    }

    /** پنجشنبه ۱۵ مهر ۱۴۰۳ */
    fun dateLong(key: Int): String {
        val p = Jalali.parse(key)
        val wd = Jalali.weekDayNames[Jalali.weekDayIndex(p.y, p.m, p.d)]
        return "$wd ${int(p.d)} ${Jalali.monthNames[p.m - 1]} ${int(p.y)}"
    }

    fun time(t: String, persian: Boolean = true): String = if (persian) digits(t) else t
}
