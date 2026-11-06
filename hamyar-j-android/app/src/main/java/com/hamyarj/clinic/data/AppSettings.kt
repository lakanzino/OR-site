package com.hamyarj.clinic.data

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * Single-row settings table. The row always uses id = 1, so saving
 * with REPLACE simply overwrites the previous settings.
 */
@Entity(tableName = "app_settings")
data class AppSettings(
    @PrimaryKey val id: Int = 1,
    val doctorName: String = "",
    val specialty: String = "",
    val defaultFee: Long = 0,
    val currencyUnit: String = "تومان"
)
