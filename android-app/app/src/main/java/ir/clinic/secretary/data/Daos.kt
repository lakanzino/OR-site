package ir.clinic.secretary.data

import androidx.room.Dao
import androidx.room.Delete
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import kotlinx.coroutines.flow.Flow

@Dao
interface PatientDao {
    @Query("SELECT * FROM patients ORDER BY id DESC")
    fun observeAll(): Flow<List<Patient>>

    @Query("SELECT * FROM patients ORDER BY id DESC")
    suspend fun getAll(): List<Patient>

    @Query("SELECT * FROM patients WHERE id = :id")
    suspend fun get(id: Long): Patient?

    @Insert
    suspend fun insert(patient: Patient): Long

    @Insert
    suspend fun insertAll(patients: List<Patient>)

    @Update
    suspend fun update(patient: Patient)

    @Delete
    suspend fun delete(patient: Patient)

    @Query("DELETE FROM patients")
    suspend fun clear()
}

@Dao
interface SettingDao {
    @Query("SELECT * FROM settings")
    fun observeAll(): Flow<List<Setting>>

    @Query("SELECT * FROM settings")
    suspend fun getAll(): List<Setting>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(items: List<Setting>)

    @Query("DELETE FROM settings")
    suspend fun clear()
}

@Dao
interface InsuranceDao {
    @Query("SELECT * FROM custom_insurance_types ORDER BY name COLLATE NOCASE")
    fun observeAll(): Flow<List<CustomInsurance>>

    @Query("SELECT * FROM custom_insurance_types ORDER BY name COLLATE NOCASE")
    suspend fun getAll(): List<CustomInsurance>

    @Insert(onConflict = OnConflictStrategy.IGNORE)
    suspend fun insert(item: CustomInsurance): Long

    @Insert
    suspend fun insertAll(items: List<CustomInsurance>)

    @Query("DELETE FROM custom_insurance_types WHERE name = :name")
    suspend fun delete(name: String)

    @Query("DELETE FROM custom_insurance_types")
    suspend fun clear()
}
