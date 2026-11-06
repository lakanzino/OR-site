package ir.clinic.secretary

import ir.clinic.secretary.data.AppSettings
import ir.clinic.secretary.data.ClinicBackup
import ir.clinic.secretary.data.ClinicSnapshot
import ir.clinic.secretary.data.CustomInsurance
import ir.clinic.secretary.data.Patient
import ir.clinic.secretary.data.Setting
import org.junit.Assert.assertEquals
import org.junit.Assert.assertThrows
import org.junit.Test

class ClinicBackupTest {
    private val snapshot = ClinicSnapshot(
        patients = listOf(Patient(1, "بیمار نمونه", "آزاد", true, "علت", "10:30", "1403/05/12", 1500, "09123456789", "RIAL")),
        settings = listOf(Setting(AppSettings.KEY_CURRENCY, "RIAL"), Setting(AppSettings.KEY_FEE_RIALS, "1500")),
        insurances = listOf(CustomInsurance("بیمه نمونه"))
    )
    @Test fun roundTripPreservesAllTables() {
        assertEquals(snapshot, ClinicBackup.decode(ClinicBackup.encode(snapshot)))
    }
    @Test fun rejectsWrongFormatAndVersion() {
        assertThrows(IllegalArgumentException::class.java) { ClinicBackup.decode("{}") }
        val text = ClinicBackup.encode(snapshot).replace("\"schemaVersion\": 2", "\"schemaVersion\": 99")
        assertThrows(IllegalArgumentException::class.java) { ClinicBackup.decode(text) }
    }
    @Test fun rejectsDuplicateIdsAndInvalidAmounts() {
        val duplicate = snapshot.copy(patients = snapshot.patients + snapshot.patients)
        assertThrows(IllegalArgumentException::class.java) { ClinicBackup.decode(ClinicBackup.encode(duplicate)) }
        val negative = snapshot.copy(patients = snapshot.patients.map { it.copy(fee = -1) })
        assertThrows(IllegalArgumentException::class.java) { ClinicBackup.decode(ClinicBackup.encode(negative)) }
    }
    @Test fun rejectsMalformedRecordsWithoutPlatformException() {
        assertThrows(IllegalArgumentException::class.java) { ClinicBackup.decode("{\"format\":\"clinic-secretary-backup\",\"schemaVersion\":2,\"patients\":[{}],\"settings\":[],\"customInsurances\":[]}") }
    }
}
