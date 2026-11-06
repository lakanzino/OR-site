package ir.clinic.secretary.util

import ir.clinic.secretary.data.Patient

object CsvExport {
    /** Protect spreadsheet users from formula injection in names, reasons and phone numbers. */
    fun cell(value: String): String {
        val trimmed = value.trimStart()
        val safe = if (trimmed.firstOrNull() in listOf('=', '+', '-', '@') ||
            value.firstOrNull() in listOf('\t', '\r', '\n')) "'$value" else value
        return "\"" + safe.replace("\"", "\"\"") + "\""
    }

    fun build(patients: List<Patient>, displayCurrency: Currency): String = buildString {
        append('\uFEFF') // UTF-8 BOM for Persian text in spreadsheet applications.
        append(rows(patients, displayCurrency).joinToString("") { it + "\r\n" })
    }

    private fun rows(patients: List<Patient>, displayCurrency: Currency): List<String> = buildList {
        add(listOf("شناسه", "نام", "تلفن", "بیمه", "بیمه مکمل", "علت مراجعه", "تاریخ", "ساعت", "مبلغ ثبت‌شده", "واحد ثبت‌شده", "مبلغ به ریال", "مبلغ نمایشی", "واحد نمایشی").joinToString(",") { cell(it) })
        for (p in patients) {
            val unit = Currency.fromCode(p.feeCurrency)
            val rials = Money.rialsOf(p.fee, unit)
            add(listOf(
                p.id.toString(), p.name, p.phone, p.insuranceType, if (p.hasSupplemental) "بله" else "خیر",
                p.reason, p.visitDate, p.visitTime, p.fee.toString(), unit.label,
                rials?.toString() ?: "", rials?.let { Money.inputAmount(it, displayCurrency) } ?: "", displayCurrency.label
            ).joinToString(",") { cell(it) })
        }
    }
}
