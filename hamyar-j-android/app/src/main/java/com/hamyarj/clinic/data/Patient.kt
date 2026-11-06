package com.hamyarj.clinic.data

import androidx.room.Entity
import androidx.room.PrimaryKey

/** A single patient visit record. */
@Entity(tableName = "patients")
data class Patient(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val name: String,
    val phone: String = "",
    /** One of [com.hamyarj.clinic.util.InsuranceOptions.basic] or empty. */
    val basicInsurance: String = "",
    val hasSupplement: Boolean = false,
    /** One of [com.hamyarj.clinic.util.InsuranceOptions.supplemental]; empty if [hasSupplement] is false. */
    val supplementalInsurance: String = "",
    val reason: String = "",
    /** "HH:mm" (24h) */
    val visitTime: String = "",
    /** Stored in the smallest unit chosen by the doctor (e.g. Toman) */
    val visitFee: Long = 0,
    /** Epoch millis. Used for date-range reports. */
    val createdAt: Long = System.currentTimeMillis()
)
