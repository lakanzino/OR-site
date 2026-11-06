package com.hamyarj.clinic.util

import java.util.Locale

/** 1200000 -> "1,200,000" */
fun Long.formatThousands(): String = String.format(Locale.US, "%,d", this)

/** "1,200,000 تومان" */
fun formatMoney(amount: Long, unit: String): String =
    "${amount.formatThousands()} $unit".trim()

/**
 * Keeps only digits. Persian (۰-۹) and Arabic-Indic (٠-٩) digits are converted
 * to Latin digits, everything else (commas, spaces, letters) is dropped.
 */
fun String.digitsOnly(): String = buildString {
    for (c in this@digitsOnly) {
        when (c) {
            in '0'..'9' -> append(c)
            in '\u06F0'..'\u06F9' -> append('0' + (c - '\u06F0'))
            in '\u0660'..'\u0669' -> append('0' + (c - '\u0660'))
        }
    }
}

/** Parses a (possibly formatted) money string into a Long. Empty -> 0 */
fun String.toMoneyLong(): Long = digitsOnly().take(15).toLongOrNull() ?: 0L

/** Normalises Arabic letter variants so searching "علی" matches "عليّ" etc. */
fun String.normalizePersian(): String =
    replace('ي', 'ی').replace('ك', 'ک').replace("\u200c", " ").trim().lowercase()
