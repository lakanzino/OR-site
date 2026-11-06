package com.hamyarj.clinic.ui.screens

import android.widget.Toast
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccessTime
import androidx.compose.material.icons.filled.Save
import androidx.compose.material3.Checkbox
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.clickable
import com.hamyarj.clinic.ui.components.AppButton
import com.hamyarj.clinic.ui.components.AppCard
import com.hamyarj.clinic.ui.components.AppTimePickerDialog
import com.hamyarj.clinic.ui.components.CompactDropdown
import com.hamyarj.clinic.ui.components.CompactTextField
import com.hamyarj.clinic.ui.components.MoneyTextField
import com.hamyarj.clinic.ui.components.PickerField
import com.hamyarj.clinic.util.InsuranceOptions
import com.hamyarj.clinic.viewmodel.PatientViewModel

/** Used for both adding a new patient and editing an existing one. */
@Composable
fun AddPatientScreen(
    vm: PatientViewModel,
    onSaved: () -> Unit,
    onCancelEdit: () -> Unit
) {
    val context = LocalContext.current
    val form by vm.form.collectAsState()
    val settings by vm.settings.collectAsState()
    var showTimePicker by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(12.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Text(
            text = if (form.isEditing) "ویرایش اطلاعات بیمار" else "ثبت بیمار جدید",
            style = MaterialTheme.typography.titleLarge
        )

        AppCard(Modifier.fillMaxWidth()) {
            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {

                CompactTextField(
                    value = form.name,
                    onValueChange = { v -> vm.updateForm { it.copy(name = v, nameError = false) } },
                    label = "نام بیمار *",
                    isError = form.nameError,
                    errorText = "وارد کردن نام الزامی است",
                    modifier = Modifier.fillMaxWidth()
                )

                CompactTextField(
                    value = form.phone,
                    onValueChange = { v ->
                        vm.updateForm { it.copy(phone = v.filter { c -> c.isDigit() || c == '+' }) }
                    },
                    label = "شماره تلفن",
                    keyboardType = KeyboardType.Phone,
                    modifier = Modifier.fillMaxWidth()
                )

                CompactDropdown(
                    label = "بیمه پایه",
                    selected = form.basicInsurance,
                    options = InsuranceOptions.basic,
                    onSelected = { v -> vm.updateForm { it.copy(basicInsurance = v) } },
                    modifier = Modifier.fillMaxWidth()
                )

                // Supplemental insurance checkbox
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable {
                            vm.updateForm { it.copy(hasSupplement = !it.hasSupplement) }
                        },
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Checkbox(
                        checked = form.hasSupplement,
                        onCheckedChange = { c ->
                            vm.updateForm {
                                it.copy(
                                    hasSupplement = c,
                                    supplementalInsurance = if (c) it.supplementalInsurance else ""
                                )
                            }
                        }
                    )
                    Text("بیمه مکمل", style = MaterialTheme.typography.labelLarge, maxLines = 1)
                }

                // Only visible when the checkbox is checked
                AnimatedVisibility(visible = form.hasSupplement) {
                    CompactDropdown(
                        label = "نام بیمه مکمل",
                        selected = form.supplementalInsurance,
                        options = InsuranceOptions.supplemental,
                        onSelected = { v -> vm.updateForm { it.copy(supplementalInsurance = v) } },
                        modifier = Modifier.fillMaxWidth()
                    )
                }

                CompactTextField(
                    value = form.reason,
                    onValueChange = { v -> vm.updateForm { it.copy(reason = v) } },
                    label = "دلیل مراجعه",
                    singleLine = false,
                    minLines = 3,
                    modifier = Modifier.fillMaxWidth()
                )

                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    PickerField(
                        label = "ساعت ویزیت",
                        valueText = form.visitTime,
                        icon = Icons.Filled.AccessTime,
                        onClick = { showTimePicker = true },
                        modifier = Modifier.weight(1f)
                    )
                    MoneyTextField(
                        value = form.visitFee,
                        onValueChange = { v -> vm.updateForm { it.copy(visitFee = v) } },
                        label = "مبلغ ویزیت (${settings.currencyUnit})",
                        modifier = Modifier.weight(1.3f)
                    )
                }
            }
        }

        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            AppButton(
                text = if (form.isEditing) "ذخیره تغییرات" else "ذخیره بیمار",
                icon = Icons.Filled.Save,
                modifier = Modifier.weight(1f),
                onClick = {
                    vm.save {
                        Toast.makeText(context, "اطلاعات بیمار ذخیره شد", Toast.LENGTH_SHORT).show()
                        onSaved()
                    }
                }
            )
            if (form.isEditing) {
                AppButton(
                    text = "انصراف",
                    outlined = true,
                    modifier = Modifier.weight(0.6f),
                    onClick = { vm.cancelEdit(); onCancelEdit() }
                )
            }
        }
    }

    if (showTimePicker) {
        AppTimePickerDialog(
            initial = form.visitTime,
            onDismiss = { showTimePicker = false },
            onConfirm = { t -> vm.updateForm { it.copy(visitTime = t) } }
        )
    }
}
