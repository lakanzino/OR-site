package com.hamyarj.clinic.ui.navigation

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.BarChart
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.PersonAdd
import androidx.compose.material.icons.filled.Settings
import androidx.compose.ui.graphics.vector.ImageVector

/** The 4 bottom-navigation tabs, in display order. */
sealed class Screen(val route: String, val label: String, val icon: ImageVector) {
    data object Settings : Screen("settings", "تنظیمات", Icons.Filled.Settings)
    data object PatientList : Screen("patients", "لیست بیماران", Icons.Filled.People)
    data object AddPatient : Screen("add_patient", "ثبت بیمار", Icons.Filled.PersonAdd)
    data object Reports : Screen("reports", "گزارشات", Icons.Filled.BarChart)

    companion object {
        val tabs by lazy { listOf(Settings, PatientList, AddPatient, Reports) }
    }
}
