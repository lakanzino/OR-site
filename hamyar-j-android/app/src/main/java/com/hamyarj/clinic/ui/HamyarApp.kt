package com.hamyarj.clinic.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.hamyarj.clinic.R
import com.hamyarj.clinic.ui.components.AutoSizeText
import com.hamyarj.clinic.ui.navigation.Screen
import com.hamyarj.clinic.ui.screens.AddPatientScreen
import com.hamyarj.clinic.ui.screens.PatientListScreen
import com.hamyarj.clinic.ui.screens.ReportsScreen
import com.hamyarj.clinic.ui.screens.SettingsScreen
import com.hamyarj.clinic.viewmodel.PatientViewModel
import com.hamyarj.clinic.viewmodel.ReportsViewModel
import com.hamyarj.clinic.viewmodel.SettingsViewModel

/** Root composable: top bar with logo, bottom navigation and the 4 screens. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HamyarApp() {
    val navController = rememberNavController()

    // Activity-scoped ViewModels shared by the screens
    val settingsVm: SettingsViewModel = viewModel()
    val patientVm: PatientViewModel = viewModel()
    val reportsVm: ReportsViewModel = viewModel()

    val settings by settingsVm.settings.collectAsState()
    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = backStackEntry?.destination?.route

    /** Navigates to a tab without stacking duplicates. */
    fun go(screen: Screen) {
        navController.navigate(screen.route) {
            popUpTo(navController.graph.findStartDestination().id) { saveState = false }
            launchSingleTop = true
        }
    }

    Scaffold(
        containerColor = MaterialTheme.colorScheme.background,
        topBar = {
            Column {
                TopAppBar(
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.surface
                    ),
                    title = {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            // Put your own app_logo.png in res/drawable (see README)
                            Image(
                                painter = painterResource(id = R.drawable.app_logo),
                                contentDescription = "Hamyar-j",
                                modifier = Modifier.size(36.dp)
                            )
                            Spacer(Modifier.width(10.dp))
                            Column {
                                Text(
                                    "Hamyar-j",
                                    style = MaterialTheme.typography.titleLarge,
                                    maxLines = 1
                                )
                                val subtitle = listOf(settings.doctorName, settings.specialty)
                                    .filter { it.isNotBlank() }.joinToString(" – ")
                                if (subtitle.isNotEmpty()) {
                                    Text(
                                        subtitle,
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                }
                            }
                        }
                    }
                )
                HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
            }
        },
        bottomBar = {
            Column {
                HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
                NavigationBar(containerColor = MaterialTheme.colorScheme.surface) {
                    Screen.tabs.forEach { screen ->
                        NavigationBarItem(
                            selected = currentRoute == screen.route,
                            onClick = {
                                if (screen == Screen.AddPatient) patientVm.prepareNewForm()
                                go(screen)
                            },
                            icon = { Icon(screen.icon, contentDescription = screen.label) },
                            label = {
                                AutoSizeText(
                                    screen.label,
                                    style = MaterialTheme.typography.labelSmall.copy(fontSize = 11.sp)
                                )
                            },
                            colors = NavigationBarItemDefaults.colors(
                                indicatorColor = MaterialTheme.colorScheme.primaryContainer,
                                selectedIconColor = MaterialTheme.colorScheme.onPrimaryContainer,
                                selectedTextColor = MaterialTheme.colorScheme.onPrimaryContainer
                            )
                        )
                    }
                }
            }
        }
    ) { inner ->
        NavHost(
            navController = navController,
            startDestination = Screen.PatientList.route,
            modifier = Modifier.padding(inner)
        ) {
            composable(Screen.Settings.route) { SettingsScreen(settingsVm) }

            composable(Screen.PatientList.route) {
                PatientListScreen(
                    vm = patientVm,
                    onEdit = { patient ->
                        patientVm.startEdit(patient)
                        go(Screen.AddPatient)
                    }
                )
            }

            composable(Screen.AddPatient.route) {
                AddPatientScreen(
                    vm = patientVm,
                    onSaved = { go(Screen.PatientList) },
                    onCancelEdit = { go(Screen.PatientList) }
                )
            }

            composable(Screen.Reports.route) { ReportsScreen(reportsVm) }
        }
    }
}
