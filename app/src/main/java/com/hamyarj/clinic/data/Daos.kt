package com.hamyarj.clinic.data

import androidx.room.Dao
import androidx.room.Delete
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import kotlinx.coroutines.flow.Flow

@Dao
interface PatientDao {
    @Query("SELECT * FROM patients ORDER BY createdAt DESC")
    fun observeAll(): Flow<List<Patient>>

    @Query("SELECT * FROM patients ORDER BY createdAt DESC")
    suspend fun getAll(): List<Patient>

    /** Patients registered between [start] and [end] (inclusive, epoch millis). */
    @Query("SELECT * FROM patients WHERE createdAt BETWEEN :start AND :end ORDER BY createdAt DESC")
    fun observeBetween(start: Long, end: Long): Flow<List<Patient>>

    @Insert
    suspend fun insert(patient: Patient): Long

    @Update
    suspend fun update(patient: Patient)

    @Delete
    suspend fun delete(patient: Patient)
}

@Dao
interface SettingsDao {
    @Query("SELECT * FROM app_settings WHERE id = 1")
    fun observe(): Flow<AppSettings?>

    @Query("SELECT * FROM app_settings WHERE id = 1")
    suspend fun get(): AppSettings?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun save(settings: AppSettings)
}
