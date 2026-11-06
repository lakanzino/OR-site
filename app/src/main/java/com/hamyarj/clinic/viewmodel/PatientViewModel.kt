package com.hamyarj.clinic.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.hamyarj.clinic.HamyarApplication
import com.hamyarj.clinic.data.AppSettings
import com.hamyarj.clinic.data.Patient
import com.hamyarj.clinic.util.DateUtils
import com.hamyarj.clinic.util.normalizePersian
import com.hamyarj.clinic.util.toMoneyLong
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/** Which columns the patient list shows. */
data class ListColumns(
    val name: Boolean = true,
    val insurance: Boolean = true,
    val supplemental: Boolean = true,
    val reason: Boolean = true,
    val time: Boolean = true
)

/** Editable state of the Add/Edit form. [visitFee] holds raw digits (no commas). */
data class PatientForm(
    val id: Long = 0,
    val name: String = "",
    val phone: String = "",
    val basicInsurance: String = "",
    val hasSupplement: Boolean = false,
    val supplementalInsurance: String = "",
    val reason: String = "",
    val visitTime: String = DateUtils.nowTime(),
    val visitFee: String = "",
    val createdAt: Long = 0,
    val nameError: Boolean = false
) {
    val isEditing get() = id != 0L
    /** True when the user already typed something into a NEW form. */
    val isDirty get() = name.isNotBlank() || phone.isNotBlank() || reason.isNotBlank()
}

/** Used by the patient list screen AND the add/edit screen. */
class PatientViewModel(app: Application) : AndroidViewModel(app) {
    private val repo = (app as HamyarApplication).repository

    val settings: StateFlow<AppSettings> = repo.observeSettings()
        .stateIn(viewModelScope, SharingStarted.Eagerly, AppSettings())

    // ---------------- List ----------------
    val searchQuery = MutableStateFlow("")
    val columns = MutableStateFlow(ListColumns())

    /** Patients filtered by the search box (by name). */
    val patients: StateFlow<List<Patient>> =
        combine(repo.observePatients(), searchQuery) { list, query ->
            val q = query.normalizePersian()
            if (q.isEmpty()) list else list.filter { it.name.normalizePersian().contains(q) }
        }.stateIn(viewModelScope, SharingStarted.Eagerly, emptyList())

    fun setSearch(query: String) { searchQuery.value = query }
    fun setColumns(transform: (ListColumns) -> ListColumns) = columns.update(transform)

    fun delete(patient: Patient) {
        viewModelScope.launch { repo.deletePatient(patient) }
    }

    // ---------------- Add / Edit form ----------------
    private val _form = MutableStateFlow(PatientForm())
    val form: StateFlow<PatientForm> = _form

    init {
        viewModelScope.launch { resetForm() }
    }

    fun updateForm(transform: (PatientForm) -> PatientForm) = _form.update(transform)

    /** Opens an existing patient in the form. */
    fun startEdit(patient: Patient) {
        _form.value = PatientForm(
            id = patient.id,
            name = patient.name,
            phone = patient.phone,
            basicInsurance = patient.basicInsurance,
            hasSupplement = patient.hasSupplement,
            supplementalInsurance = patient.supplementalInsurance,
            reason = patient.reason,
            visitTime = patient.visitTime.ifEmpty { DateUtils.nowTime() },
            visitFee = if (patient.visitFee > 0) patient.visitFee.toString() else "",
            createdAt = patient.createdAt
        )
    }

    /**
     * Called when the user opens the "Add" tab: clears the form when it was
     * showing an edit, or when nothing has been typed yet (so default fee and
     * time are fresh). A half-filled new form is kept.
     */
    fun prepareNewForm() {
        val f = _form.value
        if (f.isEditing || !f.isDirty) viewModelScope.launch { resetForm() }
    }

    fun cancelEdit() {
        viewModelScope.launch { resetForm() }
    }

    private suspend fun resetForm() {
        val s = repo.getSettings()
        _form.value = PatientForm(
            visitTime = DateUtils.nowTime(),
            visitFee = if (s.defaultFee > 0) s.defaultFee.toString() else ""
        )
    }

    /** Validates and saves (insert or update). [onSaved] is called after success. */
    fun save(onSaved: () -> Unit) {
        val f = _form.value
        if (f.name.isBlank()) {
            _form.update { it.copy(nameError = true) }
            return
        }
        viewModelScope.launch {
            val patient = Patient(
                id = f.id,
                name = f.name.trim(),
                phone = f.phone.trim(),
                basicInsurance = f.basicInsurance,
                hasSupplement = f.hasSupplement,
                supplementalInsurance = if (f.hasSupplement) f.supplementalInsurance else "",
                reason = f.reason.trim(),
                visitTime = f.visitTime,
                visitFee = f.visitFee.toMoneyLong(),
                createdAt = if (f.isEditing) f.createdAt else System.currentTimeMillis()
            )
            if (f.isEditing) repo.updatePatient(patient) else repo.insertPatient(patient)
            resetForm()
            onSaved()
        }
    }
}
