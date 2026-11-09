package com.lax1819.app.util

import java.util.Calendar

/**
 * Native Jalali (Persian / Solar Hijri) calendar core.
 *
 * Pure Kotlin port of the well known "jalaali-js" algorithm (MIT) – no external
 * dependency, works on every Android version (API 21+), fully offline and
 * valid for years -61 … 3177.
 *
 * Dates are passed around as "date keys": yyyymmdd as an Int (0 = not set).
 */
object Jalali {
    private val breaks = intArrayOf(
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178
    )

    val monthNames = listOf(
        "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
        "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"
    )

    /** Saturday-first week */
    val weekDayNames = listOf("شنبه", "یکشنبه", "دوشنبه", "سه‌شنبه", "چهارشنبه", "پنجشنبه", "جمعه")
    val weekDayShort = listOf("ش", "ی", "د", "س", "چ", "پ", "ج")

    data class Ymd(val y: Int, val m: Int, val d: Int)

    private class JalCal(val leap: Int, val gy: Int, val march: Int)

    private fun jalCal(jy: Int): JalCal {
        val bl = breaks.size
        val gy = jy + 621
        var leapJ = -14
        var jp = breaks[0]
        var jump = 0
        require(jy >= jp && jy < breaks[bl - 1]) { "Invalid Jalali year $jy" }
        for (i in 1 until bl) {
            val jm = breaks[i]
            jump = jm - jp
            if (jy < jm) break
            leapJ += (jump / 33) * 8 + (jump % 33) / 4
            jp = jm
        }
        var n = jy - jp
        leapJ += (n / 33) * 8 + (n % 33 + 3) / 4
        if (jump % 33 == 4 && jump - n == 4) leapJ += 1
        val leapG = gy / 4 - ((gy / 100 + 1) * 3) / 4 - 150
        val march = 20 + leapJ - leapG
        if (jump - n < 6) n = n - jump + ((jump + 4) / 33) * 33
        var leap = ((n + 1) % 33 - 1) % 4
        if (leap == -1) leap = 4
        return JalCal(leap, gy, march)
    }

    private fun g2d(gy: Int, gm: Int, gd: Int): Int {
        var d = ((gy + (gm - 8) / 6 + 100100) * 1461) / 4 +
            (153 * ((gm + 9) % 12) + 2) / 5 + gd - 34840408
        d = d - (((gy + 100100 + (gm - 8) / 6) / 100) * 3) / 4 + 752
        return d
    }

    private fun d2g(jdn: Int): Ymd {
        var j = 4 * jdn + 139361631
        j += (((4 * jdn + 183187720) / 146097) * 3 / 4) * 4 - 3908
        val i = ((j % 1461) / 4) * 5 + 308
        val gd = (i % 153) / 5 + 1
        val gm = (i / 153) % 12 + 1
        val gy = j / 1461 - 100100 + (8 - gm) / 6
        return Ymd(gy, gm, gd)
    }

    private fun j2d(jy: Int, jm: Int, jd: Int): Int {
        val r = jalCal(jy)
        return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - (jm / 7) * (jm - 7) + jd - 1
    }

    private fun d2j(jdn: Int): Ymd {
        val gy = d2g(jdn).y
        var jy = gy - 621
        val r = jalCal(jy)
        val jdn1f = g2d(gy, 3, r.march)
        var k = jdn - jdn1f
        if (k >= 0) {
            if (k <= 185) {
                return Ymd(jy, 1 + k / 31, k % 31 + 1)
            } else {
                k -= 186
            }
        } else {
            jy -= 1
            k += 179
            if (r.leap == 1) k += 1
        }
        return Ymd(jy, 7 + k / 30, k % 30 + 1)
    }

    fun isLeap(jy: Int): Boolean = jalCal(jy).leap == 0

    fun monthLength(jy: Int, jm: Int): Int = when {
        jm <= 6 -> 31
        jm <= 11 -> 30
        else -> if (isLeap(jy)) 30 else 29
    }

    fun fromGregorian(gy: Int, gm: Int, gd: Int): Ymd = d2j(g2d(gy, gm, gd))

    fun toGregorian(jy: Int, jm: Int, jd: Int): Ymd = d2g(j2d(jy, jm, jd))

    fun today(): Ymd {
        val c = Calendar.getInstance()
        return fromGregorian(c.get(Calendar.YEAR), c.get(Calendar.MONTH) + 1, c.get(Calendar.DAY_OF_MONTH))
    }

    /** 0 = Saturday … 6 = Friday */
    fun weekDayIndex(jy: Int, jm: Int, jd: Int): Int = (j2d(jy, jm, jd) % 7 + 2) % 7

    fun key(y: Int, m: Int, d: Int): Int = y * 10000 + m * 100 + d
    fun key(v: Ymd): Int = key(v.y, v.m, v.d)
    fun parse(key: Int): Ymd = Ymd(key / 10000, (key / 100) % 100, key % 100)
    fun todayKey(): Int = key(today())

    /** Adds [months] to a (year, month) pair. */
    fun addMonths(y: Int, m: Int, months: Int): Pair<Int, Int> {
        val total = y * 12 + (m - 1) + months
        var ny = total / 12
        var nm = total % 12
        if (nm < 0) {
            nm += 12
            ny -= 1
        }
        return Pair(ny, nm + 1)
    }
}
