package com.hamyarj.clinic.util

import android.content.Context
import android.content.Intent
import android.database.sqlite.SQLiteDatabase
import android.net.Uri
import com.hamyarj.clinic.data.AppDatabase
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File

/**
 * Backup / restore of the Room database file.
 * Uses the Storage Access Framework Uris (picked by the user), so no storage
 * permission is required on any Android version.
 */
object BackupManager {

    /** Copies the live database file to [target]. */
    suspend fun exportDatabase(context: Context, target: Uri): Result<Unit> =
        withContext(Dispatchers.IO) {
            runCatching {
                // Flush the write-ahead log so the .db file contains every change
                AppDatabase.getInstance(context).openHelper.writableDatabase
                    .query("PRAGMA wal_checkpoint(FULL)").use { it.moveToFirst() }

                val dbFile = context.getDatabasePath(AppDatabase.DB_NAME)
                val out = context.contentResolver.openOutputStream(target)
                    ?: error("Cannot open destination")
                out.use { o -> dbFile.inputStream().use { it.copyTo(o) } }
                // copyTo returns the byte count; the function promises Result<Unit>
                Unit
            }
        }

    /**
     * Validates [source], replaces the current database with it.
     * The caller must restart the app afterwards (see [restartApp]).
     */
    suspend fun importDatabase(context: Context, source: Uri): Result<Unit> =
        withContext(Dispatchers.IO) {
            runCatching {
                val tmp = File(context.cacheDir, "restore_tmp.db")
                val input = context.contentResolver.openInputStream(source)
                    ?: error("Cannot open file")
                input.use { i -> tmp.outputStream().use { i.copyTo(it) } }

                try {
                    validate(tmp)
                } catch (e: Exception) {
                    tmp.delete()
                    throw e
                }

                AppDatabase.closeInstance()
                val dbFile = context.getDatabasePath(AppDatabase.DB_NAME)
                File(dbFile.path + "-wal").delete()
                File(dbFile.path + "-shm").delete()
                tmp.copyTo(dbFile, overwrite = true)
                tmp.delete()
                // File.delete()/copyTo() return values; the function promises Result<Unit>
                Unit
            }
        }

    /** Makes sure the file is a SQLite DB containing this app's tables. */
    private fun validate(file: File) {
        file.inputStream().use {
            val header = ByteArray(16)
            val read = it.read(header)
            require(read == 16 && String(header, Charsets.US_ASCII) == "SQLite format 3\u0000") {
                "Not a valid database file"
            }
        }
        val db = SQLiteDatabase.openDatabase(file.path, null, SQLiteDatabase.OPEN_READWRITE)
        try {
            db.rawQuery(
                "SELECT name FROM sqlite_master WHERE type='table' AND name IN ('patients','app_settings')",
                null
            ).use { require(it.count == 2) { "Database does not belong to Hamyar-j" } }
        } finally {
            db.close()
            File(file.path + "-wal").delete()
            File(file.path + "-shm").delete()
        }
    }

    /** Restarts the app so Room re-opens the restored file. */
    fun restartApp(context: Context) {
        val intent = context.packageManager.getLaunchIntentForPackage(context.packageName)
            ?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
        if (intent != null) context.startActivity(intent)
        Runtime.getRuntime().exit(0)
    }
}
