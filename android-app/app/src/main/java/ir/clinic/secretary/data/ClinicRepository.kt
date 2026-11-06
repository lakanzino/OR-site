package ir.clinic.secretary.data

import androidx.room.withTransaction
import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.InsuranceCatalog
import ir.clinic.secretary.util.Money
import ir.clinic.secretary.util.PatientText
import ir.clinic.secretary.util.PersianDate
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

class ClinicRepository(private val db: AppDatabase) {
    val patients = db.patientDao().observeAll()
    val settings = db.settingDao().observeAll()
    val insurances = db.insuranceDao().observeAll()
    private val writeMutex = Mutex()

    suspend fun patient(id: Long): Patient? = db.patientDao().get(id)

    suspend fun savePatient(patient: Patient) = writeMutex.withLock {
        require(patient.name.isNotBlank() && patient.name.trim().length <= 200) { "نام بیمار باید بین ۱ و ۲۰۰ نویسه باشد." }
        require(patient.reason.length <= 5000) { "علت مراجعه بیش از حد طولانی است." }
        require(PatientText.normalizePhone(patient.phone) != null) { "شمارهٔ تماس معتبر نیست." }
        require(InsuranceCatalog.validName(patient.insuranceType)) { "نوع بیمه معتبر نیست." }
        require(PersianDate.parseOrNull(patient.visitDate) != null) { "تاریخ شمسی معتبر نیست." }
        require(Regex("^([01]\\d|2[0-3]):[0-5]\\d$").matches(patient.visitTime)) { "ساعت مراجعه معتبر نیست." }
        require(patient.fee >= 0 && Currency.entries.any { it.code == patient.feeCurrency } &&
            Money.rialsOf(patient.fee, Currency.fromCode(patient.feeCurrency)) != null) { "مبلغ واردشده معتبر نیست." }
        val clean = patient.copy(name = patient.name.trim(), insuranceType = patient.insuranceType.trim(), reason = patient.reason.trim())
        if (clean.id == 0L) db.patientDao().insert(clean)
        else {
            check(db.patientDao().get(clean.id) != null) { "این بیمار دیگر وجود ندارد." }
            db.patientDao().update(clean)
        }
    }

    suspend fun deletePatient(patient: Patient) = writeMutex.withLock { db.patientDao().delete(patient) }

    suspend fun saveSettings(name: String, specialty: String, feeRials: Long, currency: Currency) = writeMutex.withLock {
        require(name.length <= 200 && specialty.length <= 200 && feeRials >= 0) { "تنظیمات واردشده معتبر نیست." }
        db.settingDao().upsertAll(listOf(
            Setting(AppSettings.KEY_NAME, name), Setting(AppSettings.KEY_SPECIALTY, specialty),
            Setting(AppSettings.KEY_FEE_RIALS, feeRials.toString()), Setting(AppSettings.KEY_CURRENCY, currency.code)
        ))
    }

    suspend fun addInsurance(rawName: String) = writeMutex.withLock {
        val name = rawName.trim().replace(Regex("\\s+"), " ")
        require(InsuranceCatalog.validName(name)) { "نام بیمه باید بین ۱ و ۸۰ نویسه و بدون خط جدید باشد." }
        val existing = InsuranceCatalog.options(db.insuranceDao().getAll().map { it.name })
        require(!InsuranceCatalog.duplicate(name, existing)) { "این بیمه قبلاً در فهرست وجود دارد." }
        check(db.insuranceDao().insert(CustomInsurance(name)) != -1L) { "افزودن بیمه انجام نشد." }
    }

    /** Removing an option never rewrites or deletes historical patient records. */
    suspend fun removeInsurance(name: String) = writeMutex.withLock { db.insuranceDao().delete(name) }

    suspend fun snapshot(): ClinicSnapshot = db.withTransaction {
        ClinicSnapshot(db.patientDao().getAll(), db.settingDao().getAll(), db.insuranceDao().getAll())
    }

    suspend fun backupText(): String = ClinicBackup.encode(snapshot())

    /** Validate completely before touching live data; replacement is one atomic transaction. */
    suspend fun restore(text: String) {
        val snapshot = ClinicBackup.decode(text)
        writeMutex.withLock {
            db.withTransaction {
                db.patientDao().clear(); db.settingDao().clear(); db.insuranceDao().clear()
                db.patientDao().insertAll(snapshot.patients)
                db.settingDao().upsertAll(snapshot.settings)
                db.insuranceDao().insertAll(snapshot.insurances)
            }
        }
    }
}
