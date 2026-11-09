package com.lax1819.app.ui.nav

object Routes {
    const val ONBOARDING = "onboarding"
    const val HOME = "home"
    const val GUIDE = "guide"
    const val SETTINGS = "settings"
    const val SHEET = "sheet/{sheetId}"
    const val COLUMNS = "columns"
    const val ABOUT = "about"

    fun sheet(id: Long) = "sheet/$id"
}
