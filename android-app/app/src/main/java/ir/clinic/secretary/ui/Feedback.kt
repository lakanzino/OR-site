package ir.clinic.secretary.ui

import android.content.Context
import android.view.View
import android.widget.TextView
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.google.android.material.snackbar.Snackbar
import ir.clinic.secretary.R
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

/** Confirmation is green; warnings/destructive actions are amber. Labels repeat the color meaning. */
object Feedback {
    fun success(view: View, message: String) = snackbar(view, "تأیید: $message", true)
    fun warning(view: View, message: String) = snackbar(view, "هشدار: $message", false)

    private fun snackbar(view: View, message: String, positive: Boolean) {
        val bar = Snackbar.make(view, message, Snackbar.LENGTH_LONG)
        bar.view.findViewById<TextView>(com.google.android.material.R.id.snackbar_text)?.apply {
            maxLines = 5
            setTextColor(ContextCompat.getColor(view.context, if (positive) R.color.white else R.color.amber_text))
        }
        bar.setBackgroundTint(ContextCompat.getColor(view.context, if (positive) R.color.green_success else R.color.amber_surface))
        view.rootView.findViewById<View>(R.id.bottomNav)?.let { bar.setAnchorView(it) }
        bar.show()
    }

    fun confirm(
        context: Context, title: String, message: String, positiveLabel: String,
        destructive: Boolean = false, onConfirm: () -> Unit
    ) {
        val dialog = MaterialAlertDialogBuilder(context)
            .setTitle(title).setMessage(message)
            .setPositiveButton(positiveLabel) { _, _ -> onConfirm() }
            .setNegativeButton(R.string.cancel, null).create()
        dialog.setOnShowListener {
            dialog.getButton(android.app.AlertDialog.BUTTON_POSITIVE).setTextColor(
                ContextCompat.getColor(context, if (destructive) R.color.amber_text else R.color.green_success)
            )
        }
        dialog.show()
    }
}

/** Runs a suspend operation with a disabled button, lifecycle cancellation and visible result feedback. */
fun Fragment.runOperation(
    button: View,
    successMessage: String,
    afterSuccess: () -> Unit = {},
    operation: suspend () -> Unit
) {
    val target = requireView()
    button.isEnabled = false
    viewLifecycleOwner.lifecycleScope.launch {
        try {
            operation()
            Feedback.success(target, successMessage)
            afterSuccess()
        } catch (e: CancellationException) {
            throw e
        } catch (e: IllegalArgumentException) {
            Feedback.warning(target, e.message ?: getString(R.string.operation_failed))
        } catch (e: IllegalStateException) {
            Feedback.warning(target, e.message ?: getString(R.string.operation_failed))
        } catch (_: Exception) {
            Feedback.warning(target, getString(R.string.operation_failed))
        } finally {
            button.isEnabled = true
        }
    }
}
