package com.lax1819.app.ui.nav

import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.lax1819.app.ui.about.AboutScreen
import com.lax1819.app.ui.columns.ColumnsScreen
import com.lax1819.app.ui.guide.GuideScreen
import com.lax1819.app.ui.home.HomeScreen
import com.lax1819.app.ui.onboarding.OnboardingScreen
import com.lax1819.app.ui.settings.SettingsScreen
import com.lax1819.app.ui.sheet.SheetScreen
import com.lax1819.app.ui.viewmodel.AppViewModel

@Composable
fun AppNav(appVm: AppViewModel) {
    val settings by appVm.settings.collectAsStateWithLifecycle()
    // Decided once: onboarding on very first launch, otherwise the calendar
    val start = remember { if (settings.onboardingDone) Routes.HOME else Routes.ONBOARDING }
    val nav = rememberNavController()

    val go: (String) -> Unit = { route ->
        nav.navigate(route) {
            popUpTo(Routes.HOME) { saveState = true }
            launchSingleTop = true
            restoreState = true
        }
    }
    val bouncy = spring<Float>(dampingRatio = Spring.DampingRatioLowBouncy, stiffness = Spring.StiffnessMediumLow)

    NavHost(
        navController = nav,
        startDestination = start,
        enterTransition = { fadeIn(tween(220)) + scaleIn(bouncy, initialScale = 0.96f) },
        exitTransition = { fadeOut(tween(120)) },
        popEnterTransition = { fadeIn(tween(220)) + scaleIn(bouncy, initialScale = 0.96f) },
        popExitTransition = { fadeOut(tween(120)) },
    ) {
        composable(Routes.ONBOARDING) {
            OnboardingScreen(onFinish = {
                appVm.finishOnboarding()
                nav.navigate(Routes.HOME) { popUpTo(Routes.ONBOARDING) { inclusive = true } }
            })
        }
        composable(Routes.HOME) {
            HomeScreen(onOpenSheet = { nav.navigate(Routes.sheet(it)) }, onNavigate = go)
        }
        composable(Routes.GUIDE) { GuideScreen(onNavigate = go) }
        composable(Routes.SETTINGS) {
            SettingsScreen(
                onNavigate = go,
                onOpenColumns = { nav.navigate(Routes.COLUMNS) },
                onOpenAbout = { nav.navigate(Routes.ABOUT) },
            )
        }
        composable(Routes.SHEET, arguments = listOf(navArgument("sheetId") { type = NavType.LongType })) {
            SheetScreen(onBack = { nav.popBackStack() }, onOpenColumns = { nav.navigate(Routes.COLUMNS) })
        }
        composable(Routes.COLUMNS) { ColumnsScreen(onBack = { nav.popBackStack() }) }
        composable(Routes.ABOUT) { AboutScreen(onBack = { nav.popBackStack() }) }
    }
}
