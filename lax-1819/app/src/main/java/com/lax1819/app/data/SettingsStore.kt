package com.lax1819.app.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import java.security.MessageDigest
import java.security.SecureRandom
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "lax1819_settings")

object Currency {
    const val RIAL = "rial"
    const val TOMAN = "toman"
    fun label(c: String) = if (c == Currency.RIAL) "ریال" else "تومان"
}

object ThemeMode {
    const val SYSTEM = 0
    const val LIGHT = 1
    const val DARK = 2
}

data class AppSettings(
    val loaded: Boolean = false,
    val currency: String = Currency.TOMAN,
    val theme: Int = ThemeMode.SYSTEM,
    val quantityUnit: String = "عدد",
    val onboardingDone: Boolean = false,
    val demoSeeded: Boolean = false,
    val demoActive: Boolean = false,
    val mode: String = "general",
    val pinHash: String = "",
    val pinSalt: String = "",
    val biometric: Boolean = false,
) {
    val currencyLabel: String get() = Currency.label(currency)
    val hasPin: Boolean get() = pinHash.isNotEmpty()
}

/** DataStore-backed preferences (survive force-stop, reboot and process death). */
@Singleton
class SettingsStore @Inject constructor(@ApplicationContext private val context: Context) {
    private object K {
        val currency = stringPreferencesKey("currency")
        val theme = intPreferencesKey("theme")
        val unit = stringPreferencesKey("qty_unit")
        val onboarding = booleanPreferencesKey("onboarding_done")
        val demoSeeded = booleanPreferencesKey("demo_seeded")
        val demoActive = booleanPreferencesKey("demo_active")
        val mode = stringPreferencesKey("mode")
        val pinHash = stringPreferencesKey("pin_hash")
        val pinSalt = stringPreferencesKey("pin_salt")
        val biometric = booleanPreferencesKey("biometric")
    }

    val flow: Flow<AppSettings> = context.dataStore.data.map { p ->
        AppSettings(
            loaded = true,
            currency = p[K.currency] ?: Currency.TOMAN,
            theme = p[K.theme] ?: ThemeMode.SYSTEM,
            quantityUnit = p[K.unit] ?: "عدد",
            onboardingDone = p[K.onboarding] ?: false,
            demoSeeded = p[K.demoSeeded] ?: false,
            demoActive = p[K.demoActive] ?: false,
            mode = p[K.mode] ?: "general",
            pinHash = p[K.pinHash] ?: "",
            pinSalt = p[K.pinSalt] ?: "",
            biometric = p[K.biometric] ?: false,
        )
    }

    suspend fun current(): AppSettings = flow.first()

    suspend fun setCurrency(v: String) = context.dataStore.edit { it[K.currency] = v }
    suspend fun setTheme(v: Int) = context.dataStore.edit { it[K.theme] = v }
    suspend fun setUnit(v: String) = context.dataStore.edit { it[K.unit] = v.ifBlank { "عدد" } }
    suspend fun setOnboardingDone() = context.dataStore.edit { it[K.onboarding] = true }
    suspend fun setDemo(seeded: Boolean, active: Boolean) = context.dataStore.edit {
        it[K.demoSeeded] = seeded
        it[K.demoActive] = active
    }
    suspend fun setMode(v: String) = context.dataStore.edit { it[K.mode] = v }
    suspend fun setBiometric(v: Boolean) = context.dataStore.edit { it[K.biometric] = v }

    /** Stores a salted SHA-256 hash of the PIN (null or blank removes the lock). */
    suspend fun setPin(pin: String?) = context.dataStore.edit {
        if (pin.isNullOrBlank()) {
            it.remove(K.pinHash); it.remove(K.pinSalt); it[K.biometric] = false
        } else {
            val salt = ByteArray(12).also { b -> SecureRandom().nextBytes(b) }.joinToString("") { b -> "%02x".format(b) }
            it[K.pinSalt] = salt
            it[K.pinHash] = hash(pin, salt)
        }
    }

    fun verifyPin(pin: String, s: AppSettings): Boolean = s.hasPin && hash(pin, s.pinSalt) == s.pinHash

    private fun hash(pin: String, salt: String): String =
        MessageDigest.getInstance("SHA-256").digest((salt + pin).toByteArray())
            .joinToString("") { "%02x".format(it) }

    suspend fun resetAll() = context.dataStore.edit { it.clear() }
}
