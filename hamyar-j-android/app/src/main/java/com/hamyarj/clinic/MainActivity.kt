package com.hamyarj.clinic

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.unit.LayoutDirection
import com.hamyarj.clinic.ui.HamyarApp
import com.hamyarj.clinic.ui.theme.HamyarTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            HamyarTheme {
                // The whole UI is Persian, so force right-to-left layout
                CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) {
                    HamyarApp()
                }
            }
        }
    }
}
