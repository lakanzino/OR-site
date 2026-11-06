package ir.clinic.secretary.ui

import android.content.Context
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.AdapterView
import android.widget.ArrayAdapter
import androidx.fragment.app.Fragment
import androidx.fragment.app.activityViewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.google.android.material.chip.Chip
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import ir.clinic.secretary.MainActivity
import ir.clinic.secretary.MainViewModel
import ir.clinic.secretary.R
import ir.clinic.secretary.data.Patient
import ir.clinic.secretary.databinding.FragmentPatientsBinding
import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.Fa
import ir.clinic.secretary.util.InsuranceCatalog
import ir.clinic.secretary.util.Money
import ir.clinic.secretary.util.PatientFilter
import ir.clinic.secretary.util.PersianDate
import ir.clinic.secretary.util.ReportCalculator

class PatientsFragment : Fragment() {
    private var _b: FragmentPatientsBinding? = null
    private val b get() = _b!!
    private val vm: MainViewModel by activityViewModels()
    private lateinit var adapter: PatientAdapter
    private val prefs by lazy { requireContext().getSharedPreferences("ui", Context.MODE_PRIVATE) }
    private var insuranceOptions: List<String> = emptyList()
    private var bindingSpinner = false
    private var watcher: TextWatcher? = null

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentPatientsBinding.inflate(i, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        adapter = PatientAdapter(vm.settings.value?.currency ?: Currency.TOMAN) { showDetails(it) }
        b.recycler.layoutManager = LinearLayoutManager(requireContext())
        b.recycler.adapter = adapter
        setupChips()
        b.etSearch.setText(vm.filter.value.search)
        watcher = object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) = Unit
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                vm.filter.value = vm.filter.value.copy(search = s?.toString().orEmpty())
            }
            override fun afterTextChanged(s: Editable?) = Unit
        }
        b.etSearch.addTextChangedListener(watcher)
        b.btnClearFilters.setOnClickListener { vm.filter.value = PatientFilter() }
        b.btnFrom.setOnClickListener {
            ShamsiDatePicker.show(requireContext(), vm.filter.value.from) { vm.filter.value = vm.filter.value.copy(from = it) }
        }
        b.btnTo.setOnClickListener {
            ShamsiDatePicker.show(requireContext(), vm.filter.value.to) { vm.filter.value = vm.filter.value.copy(to = it) }
        }
        b.spFilterInsurance.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: View?, position: Int, id: Long) {
                if (!bindingSpinner) vm.filter.value = vm.filter.value.copy(insurance = insuranceOptions.getOrNull(position).orEmpty())
            }
            override fun onNothingSelected(parent: AdapterView<*>?) = Unit
        }
        vm.insurances.observe(viewLifecycleOwner) { renderInsuranceFilter() }
        vm.filterState.observe(viewLifecycleOwner) { f ->
            if (b.etSearch.text?.toString() != f.search) b.etSearch.setText(f.search)
            b.btnFrom.text = if (f.from.isBlank()) getString(R.string.from_date) else Fa.digits(f.from)
            b.btnTo.text = if (f.to.isBlank()) getString(R.string.to_date) else Fa.digits(f.to)
            b.btnClearFilters.visibility = if (f.active) View.VISIBLE else View.GONE
            val from = PersianDate.parseOrNull(f.from)
            val to = PersianDate.parseOrNull(f.to)
            b.tvRangeWarning.visibility = if (from != null && to != null && from.sortKey > to.sortKey) View.VISIBLE else View.GONE
            renderInsuranceFilter()
        }
        vm.settings.observe(viewLifecycleOwner) { s ->
            adapter.currency = s.currency
            renderSummary()
        }
        vm.filteredPatients.observe(viewLifecycleOwner) { list ->
            adapter.submitList(list)
            b.tvEmpty.visibility = if (list.isEmpty()) View.VISIBLE else View.GONE
            b.tvEmpty.setText(if (vm.filter.value.active) R.string.no_matching_patients else R.string.empty_list)
            renderSummary()
        }
    }

    private fun renderInsuranceFilter() {
        val current = vm.filter.value.insurance
        insuranceOptions = listOf("") + InsuranceCatalog.options(vm.insurances.value?.map { it.name } ?: emptyList()) +
            (if (current.isNotBlank() && current !in InsuranceCatalog.options(vm.insurances.value?.map { it.name } ?: emptyList())) listOf(current) else emptyList())
        bindingSpinner = true
        b.spFilterInsurance.adapter = ArrayAdapter(requireContext(), android.R.layout.simple_spinner_item,
            insuranceOptions.map { it.ifBlank { getString(R.string.all_insurances) } }).apply {
            setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        }
        b.spFilterInsurance.setSelection(insuranceOptions.indexOf(current).coerceAtLeast(0))
        bindingSpinner = false
    }

    private fun renderSummary() {
        val currency = vm.settings.value?.currency ?: Currency.TOMAN
        val report = ReportCalculator.calculate(vm.filteredPatients.value ?: emptyList())
        b.tvSummary.text = getString(R.string.summary, Fa.digits(report.count.toString()),
            report.totalRials?.let { Money.format(it, currency) } ?: getString(R.string.amount_overflow))
    }

    private fun setupChips() {
        val chips: Map<Chip, String> = mapOf(
            b.chipName to Columns.NAME, b.chipInsurance to Columns.INSURANCE, b.chipSupp to Columns.SUPPLEMENTAL,
            b.chipReason to Columns.REASON, b.chipTime to Columns.TIME, b.chipDate to Columns.DATE, b.chipFee to Columns.FEE
        )
        val saved = prefs.getStringSet("visible_columns", Columns.ALL) ?: Columns.ALL
        chips.forEach { (chip, key) ->
            chip.isChecked = key in saved
            chip.setOnCheckedChangeListener { _, _ ->
                val now = chips.filterKeys { it.isChecked }.values.toSet()
                prefs.edit().putStringSet("visible_columns", now).apply()
                applyColumns(now)
            }
        }
        applyColumns(chips.filterKeys { it.isChecked }.values.toSet())
    }

    private fun applyColumns(v: Set<String>) {
        fun vis(k: String) = if (k in v) View.VISIBLE else View.GONE
        b.header.hName.visibility = vis(Columns.NAME)
        b.header.hInsurance.visibility = vis(Columns.INSURANCE)
        b.header.hSupp.visibility = vis(Columns.SUPPLEMENTAL)
        b.header.hReason.visibility = vis(Columns.REASON)
        b.header.hTime.visibility = vis(Columns.TIME)
        b.header.hDate.visibility = vis(Columns.DATE)
        b.header.hFee.visibility = vis(Columns.FEE)
        adapter.visibleColumns = v
    }

    private fun showDetails(p: Patient) {
        val currency = vm.settings.value?.currency ?: Currency.TOMAN
        val rials = Money.rialsOf(p.fee, Currency.fromCode(p.feeCurrency))
        val msg = buildString {
            if (p.phone.isNotBlank()) append("${getString(R.string.phone)}: ${Fa.digits(p.phone)}\n")
            append("${getString(R.string.col_insurance)}: ${p.insuranceType}\n")
            append("${getString(R.string.col_supp)}: ${getString(if (p.hasSupplemental) R.string.yes else R.string.no)}\n")
            append("${getString(R.string.col_reason)}: ${p.reason.ifBlank { "—" }}\n")
            append("${getString(R.string.col_time)}: ${Fa.digits(p.visitTime)}\n")
            append("${getString(R.string.col_date)}: ${Fa.digits(p.visitDate)}\n")
            append("${getString(R.string.col_fee)}: ${rials?.let { Money.format(it, currency) } ?: getString(R.string.amount_overflow)}")
        }
        val dialog = MaterialAlertDialogBuilder(requireContext()).setTitle(p.name).setMessage(msg)
            .setPositiveButton(R.string.edit, null)
            .setNeutralButton(R.string.delete, null)
            .setNegativeButton(R.string.close, null).create()
        dialog.setOnShowListener {
            dialog.getButton(android.app.AlertDialog.BUTTON_POSITIVE).setTextColor(resources.getColor(R.color.green_success, null))
            dialog.getButton(android.app.AlertDialog.BUTTON_NEUTRAL).setTextColor(resources.getColor(R.color.amber_text, null))
            dialog.getButton(android.app.AlertDialog.BUTTON_POSITIVE).setOnClickListener {
                dialog.dismiss(); (activity as? MainActivity)?.editPatient(p)
            }
            dialog.getButton(android.app.AlertDialog.BUTTON_NEUTRAL).setOnClickListener {
                dialog.dismiss()
                Feedback.confirm(requireContext(), getString(R.string.delete), getString(R.string.confirm_delete), getString(R.string.delete), destructive = true) {
                    runOperation(b.tvSummary, getString(R.string.msg_deleted)) { vm.deletePatient(p) }
                }
            }
        }
        dialog.show()
    }

    override fun onDestroyView() {
        watcher?.let { b.etSearch.removeTextChangedListener(it) }
        super.onDestroyView(); _b = null
    }
}
