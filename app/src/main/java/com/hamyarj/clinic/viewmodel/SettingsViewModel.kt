package com.hamyarj.clinic.viewmodel

import android.app.Application
import android.content.Context
import android.net.Uri
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.hamyarj.clinic.HamyarApplication
import com.hamyarj.clinic.data.AppSettings
import com.hamyarj.clinic.util.CsvExporter
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class SettingsViewModel(app: Application) : AndroidViewModel(app) {
    private val repo = (app as HamyarApplication).repository

    val settings: StateFlow<AppSettings> = repo.observeSettings()
        .stateIn(viewModelScope, SharingStarted.Eagerly, AppSettings())

    fun save(settings: AppSettings) {
        viewModelScope.launch { repo.saveSettings(settings) }
    }

    /** Builds the CSV on a background thread and returns its shareable Uri. */
    suspend fun createCsvUri(context: Context): Uri = withContext(Dispatchers.IO) {
        CsvExporter.createCsvUri(context, repo.getAllPatients())
    }
}
