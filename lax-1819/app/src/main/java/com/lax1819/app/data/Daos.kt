package com.lax1819.app.data

import androidx.room.Dao
import androidx.room.Delete
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import kotlinx.coroutines.flow.Flow

@Dao
interface SheetDao {
    @Query(
        """SELECT s.id AS id, s.dateKey AS dateKey, s.title AS title, s.createdAt AS createdAt,
        (SELECT COUNT(*) FROM entries e WHERE e.sheetId = s.id) AS entryCount,
        (SELECT COALESCE(SUM(e.cost), 0) FROM entries e WHERE e.sheetId = s.id) AS totalCost
        FROM sheets s WHERE s.dateKey = :dateKey ORDER BY s.createdAt"""
    )
    fun observeSummaries(dateKey: Int): Flow<List<SheetSummary>>

    @Query("SELECT dateKey, COUNT(*) AS cnt FROM sheets WHERE dateKey BETWEEN :from AND :to GROUP BY dateKey")
    fun observeMonthCounts(from: Int, to: Int): Flow<List<DayCount>>

    @Query("SELECT * FROM sheets WHERE id = :id")
    fun observe(id: Long): Flow<SheetEntity?>

    @Query("SELECT * FROM sheets WHERE id = :id")
    suspend fun get(id: Long): SheetEntity?

    @Query("SELECT * FROM sheets WHERE dateKey < :dateKey ORDER BY dateKey DESC, createdAt DESC LIMIT 1")
    suspend fun previousSheet(dateKey: Int): SheetEntity?

    @Query("SELECT * FROM sheets ORDER BY dateKey, createdAt")
    suspend fun all(): List<SheetEntity>

    @Query("SELECT COUNT(*) FROM sheets")
    suspend fun count(): Int

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(sheet: SheetEntity): Long

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(list: List<SheetEntity>)

    @Update
    suspend fun update(sheet: SheetEntity)

    @Query("DELETE FROM sheets WHERE id = :id")
    suspend fun delete(id: Long)

    @Query("DELETE FROM sheets")
    suspend fun deleteAll()

    @Query("DELETE FROM sheets WHERE title LIKE '★ نمونه%'")
    suspend fun deleteDemo()
}

@Dao
interface EntryDao {
    @Query("SELECT * FROM entries WHERE sheetId = :sheetId ORDER BY position, id")
    fun observeBySheet(sheetId: Long): Flow<List<EntryEntity>>

    @Query("SELECT * FROM entries WHERE sheetId = :sheetId ORDER BY position, id")
    suspend fun listBySheet(sheetId: Long): List<EntryEntity>

    @Query("SELECT * FROM entries ORDER BY sheetId, position, id")
    suspend fun all(): List<EntryEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(entry: EntryEntity): Long

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(list: List<EntryEntity>)

    @Update
    suspend fun update(entry: EntryEntity)

    @Query("DELETE FROM entries WHERE id = :id")
    suspend fun delete(id: Long)

    @Query("DELETE FROM entries")
    suspend fun deleteAll()

    @Query("UPDATE entries SET cost = cost * 10, deposit = deposit * 10, instAmount = instAmount * 10")
    suspend fun multiplyMoney()

    @Query("UPDATE entries SET cost = cost / 10, deposit = deposit / 10, instAmount = instAmount / 10")
    suspend fun divideMoney()
}

@Dao
interface ColumnDao {
    @Query("SELECT * FROM columns ORDER BY position")
    fun observeAll(): Flow<List<ColumnEntity>>

    @Query("SELECT * FROM columns ORDER BY position")
    suspend fun all(): List<ColumnEntity>

    @Query("SELECT COUNT(*) FROM columns")
    suspend fun count(): Int

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(column: ColumnEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(list: List<ColumnEntity>)

    @Query("DELETE FROM columns WHERE `key` = :key")
    suspend fun delete(key: String)

    @Query("DELETE FROM columns")
    suspend fun deleteAll()
}

@Dao
interface QuickDao {
    @Query("SELECT * FROM quick_items WHERE kind = :kind ORDER BY favorite DESC, useCount DESC, lastUsed DESC")
    fun observe(kind: String): Flow<List<QuickItemEntity>>

    @Query("SELECT * FROM quick_items WHERE kind = :kind AND text = :text LIMIT 1")
    suspend fun find(kind: String, text: String): QuickItemEntity?

    @Query("SELECT * FROM quick_items")
    suspend fun all(): List<QuickItemEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(item: QuickItemEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(list: List<QuickItemEntity>)

    @Delete
    suspend fun delete(item: QuickItemEntity)

    @Query("DELETE FROM quick_items")
    suspend fun deleteAll()
}

@Dao
interface TemplateDao {
    @Query("SELECT * FROM templates ORDER BY id DESC")
    fun observeAll(): Flow<List<TemplateEntity>>

    @Query("SELECT * FROM templates")
    suspend fun all(): List<TemplateEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(t: TemplateEntity): Long

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(list: List<TemplateEntity>)

    @Query("DELETE FROM templates WHERE id = :id")
    suspend fun delete(id: Long)

    @Query("DELETE FROM templates")
    suspend fun deleteAll()
}
