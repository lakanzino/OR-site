package com.lax1819.app.data

import androidx.room.Database
import androidx.room.RoomDatabase

@Database(
    entities = [
        SheetEntity::class,
        EntryEntity::class,
        ColumnEntity::class,
        QuickItemEntity::class,
        TemplateEntity::class,
    ],
    version = 1,
    exportSchema = false,
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun sheetDao(): SheetDao
    abstract fun entryDao(): EntryDao
    abstract fun columnDao(): ColumnDao
    abstract fun quickDao(): QuickDao
    abstract fun templateDao(): TemplateDao

    companion object {
        const val NAME = "lax1819.db"
    }
}
