package ir.clinic.secretary.data

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase

@Database(
    entities = [Patient::class, Setting::class, CustomInsurance::class],
    version = 2,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun patientDao(): PatientDao
    abstract fun settingDao(): SettingDao
    abstract fun insuranceDao(): InsuranceDao

    companion object {
        const val NAME = "clinic.db"

        /** No existing fee is multiplied or discarded. Legacy fees are explicitly treated as Toman. */
        val MIGRATION_1_2 = object : Migration(1, 2) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE patients ADD COLUMN phone TEXT NOT NULL DEFAULT ''")
                db.execSQL("ALTER TABLE patients ADD COLUMN feeCurrency TEXT NOT NULL DEFAULT 'TOMAN'")
                db.execSQL("CREATE TABLE IF NOT EXISTS custom_insurance_types (name TEXT NOT NULL PRIMARY KEY)")
            }
        }

        @Volatile private var instance: AppDatabase? = null

        fun get(context: Context): AppDatabase = instance ?: synchronized(this) {
            instance ?: Room.databaseBuilder(
                context.applicationContext, AppDatabase::class.java, NAME
            ).addMigrations(MIGRATION_1_2).build().also { instance = it }
        }
    }
}
