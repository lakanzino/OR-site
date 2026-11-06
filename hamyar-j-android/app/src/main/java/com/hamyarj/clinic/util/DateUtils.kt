package com.hamyarj.clinic.util

import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone

/**
 * All date helpers live here. To show Jalali (Persian) dates later, only
 * [format] has to be changed.
 */
object DateUtils {

    fun startOfDay(millis: Long): Long = Calendar.getInstance().apply {
        timeInMillis = millis
        set(Calendar.HOUR_OF_DAY, 0); set(Calendar.MINUTE, 0)
        set(Calendar.SECOND, 0); set(Calendar.MILLISECOND, 0)
    }.timeInMillis

    fun endOfDay(millis: Long): Long = Calendar.getInstance().apply {
        timeInMillis = millis
        set(Calendar.HOUR_OF_DAY, 23); set(Calendar.MINUTE, 59)
        set(Calendar.SECOND, 59); set(Calendar.MILLISECOND, 999)
    }.timeInMillis

    fun startOfMonth(millis: Long): Long = Calendar.getInstance().apply {
        timeInMillis = startOfDay(millis)
        set(Calendar.DAY_OF_MONTH, 1)
    }.timeInMillis

    fun format(millis: Long): String =
        SimpleDateFormat("yyyy/MM/dd", Locale.US).format(Date(millis))

    fun nowTime(): String = SimpleDateFormat("HH:mm", Locale.US).format(Date())

    fun fileStamp(): String = SimpleDateFormat("yyyyMMdd_HHmm", Locale.US).format(Date())

    /** Material3 DatePicker works with UTC-midnight millis; convert from local millis. */
    fun localToPickerMillis(local: Long): Long {
        val c = Calendar.getInstance().apply { timeInMillis = local }
        return Calendar.getInstance(TimeZone.getTimeZone("UTC")).apply {
            clear()
            set(c.get(Calendar.YEAR), c.get(Calendar.MONTH), c.get(Calendar.DAY_OF_MONTH))
        }.timeInMillis
    }

    /** Convert UTC-midnight millis (from DatePicker) to local start-of-day millis. */
    fun pickerToLocalMillis(utc: Long): Long {
        val c = Calendar.getInstance(TimeZone.getTimeZone("UTC")).apply { timeInMillis = utc }
        return Calendar.getInstance().apply {
            clear()
            set(c.get(Calendar.YEAR), c.get(Calendar.MONTH), c.get(Calendar.DAY_OF_MONTH))
        }.timeInMillis
    }
}
