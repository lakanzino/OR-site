package ir.clinic.secretary.data

import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.InsuranceCatalog
import ir.clinic.secretary.util.PatientText
import ir.clinic.secretary.util.PersianDate
import org.json.JSONArray
import org.json.JSONException
import org.json.JSONObject

data class ClinicSnapshot(
    val patients: List<Patient>,
    val settings: List<Setting>,
    val insurances: List<CustomInsurance>
)

/** Versioned, validated logical backup of every application table. No server or storage permission. */
object ClinicBackup {
    const val FORMAT = "clinic-secretary-backup"
    const val VERSION = 2
    const val MAX_BYTES = 10 * 1024 * 1024
    private val TIME = Regex("^([01]\\d|2[0-3]):[0-5]\\d$")
    private val ALLOWED_SETTING_KEYS = setOf(
        AppSettings.KEY_NAME, AppSettings.KEY_SPECIALTY, AppSettings.KEY_FEE,
        AppSettings.KEY_FEE_RIALS, AppSettings.KEY_CURRENCY
    )

    fun encode(snapshot: ClinicSnapshot): String {
        val root = JSONObject().put("format", FORMAT).put("schemaVersion", VERSION)
        root.put("patients", JSONArray().apply {
            snapshot.patients.forEach { p ->
                put(JSONObject().apply {
                    put("id", p.id); put("name", p.name); put("phone", p.phone)
                    put("insuranceType", p.insuranceType); put("hasSupplemental", p.hasSupplemental)
                    put("reason", p.reason); put("visitTime", p.visitTime); put("visitDate", p.visitDate)
                    put("fee", p.fee); put("feeCurrency", p.feeCurrency)
                })
            }
        })
        root.put("settings", JSONArray().apply {
            snapshot.settings.forEach { put(JSONObject().put("key", it.key).put("value", it.value)) }
        })
        root.put("customInsurances", JSONArray().apply {
            snapshot.insurances.forEach { put(JSONObject().put("name", it.name)) }
        })
        return root.toString(2)
    }

    fun decode(text: String): ClinicSnapshot {
        require(text.toByteArray(Charsets.UTF_8).size <= MAX_BYTES) { "فایل پشتیبان بیش از حد بزرگ است." }
        val root = json("فایل پشتیبان معتبر نیست یا آسیب دیده است.") { JSONObject(text) }
        require(root.opt("format") == FORMAT && root.opt("schemaVersion") == VERSION) {
            "این فایل، پشتیبان معتبر نسخهٔ ۲ منشی مطب نیست."
        }
        val patientRows = json("ساختار فایل پشتیبان کامل نیست.") { root.getJSONArray("patients") }
        val settingRows = json("ساختار فایل پشتیبان کامل نیست.") { root.getJSONArray("settings") }
        val insuranceRows = json("ساختار فایل پشتیبان کامل نیست.") { root.getJSONArray("customInsurances") }
        require(patientRows.length() <= 100_000 && settingRows.length() <= 100 && insuranceRows.length() <= 1000) {
            "تعداد رکوردهای فایل مجاز نیست."
        }

        val patients = (0 until patientRows.length()).map { i ->
            val o = json("اطلاعات بیماران در فایل پشتیبان معتبر نیست.") { patientRows.getJSONObject(i) }
            val p = json("اطلاعات بیماران در فایل پشتیبان معتبر نیست.") {
                Patient(
                    id = o.getLong("id"), name = o.getString("name"), phone = o.getString("phone"),
                    insuranceType = o.getString("insuranceType"), hasSupplemental = o.getBoolean("hasSupplemental"),
                    reason = o.getString("reason"), visitTime = o.getString("visitTime"), visitDate = o.getString("visitDate"),
                    fee = o.getLong("fee"), feeCurrency = o.getString("feeCurrency")
                )
            }
            require(
                p.id > 0 && p.name.isNotBlank() && p.name.length <= 200 && p.reason.length <= 5000 &&
                    p.phone.length <= 30 && PatientText.normalizePhone(p.phone) != null &&
                    InsuranceCatalog.validName(p.insuranceType) && p.fee >= 0 &&
                    Currency.entries.any { it.code == p.feeCurrency } && PersianDate.parseOrNull(p.visitDate) != null &&
                    TIME.matches(p.visitTime)
            ) { "اطلاعات یکی از بیماران در فایل پشتیبان معتبر نیست." }
            p
        }
        require(patients.distinctBy { it.id }.size == patients.size) { "شناسهٔ تکراری در فایل پشتیبان وجود دارد." }

        val settings = (0 until settingRows.length()).map { i ->
            val o = json("تنظیمات فایل پشتیبان معتبر نیست.") { settingRows.getJSONObject(i) }
            val setting = json("تنظیمات فایل پشتیبان معتبر نیست.") { Setting(o.getString("key"), o.getString("value")) }
            require(setting.key in ALLOWED_SETTING_KEYS && setting.value.length <= 1000) { "تنظیمات فایل پشتیبان معتبر نیست." }
            if (setting.key == AppSettings.KEY_CURRENCY) {
                require(Currency.entries.any { it.code == setting.value }) { "واحد پول فایل پشتیبان معتبر نیست." }
            }
            if (setting.key == AppSettings.KEY_FEE || setting.key == AppSettings.KEY_FEE_RIALS) {
                val amount = setting.value.toLongOrNull()
                val max = if (setting.key == AppSettings.KEY_FEE) Long.MAX_VALUE / 10 else Long.MAX_VALUE
                require(amount != null && amount in 0L..max) { "مبلغ پیش‌فرض فایل پشتیبان معتبر نیست." }
            }
            if (setting.key == AppSettings.KEY_NAME || setting.key == AppSettings.KEY_SPECIALTY) {
                require(setting.value.length <= 200) { "نام پزشک یا تخصص در فایل پشتیبان بیش از حد طولانی است." }
            }
            setting
        }
        require(settings.distinctBy { it.key }.size == settings.size) { "تنظیمات تکراری در فایل پشتیبان وجود دارد." }

        val insurances = (0 until insuranceRows.length()).map { i ->
            val o = json("فهرست بیمه‌های فایل پشتیبان معتبر نیست.") { insuranceRows.getJSONObject(i) }
            val name = json("فهرست بیمه‌های فایل پشتیبان معتبر نیست.") { o.getString("name") }
            require(
                InsuranceCatalog.validName(name) && name == name.trim() &&
                    InsuranceCatalog.DEFAULTS.none { InsuranceCatalog.normalize(it) == InsuranceCatalog.normalize(name) }
            ) { "فهرست بیمه‌های فایل پشتیبان معتبر نیست." }
            CustomInsurance(name)
        }
        require(insurances.distinctBy { InsuranceCatalog.normalize(it.name) }.size == insurances.size) {
            "بیمهٔ تکراری در فایل پشتیبان وجود دارد."
        }
        return ClinicSnapshot(patients, settings, insurances)
    }

    private fun <T> json(message: String, block: () -> T): T = try {
        block()
    } catch (e: JSONException) {
        throw IllegalArgumentException(message, e)
    }
}
