package ir.clinic.secretary

import android.app.Application
import android.content.Context
import android.net.Uri
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.LiveData
import androidx.lifecycle.asLiveData
import ir.clinic.secretary.data.AppDatabase
import ir.clinic.secretary.data.AppSettings
import ir.clinic.secretary.data.ClinicBackup
import ir.clinic.secretary.data.ClinicRepository
import ir.clinic.secretary.data.Patient
import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.CsvExport
import ir.clinic.secretary.util.PatientFilter
import ir.clinic.secretary.util.PatientFilters
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream

/** Shared state survives navigation and rotation. Writes finish before the UI shows confirmation. */
class MainViewModel(app: Application) : AndroidViewModel(app) {
    private val repository = ClinicRepository(AppDatabase.get(app))
    val patients: LiveData<List<Patient>> = repository.patients.asLiveData()
    val settings: LiveData<AppSettings> = repository.settings
        .map { rows -> AppSettings.fromMap(rows.associate { it.key to it.value }) }.asLiveData()
    val insurances = repository.insurances.asLiveData()
    val filter = MutableStateFlow(PatientFilter())
    val filterState: LiveData<PatientFilter> = filter.asLiveData()
    val filteredPatients: LiveData<List<Patient>> = combine(repository.patients, filter) { rows, f ->
        PatientFilters.apply(rows, f)
    }.asLiveData()
    var editingPatient: Patient? = null
        private set

    fun beginEdit(patient: Patient) { editingPatient = patient }
    fun cancelEdit() { editingPatient = null }
    suspend fun patient(id: Long): Patient? = repository.patient(id)
    suspend fun savePatient(patient: Patient) { repository.savePatient(patient); editingPatient = null }
    suspend fun deletePatient(patient: Patient) = repository.deletePatient(patient)
    suspend fun saveSettings(name: String, specialty: String, feeRials: Long, currency: Currency) =
        repository.saveSettings(name, specialty, feeRials, currency)
    suspend fun addInsurance(name: String) = repository.addInsurance(name)
    suspend fun removeInsurance(name: String) = repository.removeInsurance(name)

    suspend fun exportCsv(context: Context, uri: Uri, onlyFiltered: Boolean) = withContext(Dispatchers.IO) {
        val rows = if (onlyFiltered) PatientFilters.apply(repository.snapshot().patients, filter.value) else repository.snapshot().patients
        val currency = settings.value ?: AppSettings()
        writeUri(context, uri, CsvExport.build(rows, currency.currency).toByteArray(Charsets.UTF_8))
    }

    suspend fun exportBackup(context: Context, uri: Uri) = withContext(Dispatchers.IO) {
        val bytes = repository.backupText().toByteArray(Charsets.UTF_8)
        require(bytes.size <= ClinicBackup.MAX_BYTES) { "حجم پشتیبان از حد مجاز بیشتر است؛ فایل ساخته نشد." }
        writeUri(context, uri, bytes)
    }

    suspend fun restoreBackup(context: Context, uri: Uri) = withContext(Dispatchers.IO) {
        val input = context.contentResolver.openInputStream(uri) ?: error("فایل قابل خواندن نیست.")
        val bytes = input.use {
            val output = ByteArrayOutputStream()
            val buffer = ByteArray(8192)
            while (true) {
                val count = it.read(buffer)
                if (count < 0) break
                require(output.size() + count <= ClinicBackup.MAX_BYTES) { "فایل پشتیبان بیش از حد بزرگ است." }
                output.write(buffer, 0, count)
            }
            output.toByteArray()
        }
        repository.restore(bytes.toString(Charsets.UTF_8))
        editingPatient = null
        filter.value = PatientFilter()
    }

    private fun writeUri(context: Context, uri: Uri, bytes: ByteArray) {
        val output = context.contentResolver.openOutputStream(uri, "wt") ?: error("فایل مقصد قابل نوشتن نیست.")
        output.use { it.write(bytes); it.flush() }
    }
}
