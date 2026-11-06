package ir.clinic.secretary.util

import ir.clinic.secretary.data.Patient

data class PatientFilter(
    val search: String = "",
    val from: String = "",
    val to: String = "",
    val insurance: String = ""
) {
    val active: Boolean get() = search.isNotBlank() || from.isNotBlank() || to.isNotBlank() || insurance.isNotBlank()
}

object PatientFilters {
    fun apply(patients: List<Patient>, filter: PatientFilter): List<Patient> {
        val query = PatientText.searchable(filter.search)
        val from = PersianDate.parseOrNull(filter.from)?.sortKey
        val to = PersianDate.parseOrNull(filter.to)?.sortKey
        val insurance = InsuranceCatalog.normalize(filter.insurance)
        return patients.filter { p ->
            val date = PersianDate.parseOrNull(p.visitDate)?.sortKey
            (query.isEmpty() || PatientText.searchable("${p.name} ${p.phone}").contains(query)) &&
                (from == null || (date != null && date >= from)) &&
                (to == null || (date != null && date <= to)) &&
                (insurance.isEmpty() || InsuranceCatalog.normalize(p.insuranceType) == insurance)
        }
    }
}

data class InsuranceReport(val insurance: String, val count: Int, val totalRials: Long?)
data class PatientReport(
    val count: Int,
    val totalRials: Long?,
    val supplementalCount: Int,
    val byInsurance: List<InsuranceReport>
)

object ReportCalculator {
    private fun rials(patients: List<Patient>): Long? {
        val amounts = patients.map { Money.rialsOf(it.fee, Currency.fromCode(it.feeCurrency)) ?: return null }
        return Money.totalRials(amounts)
    }

    fun calculate(patients: List<Patient>): PatientReport = PatientReport(
        count = patients.size,
        totalRials = rials(patients),
        supplementalCount = patients.count { it.hasSupplemental },
        byInsurance = patients.groupBy { it.insuranceType }.map { (insurance, rows) ->
            InsuranceReport(insurance, rows.size, rials(rows))
        }.sortedBy { it.insurance }
    )
}
