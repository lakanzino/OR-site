package ir.clinic.secretary

import ir.clinic.secretary.data.Patient
import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.CsvExport
import ir.clinic.secretary.util.PatientFilter
import ir.clinic.secretary.util.PatientFilters
import ir.clinic.secretary.util.ReportCalculator
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ReportsAndCsvTest {
    private fun patient(id: Long, name: String = "بیمار", insurance: String = "آزاد", fee: Long = 100, currency: String = "TOMAN", date: String = "1403/05/12") =
        Patient(id = id, name = name, insuranceType = insurance, hasSupplemental = id % 2 == 0L, reason = "", visitTime = "10:00", visitDate = date, fee = fee, feeCurrency = currency)

    @Test fun searchNormalizesPersianArabicAndPhone() {
        val rows = listOf(patient(1, name = "علي رضايي"), patient(2, name = "علی رضایی"), patient(3, name = "سارا"))
        assertEquals(listOf(1L, 2L), PatientFilters.apply(rows, PatientFilter(search = "علی رضایی")).map { it.id })
        val withPhone = listOf(patient(1).copy(phone = "۰۹۱۲۳۴۵۶۷۸۹"))
        assertEquals(1, PatientFilters.apply(withPhone, PatientFilter(search = "0912")).size)
    }
    @Test fun dateRangeIsInclusiveAndInsuranceNormalized() {
        val rows = listOf(patient(1, date = "1403/05/11"), patient(2, date = "1403/05/12"), patient(3, date = "1403/05/13"))
        assertEquals(listOf(2L), PatientFilters.apply(rows, PatientFilter(from = "1403/05/12", to = "1403/05/12")).map { it.id })
        assertEquals(0, PatientFilters.apply(rows, PatientFilter(from = "1403/05/13", to = "1403/05/12")).size)
        assertEquals(1, PatientFilters.apply(listOf(patient(1, insurance = "تامين اجتماعي")), PatientFilter(insurance = "تامین اجتماعی")).size)
    }
    @Test fun reportConvertsMixedCurrenciesToRials() {
        val report = ReportCalculator.calculate(listOf(patient(1, fee = 100, currency = "TOMAN"), patient(2, fee = 500, currency = "RIAL")))
        assertEquals(2, report.count)
        assertEquals(1500L, report.totalRials)
        assertEquals(1, report.supplementalCount)
        assertEquals(1500L, report.byInsurance.single().totalRials)
    }
    @Test fun reportOverflowIsExplicit() {
        val report = ReportCalculator.calculate(listOf(patient(1, fee = Long.MAX_VALUE, currency = "RIAL"), patient(2, fee = 1, currency = "RIAL")))
        assertNull(report.totalRials)
        assertNull(report.byInsurance.single().totalRials)
    }
    @Test fun csvHasBomCrLfQuotesAndFormulaProtection() {
        val csv = CsvExport.build(listOf(patient(1, name = "=HYPERLINK(\"x\")").copy(reason = "خط اول\nخط دوم", phone = "+989123456789")), Currency.TOMAN)
        assertTrue(csv.startsWith("\uFEFF\"شناسه\""))
        assertTrue(csv.contains("\r\n"))
        assertTrue(csv.contains("\"'=HYPERLINK(\"\"x\"\")\""))
        assertTrue(csv.contains("\"'+989123456789\""))
        assertTrue(csv.contains("خط اول\nخط دوم"))
    }
}
