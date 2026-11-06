package com.hamyarj.clinic.util

import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.core.content.FileProvider
import com.hamyarj.clinic.data.Patient
import java.io.File

/** Builds a CSV of all patients and shares it through the Android share sheet. */
object CsvExporter {

    private val header = listOf(
        "شناسه", "نام", "تلفن", "بیمه پایه", "بیمه مکمل", "نوع بیمه مکمل",
        "دلیل مراجعه", "ساعت ویزیت", "مبلغ ویزیت", "تاریخ ثبت"
    )

    /** Writes the CSV file to cache/exports and returns a shareable content:// Uri. */
    fun createCsvUri(context: Context, patients: List<Patient>): Uri {
        val dir = File(context.cacheDir, "exports").apply { mkdirs() }
        dir.listFiles()?.forEach { it.delete() } // remove older exports
        val file = File(dir, "hamyar_patients_${DateUtils.fileStamp()}.csv")

        file.bufferedWriter(Charsets.UTF_8).use { w ->
            w.write("\uFEFF") // UTF-8 BOM so Excel shows Persian text correctly
            w.write(header.joinToString(",") { escape(it) })
            w.write("\r\n")
            patients.forEach { p ->
                val row = listOf(
                    p.id.toString(),
                    p.name,
                    p.phone,
                    p.basicInsurance,
                    if (p.hasSupplement) "دارد" else "ندارد",
                    p.supplementalInsurance,
                    p.reason,
                    p.visitTime,
                    p.visitFee.toString(),
                    DateUtils.format(p.createdAt)
                )
                w.write(row.joinToString(",") { escape(it) })
                w.write("\r\n")
            }
        }
        return FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    }

    fun share(context: Context, uri: Uri) {
        val intent = Intent(Intent.ACTION_SEND).apply {
            type = "text/csv"
            putExtra(Intent.EXTRA_STREAM, uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(Intent.createChooser(intent, "اشتراک‌گذاری فایل CSV"))
    }

    /** Wraps a value in quotes and escapes inner quotes (RFC 4180). */
    private fun escape(value: String): String = "\"" + value.replace("\"", "\"\"") + "\""
}
