package com.lax1819.app.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material3.FilledTonalIconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.lax1819.app.util.Fa
import com.lax1819.app.util.PersianDigitsTransformation
import com.lax1819.app.util.ThousandsTransformation

/**
 * Money input with live thousands separators (۱٬۲۳۴٬۵۶۷) and a currency label.
 * The field state holds raw digits; ThousandsTransformation renders the grouping.
 */
@Composable
fun MoneyField(
    label: String,
    value: Long,
    currencyLabel: String,
    onValueChange: (Long) -> Unit,
    modifier: Modifier = Modifier,
) {
    OutlinedTextField(
        value = if (value == 0L) "" else value.toString(),
        onValueChange = { raw ->
            val d = Fa.onlyDigits(raw).take(15)
            onValueChange(d.toLongOrNull() ?: 0L)
        },
        modifier = modifier.fillMaxWidth(),
        label = { Text(label) },
        suffix = { Text(currencyLabel, color = MaterialTheme.colorScheme.onSurfaceVariant) },
        singleLine = true,
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
        visualTransformation = ThousandsTransformation(),
        textStyle = MaterialTheme.typography.bodyLarge.copy(textAlign = TextAlign.Start),
        shape = androidx.compose.foundation.shape.RoundedCornerShape(16.dp),
    )
}

/** Integer / plain number input displayed with Persian digits. */
@Composable
fun NumberField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    decimal: Boolean = false,
    suffix: String? = null,
) {
    OutlinedTextField(
        value = value,
        onValueChange = { raw -> onValueChange(if (decimal) Fa.onlyDecimal(raw) else Fa.onlyDigits(raw).take(12)) },
        modifier = modifier,
        label = { Text(label) },
        suffix = if (suffix != null) {
            { Text(suffix, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        } else null,
        singleLine = true,
        keyboardOptions = KeyboardOptions(keyboardType = if (decimal) KeyboardType.Decimal else KeyboardType.Number),
        visualTransformation = PersianDigitsTransformation(),
        shape = androidx.compose.foundation.shape.RoundedCornerShape(16.dp),
    )
}

/** − [ value unit ] + stepper for quantities. */
@Composable
fun QuantityStepper(
    value: Double,
    unit: String,
    onChange: (Double) -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(modifier, verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        FilledTonalIconButton(onClick = { onChange((value - 1).coerceAtLeast(0.0)) }, modifier = Modifier.size(40.dp)) {
            Icon(Icons.Filled.Remove, contentDescription = null)
        }
        Text(
            Fa.qty(value) + " " + unit,
            style = MaterialTheme.typography.titleMedium,
            textAlign = TextAlign.Center,
            modifier = Modifier.width(96.dp),
        )
        FilledTonalIconButton(onClick = { onChange(value + 1) }, modifier = Modifier.size(40.dp)) {
            Icon(Icons.Filled.Add, contentDescription = null)
        }
    }
}

/** Text field that keeps the user's text while typing (no external reformatting). */
@Composable
fun TextInput(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    singleLine: Boolean = true,
    trailing: (@Composable () -> Unit)? = null,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        modifier = modifier.fillMaxWidth(),
        label = { Text(label) },
        singleLine = singleLine,
        minLines = if (singleLine) 1 else 3,
        trailingIcon = trailing,
        shape = androidx.compose.foundation.shape.RoundedCornerShape(16.dp),
    )
}

@Composable
fun Gap(h: Int = 8) = Spacer(Modifier.size(h.dp))

@Composable
fun rememberToggle(initial: Boolean = false) = remember { mutableStateOf(initial) }
