package ir.clinic.secretary.ui

import android.content.Context
import android.view.LayoutInflater
import androidx.core.content.ContextCompat
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import ir.clinic.secretary.R
import ir.clinic.secretary.databinding.DialogShamsiDateBinding
import ir.clinic.secretary.util.Fa
import ir.clinic.secretary.util.PersianDate

/** Small offline Shamsi picker; validates leap years before returning a date. */
object ShamsiDatePicker {
    fun show(context: Context, initial: String, onSelected: (String) -> Unit) {
        val b = DialogShamsiDateBinding.inflate(LayoutInflater.from(context))
        val date = PersianDate.parseOrNull(initial) ?: PersianDate.today()
        b.etYear.setText(date.year.toString())
        b.etMonth.setText(date.month.toString())
        b.etDay.setText(date.day.toString())
        val dialog = MaterialAlertDialogBuilder(context)
            .setTitle(R.string.pick_shamsi_date).setView(b.root)
            .setPositiveButton(R.string.confirm, null)
            .setNeutralButton(R.string.today, null)
            .setNegativeButton(R.string.cancel, null).create()
        dialog.setOnShowListener {
            dialog.getButton(android.app.AlertDialog.BUTTON_POSITIVE).apply {
                setTextColor(ContextCompat.getColor(context, R.color.green_success))
                setOnClickListener {
                    val y = Fa.normalize(b.etYear.text?.toString().orEmpty()).trim()
                    val m = Fa.normalize(b.etMonth.text?.toString().orEmpty()).trim()
                    val d = Fa.normalize(b.etDay.text?.toString().orEmpty()).trim()
                    val picked = PersianDate.parseOrNull("$y/$m/$d")
                    if (picked == null) {
                        b.tvDateWarning.text = context.getString(R.string.invalid_shamsi_date)
                        b.tvDateWarning.visibility = android.view.View.VISIBLE
                    } else {
                        onSelected(picked.format())
                        dialog.dismiss()
                    }
                }
            }
            dialog.getButton(android.app.AlertDialog.BUTTON_NEUTRAL).setOnClickListener {
                onSelected(PersianDate.today().format())
                dialog.dismiss()
            }
        }
        dialog.show()
    }
}
