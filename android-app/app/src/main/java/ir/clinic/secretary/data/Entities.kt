package ir.clinic.secretary.data

import androidx.room.Entity
import androidx.room.PrimaryKey
import ir.clinic.secretary.util.Currency

/** A patient visit. Existing version-1 rows keep their fee and are assigned TOMAN on migration. */
@Entity(tableName = "patients")
data class Patient(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val name: String,
    val insuranceType: String,
    val hasSupplemental: Boolean,
    val reason: String,
    val visitTime: String,
    val visitDate: String,
    val fee: Long,
    val phone: String = "",
    val feeCurrency: String = Currency.TOMAN.code
)

@Entity(tableName = "settings")
data class Setting(@PrimaryKey val key: String, val value: String)

/** Only user-added options live here; the four original options remain available. */
@Entity(tableName = "custom_insurance_types")
data class CustomInsurance(@PrimaryKey val name: String)

data class AppSettings(
    val doctorName: String = "نام پزشک",
    val specialty: String = "تخصص پزشک",
    val defaultFeeRials: Long = 0L,
    val currency: Currency = Currency.TOMAN
) {
    companion object {
        const val KEY_NAME = "doctor_name"
        const val KEY_SPECIALTY = "doctor_specialty"
        const val KEY_FEE = "default_fee" // Legacy amount in Toman, retained for compatibility.
        const val KEY_FEE_RIALS = "default_fee_rials"
        const val KEY_CURRENCY = "display_currency"

        fun fromMap(m: Map<String, String>): AppSettings {
            val defaults = AppSettings()
            val currency = Currency.fromCode(m[KEY_CURRENCY])
            val legacyRials = m[KEY_FEE]?.toLongOrNull()?.let {
                if (it in 0L..(Long.MAX_VALUE / 10)) it * 10 else null
            }
            return AppSettings(
                doctorName = m[KEY_NAME] ?: defaults.doctorName,
                specialty = m[KEY_SPECIALTY] ?: defaults.specialty,
                defaultFeeRials = m[KEY_FEE_RIALS]?.toLongOrNull()?.takeIf { it >= 0 }
                    ?: legacyRials ?: 0L,
                currency = currency
            )
        }
    }
}
