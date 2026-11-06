package ir.clinic.secretary.ui

import android.app.TimePickerDialog
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ArrayAdapter
import androidx.fragment.app.Fragment
import androidx.fragment.app.activityViewModels
import ir.clinic.secretary.MainActivity
import ir.clinic.secretary.MainViewModel
import ir.clinic.secretary.R
import ir.clinic.secretary.data.AppSettings
import ir.clinic.secretary.data.Patient
import ir.clinic.secretary.databinding.FragmentRegisterBinding
import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.Fa
import ir.clinic.secretary.util.InsuranceCatalog
import ir.clinic.secretary.util.Money
import ir.clinic.secretary.util.PatientText
import ir.clinic.secretary.util.PersianDate
import java.util.Calendar
import java.util.Locale

class RegisterFragment : Fragment() {
    private var _b: FragmentRegisterBinding? = null
    private val b get() = _b!!
    private val vm: MainViewModel by activityViewModels()
    private var editing: Patient? = null
    private var hour = 0
    private var minute = 0
    private var visitDate = PersianDate.today().format()
    private var currency = Currency.TOMAN
    private var defaultFeeRials = 0L
    private var defaultFeeApplied = false
    private var editingFeeApplied = false
    private var feeTouched = false
    private var settingFee = false
    private var options: List<String> = InsuranceCatalog.DEFAULTS

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentRegisterBinding.inflate(i, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        editing = vm.editingPatient
        b.tvFormTitle.setText(if (editing == null) R.string.title_register else R.string.title_edit)
        b.btnSave.setText(if (editing == null) R.string.btn_save else R.string.btn_update)
        b.btnCancelEdit.visibility = if (editing == null) View.GONE else View.VISIBLE
        b.btnCancelEdit.setOnClickListener {
            vm.cancelEdit()
            (activity as? MainActivity)?.restartRegister()
        }
        renderInsuranceOptions(emptyList())
        vm.insurances.observe(viewLifecycleOwner) { custom -> renderInsuranceOptions(custom.map { it.name }) }

        b.etFee.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) = Unit
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                if (!settingFee) feeTouched = true
            }
            override fun afterTextChanged(s: Editable?) = Unit
        })
        vm.settings.observe(viewLifecycleOwner) { s ->
            currency = s.currency
            defaultFeeRials = s.defaultFeeRials
            b.tilFee.suffixText = s.currency.label
            if (editing == null && (!defaultFeeApplied || !feeTouched)) {
                setFeeText(Money.inputAmount(s.defaultFeeRials, s.currency))
                defaultFeeApplied = true
            } else if (editing != null && !editingFeeApplied) {
                val rials = Money.rialsOf(editing!!.fee, Currency.fromCode(editing!!.feeCurrency)) ?: 0L
                setFeeText(Money.inputAmount(rials, s.currency))
                editingFeeApplied = true
            }
        }

        editing?.let { p ->
            b.etName.setText(p.name)
            b.etPhone.setText(p.phone)
            b.cbSupplemental.isChecked = p.hasSupplemental
            b.etReason.setText(p.reason)
            visitDate = p.visitDate
            val parts = p.visitTime.split(":")
            hour = parts.getOrNull(0)?.toIntOrNull() ?: 0
            minute = parts.getOrNull(1)?.toIntOrNull() ?: 0
            val rials = Money.rialsOf(p.fee, Currency.fromCode(p.feeCurrency)) ?: 0L
            setFeeText(Money.inputAmount(rials, vm.settings.value?.currency ?: Currency.TOMAN))
            renderInsuranceOptions(currentCustomOptions(), p.insuranceType)
        } ?: run {
            val now = Calendar.getInstance()
            hour = now.get(Calendar.HOUR_OF_DAY)
            minute = now.get(Calendar.MINUTE)
            if (vm.settings.value == null) setFeeText("0")
        }
        showTime()
        showDate()

        b.etTime.setOnClickListener {
            TimePickerDialog(requireContext(), { _, h, m -> hour = h; minute = m; showTime() }, hour, minute, true).show()
        }
        b.etDate.setOnClickListener {
            ShamsiDatePicker.show(requireContext(), visitDate) { picked -> visitDate = picked; showDate() }
        }
        b.btnSave.setOnClickListener { save() }
    }

    private fun currentCustomOptions(): List<String> = vm.insurances.value?.map { it.name } ?: emptyList()

    private fun renderInsuranceOptions(custom: List<String>, preferred: String? = null) {
        val selected = preferred ?: b.spInsurance.selectedItem as? String
        options = InsuranceCatalog.options(custom).toMutableList().apply {
            if (selected != null && selected !in this && InsuranceCatalog.validName(selected)) add(selected)
        }
        val adapter = ArrayAdapter(requireContext(), android.R.layout.simple_spinner_item, options)
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        b.spInsurance.adapter = adapter
        b.spInsurance.setSelection((selected?.let { options.indexOf(it) } ?: 0).coerceAtLeast(0))
    }

    private fun setFeeText(value: String) {
        settingFee = true
        b.etFee.setText(value)
        settingFee = false
    }

    private fun save() {
        val name = b.etName.text?.toString()?.trim().orEmpty()
        val phone = PatientText.normalizePhone(b.etPhone.text?.toString().orEmpty())
        val feeRials = Money.parseToRials(b.etFee.text?.toString(), currency)
        b.tilName.error = if (name.isEmpty() || name.length > 200) getString(R.string.err_name_required) else null
        b.tilPhone.error = if (phone == null) getString(R.string.err_phone) else null
        b.tilFee.error = if (feeRials == null) getString(R.string.err_fee) else null
        if (name.isEmpty() || name.length > 200 || phone == null || feeRials == null) {
            Feedback.warning(b.root, getString(R.string.fix_form))
            return
        }
        val insurance = b.spInsurance.selectedItem as? String
        if (insurance == null) { Feedback.warning(b.root, getString(R.string.err_insurance)); return }
        val patient = Patient(
            id = editing?.id ?: 0L, name = name, insuranceType = insurance,
            hasSupplemental = b.cbSupplemental.isChecked, reason = b.etReason.text?.toString()?.trim().orEmpty(),
            visitTime = String.format(Locale.US, "%02d:%02d", hour, minute), visitDate = visitDate,
            fee = feeRials, phone = phone, feeCurrency = Currency.RIAL.code
        )
        val wasEditing = editing != null
        runOperation(b.btnSave, getString(if (wasEditing) R.string.msg_updated else R.string.msg_saved), afterSuccess = {
            if (wasEditing) (activity as? MainActivity)?.restartRegister() else clearForm()
        }) { vm.savePatient(patient) }
    }

    private fun clearForm() {
        b.etName.text?.clear(); b.etPhone.text?.clear(); b.etReason.text?.clear()
        b.cbSupplemental.isChecked = false
        b.spInsurance.setSelection(0)
        setFeeText(Money.inputAmount(defaultFeeRials, currency))
        feeTouched = false
        visitDate = PersianDate.today().format(); showDate()
        val now = Calendar.getInstance()
        hour = now.get(Calendar.HOUR_OF_DAY); minute = now.get(Calendar.MINUTE); showTime()
        b.tilName.error = null; b.tilPhone.error = null; b.tilFee.error = null
        b.etName.requestFocus()
    }

    private fun showTime() { b.etTime.setText(Fa.digits(String.format(Locale.US, "%02d:%02d", hour, minute))) }
    private fun showDate() { b.etDate.setText(Fa.digits(visitDate)) }
    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
