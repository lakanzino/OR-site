package com.hamyarj.clinic.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.hamyarj.clinic.HamyarApplication
import com.hamyarj.clinic.data.AppSettings
import com.hamyarj.clinic.util.DateUtils
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn

data class InsuranceCount(val name: String, val count: Int, val income: Long)

data class ReportState(
    val patientCount: Int = 0,
    val totalIncome: Long = 0,
    /** Patients per basic insurance, most frequent first. */
    val breakdown: List<InsuranceCount> = emptyList()
)

@OptIn(ExperimentalCoroutinesApi::class)
class ReportsViewModel(app: Application) : AndroidViewModel(app) {
    private val repo = (app as HamyarApplication).repository

    val settings: StateFlow<AppSettings> = repo.observeSettings()
        .stateIn(viewModelScope, SharingStarted.Eagerly, AppSettings())

    // Default range: first day of this month -> end of today
    private val now = System.currentTimeMillis()
    val startMillis = MutableStateFlow(DateUtils.startOfMonth(now))
    val endMillis = MutableStateFlow(DateUtils.endOfDay(now))

    val report: StateFlow<ReportState> = combine(startMillis, endMillis) { s, e -> s to e }
        .flatMapLatest { (s, e) -> repo.observePatientsBetween(s, e) }
        .map { list ->
            ReportState(
                patientCount = list.size,
                totalIncome = list.sumOf { it.visitFee },
                breakdown = list
                    .groupBy { it.basicInsurance.ifBlank { "بدون بیمه / نامشخص" } }
                    .map { (name, items) -> InsuranceCount(name, items.size, items.sumOf { it.visitFee }) }
                    .sortedByDescending { it.count }
            )
        }
        .stateIn(viewModelScope, SharingStarted.Eagerly, ReportState())

    /** Picking a start date after the end date moves the end date as well (and vice versa). */
    fun setStart(millis: Long) {
        val s = DateUtils.startOfDay(millis)
        startMillis.value = s
        if (endMillis.value < s) endMillis.value = DateUtils.endOfDay(millis)
    }

    fun setEnd(millis: Long) {
        val e = DateUtils.endOfDay(millis)
        endMillis.value = e
        if (startMillis.value > e) startMillis.value = DateUtils.startOfDay(millis)
    }
}
