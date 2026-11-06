package ir.clinic.secretary.ui

import android.net.Uri
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.fragment.app.activityViewModels
import ir.clinic.secretary.MainViewModel
import ir.clinic.secretary.R
import ir.clinic.secretary.databinding.FragmentSettingsBinding
import ir.clinic.secretary.databinding.ItemCustomInsuranceBinding
import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.InsuranceCatalog
import ir.clinic.secretary.util.Money
import ir.clinic.secretary.util.PersianDate

class SettingsFragment : Fragment() {
    private var _b: FragmentSettingsBinding? = null
    private val b get() = _b!!
    private val vm: MainViewModel by activityViewModels()
    private var currentCurrency = Currency.TOMAN
    private var bindingCurrency = false
    private var pendingBackupUri: Uri? = null
    private var pendingRestoreUri: Uri? = null
    private var pendingCsvUri: Uri? = null

    private val createBackup = registerForActivityResult(ActivityResultContracts.CreateDocument("application/json")) { uri ->
        if (uri != null) {
            pendingBackupUri = uri
            Feedback.confirm(requireContext(), getString(R.string.backup), getString(R.string.export_privacy_warning), getString(R.string.export)) {
                val target = pendingBackupUri ?: return@confirm
                runOperation(b.btnBackup, getString(R.string.msg_backup_exported)) { vm.exportBackup(requireContext(), target) }
            }
        }
    }
    private val openBackup = registerForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        if (uri != null) {
            pendingRestoreUri = uri
            Feedback.confirm(requireContext(), getString(R.string.restore), getString(R.string.restore_warning), getString(R.string.restore), destructive = true) {
                val source = pendingRestoreUri ?: return@confirm
                runOperation(b.btnRestore, getString(R.string.msg_restored)) { vm.restoreBackup(requireContext(), source) }
            }
        }
    }
    private val createCsv = registerForActivityResult(ActivityResultContracts.CreateDocument("text/csv")) { uri ->
        if (uri != null) {
            pendingCsvUri = uri
            Feedback.confirm(requireContext(), getString(R.string.export_csv), getString(R.string.export_privacy_warning), getString(R.string.export)) {
                val target = pendingCsvUri ?: return@confirm
                runOperation(b.btnExportCsv, getString(R.string.msg_csv_exported)) { vm.exportCsv(requireContext(), target, false) }
            }
        }
    }

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentSettingsBinding.inflate(i, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        b.tvDefaultInsurances.text = InsuranceCatalog.DEFAULTS.joinToString("، ")
        b.currencyGroup.setOnCheckedChangeListener { _, checkedId ->
            if (!bindingCurrency) {
                val next = if (checkedId == R.id.rbRial) Currency.RIAL else Currency.TOMAN
                val rials = Money.parseToRials(b.etDefaultFee.text?.toString(), currentCurrency)
                if (rials == null) {
                    Feedback.warning(b.root, getString(R.string.err_fee))
                    bindingCurrency = true
                    b.currencyGroup.check(if (currentCurrency == Currency.RIAL) R.id.rbRial else R.id.rbToman)
                    bindingCurrency = false
                } else {
                    currentCurrency = next
                    b.tilDefaultFee.suffixText = next.label
                    b.etDefaultFee.setText(Money.inputAmount(rials, next))
                }
            }
        }
        vm.settings.observe(viewLifecycleOwner) { s ->
            bindingCurrency = true
            currentCurrency = s.currency
            b.currencyGroup.check(if (s.currency == Currency.RIAL) R.id.rbRial else R.id.rbToman)
            b.tilDefaultFee.suffixText = s.currency.label
            b.etDoctorName.setText(s.doctorName)
            b.etSpecialty.setText(s.specialty)
            b.etDefaultFee.setText(Money.inputAmount(s.defaultFeeRials, s.currency))
            bindingCurrency = false
        }
        vm.insurances.observe(viewLifecycleOwner) { custom ->
            b.customInsuranceRows.removeAllViews()
            b.tvNoCustomInsurance.visibility = if (custom.isEmpty()) View.VISIBLE else View.GONE
            custom.forEach { item ->
                val row = ItemCustomInsuranceBinding.inflate(layoutInflater, b.customInsuranceRows, false)
                row.tvInsurance.text = item.name
                row.btnDelete.setOnClickListener {
                    Feedback.confirm(requireContext(), getString(R.string.delete_insurance), getString(R.string.delete_insurance_warning, item.name), getString(R.string.delete), destructive = true) {
                        runOperation(row.btnDelete, getString(R.string.msg_insurance_removed)) { vm.removeInsurance(item.name) }
                    }
                }
                b.customInsuranceRows.addView(row.root)
            }
        }
        b.btnAddInsurance.setOnClickListener {
            val name = b.etNewInsurance.text?.toString()?.trim().orEmpty()
            runOperation(b.btnAddInsurance, getString(R.string.msg_insurance_added), afterSuccess = { b.etNewInsurance.text?.clear() }) {
                vm.addInsurance(name)
            }
        }
        b.btnSaveSettings.setOnClickListener {
            val fee = Money.parseToRials(b.etDefaultFee.text?.toString(), currentCurrency)
            b.tilDefaultFee.error = if (fee == null) getString(R.string.err_fee) else null
            val name = b.etDoctorName.text?.toString()?.trim().orEmpty()
            val specialty = b.etSpecialty.text?.toString()?.trim().orEmpty()
            if (fee == null) { Feedback.warning(b.root, getString(R.string.fix_form)); return@setOnClickListener }
            if (name.length > 200 || specialty.length > 200) { Feedback.warning(b.root, getString(R.string.err_settings_length)); return@setOnClickListener }
            runOperation(b.btnSaveSettings, getString(R.string.msg_settings_saved)) { vm.saveSettings(name, specialty, fee, currentCurrency) }
        }
        b.btnBackup.setOnClickListener { createBackup.launch("clinic-backup-${PersianDate.today().format().replace('/', '-')}.json") }
        b.btnRestore.setOnClickListener { openBackup.launch(arrayOf("application/json", "text/plain", "application/octet-stream")) }
        b.btnExportCsv.setOnClickListener { createCsv.launch("clinic-patients-${PersianDate.today().format().replace('/', '-')}.csv") }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
