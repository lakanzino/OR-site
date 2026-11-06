package ir.clinic.secretary

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import androidx.room.Room
import androidx.test.core.app.ApplicationProvider
import ir.clinic.secretary.data.AppDatabase
import ir.clinic.secretary.data.AppSettings
import ir.clinic.secretary.data.ClinicRepository
import ir.clinic.secretary.data.Patient
import kotlinx.coroutines.runBlocking
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertThrows
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34])
class DatabaseMigrationTest {
    private lateinit var context: Context
    private lateinit var db: AppDatabase
    private val name = "migration-test.db"

    @Before fun createVersion1Database() {
        context = ApplicationProvider.getApplicationContext()
        context.deleteDatabase(name)
        val file = context.getDatabasePath(name)
        file.parentFile?.mkdirs()
        val old = SQLiteDatabase.openOrCreateDatabase(file, null)
        old.execSQL("CREATE TABLE patients (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name TEXT NOT NULL, insuranceType TEXT NOT NULL, hasSupplemental INTEGER NOT NULL, reason TEXT NOT NULL, visitTime TEXT NOT NULL, visitDate TEXT NOT NULL, fee INTEGER NOT NULL)")
        old.execSQL("CREATE TABLE settings (`key` TEXT NOT NULL PRIMARY KEY, value TEXT NOT NULL)")
        old.execSQL("INSERT INTO patients VALUES (1,'بیمار قدیمی','آزاد',0,'','10:00','1403/05/12',250)")
        old.execSQL("INSERT INTO settings VALUES ('default_fee','250')")
        old.version = 1
        old.close()
        db = Room.databaseBuilder(context, AppDatabase::class.java, name).addMigrations(AppDatabase.MIGRATION_1_2).allowMainThreadQueries().build()
    }
    @After fun close() { db.close(); context.deleteDatabase(name) }

    @Test fun migrationPreservesLegacyRowsAndAddsColumns() = runBlocking {
        val p = db.patientDao().get(1)!!
        assertEquals("بیمار قدیمی", p.name)
        assertEquals(250L, p.fee)
        assertEquals("TOMAN", p.feeCurrency)
        assertEquals("", p.phone)
        val settings = AppSettings.fromMap(db.settingDao().getAll().associate { it.key to it.value })
        assertEquals(2500L, settings.defaultFeeRials)
        db.insuranceDao().insert(ir.clinic.secretary.data.CustomInsurance("بیمه جدید"))
        assertEquals(1, db.insuranceDao().getAll().size)
    }

    @Test fun restoreIsAtomicAndInvalidBackupLeavesDataUntouched() = runBlocking {
        val repo = ClinicRepository(db)
        val before = repo.backupText()
        assertThrows(IllegalArgumentException::class.java) { runBlocking { repo.restore("{\"format\":\"clinic-secretary-backup\",\"schemaVersion\":2,\"patients\":[{}],\"settings\":[],\"customInsurances\":[]}") } }
        assertEquals(before, repo.backupText())
        val replacement = ClinicBackupTestAccess.replacement()
        repo.restore(replacement)
        assertEquals(1, db.patientDao().getAll().size)
        assertEquals("بیمار جایگزین", db.patientDao().getAll().single().name)
        assertTrue(db.settingDao().getAll().any { it.key == AppSettings.KEY_CURRENCY && it.value == "RIAL" })
    }
}

object ClinicBackupTestAccess {
    fun replacement(): String = ir.clinic.secretary.data.ClinicBackup.encode(
        ir.clinic.secretary.data.ClinicSnapshot(
            patients = listOf(Patient(7, "بیمار جایگزین", "آزاد", false, "", "11:00", "1403/06/01", 9000, "", "RIAL")),
            settings = listOf(ir.clinic.secretary.data.Setting(AppSettings.KEY_CURRENCY, "RIAL")),
            insurances = emptyList()
        )
    )
}
