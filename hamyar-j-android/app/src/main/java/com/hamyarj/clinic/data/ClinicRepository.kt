package com.hamyarj.clinic.data

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

/** Single access point to the data layer used by all ViewModels. */
class ClinicRepository(private val db: AppDatabase) {

    private val patientDao = db.patientDao()
    private val settingsDao = db.settingsDao()

    // ---- Patients ----
    fun observePatients(): Flow<List<Patient>> = patientDao.observeAll()
    fun observePatientsBetween(start: Long, end: Long): Flow<List<Patient>> =
        patientDao.observeBetween(start, end)

    suspend fun getAllPatients(): List<Patient> = patientDao.getAll()
    suspend fun insertPatient(patient: Patient) = patientDao.insert(patient)
    suspend fun updatePatient(patient: Patient) = patientDao.update(patient)
    suspend fun deletePatient(patient: Patient) = patientDao.delete(patient)

    // ---- Settings (falls back to defaults when nothing was saved yet) ----
    fun observeSettings(): Flow<AppSettings> = settingsDao.observe().map { it ?: AppSettings() }
    suspend fun getSettings(): AppSettings = settingsDao.get() ?: AppSettings()
    suspend fun saveSettings(settings: AppSettings) = settingsDao.save(settings.copy(id = 1))
}
