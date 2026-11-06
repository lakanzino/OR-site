package ir.clinic.secretary.util

import java.util.Calendar
import java.util.Locale
import java.util.TimeZone

/** Offline Jalali/Gregorian conversion (jalaali algorithm), independent of the device's calendar setting. */
data class PersianDate(val year: Int, val month: Int, val day: Int, val weekdayName: String = "") {
    fun format(): String = String.format(Locale.US, "%04d/%02d/%02d", year, month, day)
    fun longFormat(): String = "$weekdayName ${Fa.digits(day.toString())} ${MONTHS[month - 1]} ${Fa.digits(year.toString())}"
    val sortKey: Int get() = year * 10000 + month * 100 + day

    fun toCalendar(): Calendar {
        val jdn = jalaliToJulianDay(year, month, day)
        val anchorYear = year + 621
        val offset = jdn - gregorianToJulianDay(anchorYear, 3, jalaliMarchDay(anchorYear))
        require(offset >= 0 && offset < if (isLeap(year)) 366 else 365) { "Invalid Jalali day for this year" }
        val jm = if (offset < 186) 1 + offset / 31 else 7 + (offset - 186) / 30
        val jd = if (offset < 186) 1 + offset % 31 else 1 + (offset - 186) % 30
        require(jm == month && jd == day) { "Invalid Jalali date" }
        val gregorian = gregorianFromJulianDay(jdn)
        return utcCalendar(gregorian.first, gregorian.second, gregorian.third)
    }

    companion object {
        private val MONTHS = arrayOf("فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند")
        private val WEEKDAYS = arrayOf("شنبه", "یکشنبه", "دوشنبه", "سه‌شنبه", "چهارشنبه", "پنجشنبه", "جمعه")
        private val BREAKS = intArrayOf(-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178)

        fun today(): PersianDate = from(Calendar.getInstance())

        fun from(cal: Calendar): PersianDate {
            val gy = cal.get(Calendar.YEAR)
            val gm = cal.get(Calendar.MONTH) + 1
            val gd = cal.get(Calendar.DAY_OF_MONTH)
            val jdn = gregorianToJulianDay(gy, gm, gd)
            var jy = gy - 621
            var delta = jdn - gregorianToJulianDay(gy, 3, jalaliMarchDay(gy))
            if (delta < 0) {
                jy--
                delta = jdn - gregorianToJulianDay(gy - 1, 3, jalaliMarchDay(gy - 1))
            }
            val month = if (delta < 186) 1 + delta / 31 else 7 + (delta - 186) / 30
            val day = if (delta < 186) 1 + delta % 31 else 1 + (delta - 186) % 30
            val weekday = ((jdn + 2) % 7 + 7) % 7
            return PersianDate(jy, month, day, WEEKDAYS[weekday])
        }

        fun parseOrNull(input: String): PersianDate? {
            val match = Regex("^(\\d{4})/(\\d{1,2})/(\\d{1,2})$").matchEntire(Fa.normalize(input).trim()) ?: return null
            val (y, m, d) = match.destructured
            val date = PersianDate(y.toInt(), m.toInt(), d.toInt())
            if (date.year !in 1300..1500 || date.month !in 1..12 || date.day !in 1..31) return null
            return try { date.toCalendar(); date } catch (_: Exception) { null }
        }

        fun daysAgo(days: Int): PersianDate {
            val cal = Calendar.getInstance()
            cal.add(Calendar.DAY_OF_YEAR, -days)
            return from(cal)
        }

        // jalaali's integer division truncates toward zero; Kotlin Int division does the same.
        private fun div(a: Int, b: Int): Int = a / b
        private fun mod(a: Int, b: Int): Int = a - div(a, b) * b

        private fun marchInfo(gy: Int): Pair<Int, Int> {
            val jy = gy - 621
            var leapJ = -14
            var jp = BREAKS[0]
            var jump = 0
            for (i in 1 until BREAKS.size) {
                val jm = BREAKS[i]
                jump = jm - jp
                if (jy < jm) break
                leapJ += div(jump, 33) * 8 + div(mod(jump, 33), 4)
                jp = jm
            }
            var n = jy - jp
            leapJ += div(n, 33) * 8 + div(mod(n, 33) + 3, 4)
            if (mod(jump, 33) == 4 && jump - n == 4) leapJ++
            val leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150
            val march = 20 + leapJ - leapG
            if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33
            var leap = mod(mod(n + 1, 33) - 1, 4)
            if (leap == -1) leap = 4
            return march to leap
        }

        private fun jalaliMarchDay(gy: Int): Int = marchInfo(gy).first
        fun isLeap(jy: Int): Boolean = marchInfo(jy + 621).second == 0

        private fun gregorianToJulianDay(y: Int, m: Int, d: Int): Int =
            div((y + div(m - 8, 6) + 100100) * 1461, 4) + div(153 * mod(m + 9, 12) + 2, 5) + d - 34840408 -
            div(div(y + 100100 + div(m - 8, 6), 100) * 3, 4) + 752

        private fun jalaliToJulianDay(y: Int, m: Int, d: Int): Int {
            val gy = y + 621
            return gregorianToJulianDay(gy, 3, jalaliMarchDay(gy)) + (m - 1) * 31 - div(m, 7) * (m - 7) + d - 1
        }

        private fun gregorianFromJulianDay(jdn: Int): Triple<Int, Int, Int> {
            val j = 4 * jdn + 139361631 + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908
            val i = div(mod(j, 1461), 4) * 5 + 308
            val gd = div(mod(i, 153), 5) + 1
            val gm = mod(div(i, 153), 12) + 1
            val gy = div(j, 1461) - 100100 + div(8 - gm, 6)
            return Triple(gy, gm, gd)
        }

        private fun utcCalendar(y: Int, m: Int, d: Int): Calendar = Calendar.getInstance(TimeZone.getTimeZone("UTC"), Locale.US).apply {
            clear(); set(y, m - 1, d, 0, 0, 0)
        }
    }
}

/** Persian digit / number helpers. */
object Fa {
    private const val FA = "۰۱۲۳۴۵۶۷۸۹"
    fun digits(s: String): String = buildString { for (c in s) append(if (c in '0'..'9') FA[c - '0'] else c) }
    fun normalize(s: String): String = buildString {
        for (c in s) append(when (c) { in '۰'..'۹' -> '0' + (c - '۰'); in '٠'..'٩' -> '0' + (c - '٠'); else -> c })
    }
    fun parseNumber(s: String?): Long? = normalize(s.orEmpty()).filter { it.isDigit() }.toLongOrNull()
    fun money(v: Long): String = digits(String.format(Locale.US, "%,d", v)).replace(',', '٬')
}
