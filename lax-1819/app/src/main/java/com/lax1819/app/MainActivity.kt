package com.lax1819.app

import android.graphics.Color
import android.os.Bundle
import android.os.SystemClock
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.platform.LocalView
import androidx.core.content.ContextCompat
import androidx.core.view.WindowCompat
import androidx.fragment.app.FragmentActivity
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.lax1819.app.data.ThemeMode
import com.lax1819.app.ui.components.AppBackground
import com.lax1819.app.ui.lock.LockScreen
import com.lax1819.app.ui.nav.AppNav
import com.lax1819.app.ui.theme.Lax1819Theme
import com.lax1819.app.ui.viewmodel.AppViewModel
import dagger.hilt.android.AndroidEntryPoint

/** FragmentActivity (not ComponentActivity) because BiometricPrompt needs it. */
@AndroidEntryPoint
class MainActivity : FragmentActivity() {
    private val appVm: AppViewModel by viewModels()
    private var stoppedAt = 0L

    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge(
            statusBarStyle = SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT),
            navigationBarStyle = SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT),
        )
        super.onCreate(savedInstanceState)

        setContent {
            val settings by appVm.settings.collectAsStateWithLifecycle()
            val dark = when (settings.theme) {
                ThemeMode.LIGHT -> false
                ThemeMode.DARK -> true
                else -> isSystemInDarkTheme()
            }
            Lax1819Theme(darkTheme = dark) {
                val view = LocalView.current
                SideEffect {
                    val c = WindowCompat.getInsetsController(window, view)
                    c.isAppearanceLightStatusBars = !dark
                    c.isAppearanceLightNavigationBars = !dark
                }
                AppBackground {
                    when {
                        !settings.loaded -> Unit // wait for DataStore (a few ms) – avoids start-screen flicker
                        settings.hasPin && !appVm.unlocked -> LockScreen(
                            biometricEnabled = settings.biometric,
                            onPin = appVm::tryPin,
                            onBiometric = { showBiometric() },
                        )
                        else -> AppNav(appVm)
                    }
                }
            }
        }
    }

    override fun onStop() {
        super.onStop()
        stoppedAt = SystemClock.elapsedRealtime()
    }

    override fun onStart() {
        super.onStart()
        // Re-lock if the app stayed in background for more than 60 seconds
        if (stoppedAt != 0L && SystemClock.elapsedRealtime() - stoppedAt > 60_000 && appVm.settings.value.hasPin) {
            appVm.lock()
        }
    }

    private fun showBiometric() {
        val can = BiometricManager.from(this).canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK)
        if (can != BiometricManager.BIOMETRIC_SUCCESS) return
        val prompt = BiometricPrompt(
            this,
            ContextCompat.getMainExecutor(this),
            object : BiometricPrompt.AuthenticationCallback() {
                override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                    appVm.unlock()
                }
            },
        )
        val info = BiometricPrompt.PromptInfo.Builder()
            .setTitle(getString(R.string.biometric_title))
            .setNegativeButtonText(getString(R.string.use_pin))
            .setAllowedAuthenticators(BiometricManager.Authenticators.BIOMETRIC_WEAK)
            .build()
        prompt.authenticate(info)
    }
}
