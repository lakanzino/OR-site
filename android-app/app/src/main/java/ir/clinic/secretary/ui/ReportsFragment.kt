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
import ir.clinic.secretary.data.Patient
import ir.clinic.secretary.databinding.FragmentReportsBinding
import ir.clinic.secretary.databinding.ItemInsuranceReportBinding
import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.Fa
import ir.clinic.secretary.util.Money
import ir.clinic.secretary.util.PatientFilter
import ir.clinic.secretary.util.PersianDate
import ir.clinic.secretary.util.ReportCalculator

class ReportsFragment : Fragment() {
    private var _b: FragmentReportsBinding? = null
    private val b get() = _b!!
    private val vm: MainViewModel by activityViewModels()
    private var rows: List<Patient> = emptyList()
    private var currency = Currency.TOMAN
    private var exportUri: Uri? = null
    private val createCsv = registerForActivityResult(ActivityResultContracts.CreateDocument("text/csv")) { uri ->
        if (uri != null) {
            exportUri = uri
            Feedback.confirm(requireContext(), getString(R.string.export_csv), getString(R.string.export_privacy_warning), getString(R.string.export)) {
                val target = exportUri ?: return@confirm
                runOperation(b.btnExportCsv, getString(R.string.msg_csv_exported)) { vm.exportCsv(requireContext(), target, true) }
            }
        }
    }

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentReportsBinding.inflate(i, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        b.btnToday.setOnClickListener { vm.filter.value = vm.filter.value.copy(from = PersianDate.today().format(), to = PersianDate.today().format()) }
        b.btnWeek.setOnClickListener { vm.filter.value = vm.filter.value.copy(from = PersianDate.daysAgo(6).format(), to = PersianDate.today().format()) }
        b.btnMonth.setOnClickListener { vm.filter.value = vm.filter.value.copy(from = PersianDate.daysAgo(29).format(), to = PersianDate.today().format()) }
        b.btnAllDates.setOnClickListener { vm.filter.value = vm.filter.value.copy(from = "", to = "") }
        b.btnFrom.setOnClickListener { ShamsiDatePicker.show(requireContext(), vm.filter.value.from) { vm.filter.value = vm.filter.value.copy(from = it) } }
        b.btnTo.setOnClickListener { ShamsiDatePicker.show(requireContext(), vm.filter.value.to) { vm.filter.value = vm.filter.value.copy(to = it) } }
        b.btnClearFilters.setOnClickListener { vm.filter.value = PatientFilter() }
        b.btnExportCsv.setOnClickListener { createCsv.launch("clinic-patients-${PersianDate.today().format().replace('/', '-')}.csv") }
        vm.filterState.observe(viewLifecycleOwner) { f ->
            b.btnFrom.text = if (f.from.isBlank()) getString(R.string.from_date) else Fa.digits(f.from)
            b.btnTo.text = if (f.to.isBlank()) getString(R.string.to_date) else Fa.digits(f.to)
            b.tvSearchFilter.text = if (f.search.isBlank() && f.insurance.isBlank()) getString(R.string.report_all_filters)
                else getString(R.string.report_active_filters, f.search.ifBlank { "—" }, f.insurance.ifBlank { getString(R.string.all_insurances) })
            val from = PersianDate.parseOrNull(f.from)
            val to = PersianDate.parseOrNull(f.to)
            b.tvRangeWarning.visibility = if (from != null && to != null && from.sortKey > to.sortKey) View.VISIBLE else View.GONE
            render()
        }
        vm.settings.observe(viewLifecycleOwner) { currency = it.currency; render() }
        vm.filteredPatients.observe(viewLifecycleOwner) { rows = it; render() }
    }

    private fun render() {
        val report = ReportCalculator.calculate(rows)
        b.tvCount.text = Fa.digits(report.count.toString())
        b.tvTotal.text = report.totalRials?.let { Money.format(it, currency) } ?: getString(R.string.amount_overflow)
        b.tvSupplemental.text = Fa.digits(report.supplementalCount.toString())
        b.insuranceRows.removeAllViews()
        report.byInsurance.forEach { item ->
            val row = ItemInsuranceReportBinding.inflate(layoutInflater, b.insuranceRows, false)
            row.tvInsurance.text = item.insurance
            row.tvCount.text = getString(R.string.patient_count, Fa.digits(item.count.toString()))
            row.tvTotal.text = item.totalRials?.let { Money.format(it, currency) } ?: getString(R.string.amount_overflow)
            b.insuranceRows.addView(row.root)
        }
        b.tvNoInsurance.visibility = if (report.byInsurance.isEmpty()) View.VISIBLE else View.GONE
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
