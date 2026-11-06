package com.hamyarj.clinic.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.TransformedText
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.text.AnnotatedString
import com.hamyarj.clinic.util.digitsOnly

/**
 * Compact input field: small paddings, a clear 1dp border (1.5dp when focused),
 * the label sits above the field (auto-sized, never wraps).
 */
@Composable
fun CompactTextField(
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    label: String? = null,
    placeholder: String = "",
    keyboardType: KeyboardType = KeyboardType.Text,
    singleLine: Boolean = true,
    minLines: Int = 1,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    isError: Boolean = false,
    errorText: String? = null,
    leading: (@Composable () -> Unit)? = null,
    trailing: (@Composable () -> Unit)? = null
) {
    val interaction = remember { MutableInteractionSource() }
    val focused by interaction.collectIsFocusedAsState()
    val colors = MaterialTheme.colorScheme
    val shape = RoundedCornerShape(10.dp)
    val borderColor = when {
        isError -> colors.error
        focused -> colors.primary
        else -> colors.outline
    }

    Column(modifier) {
        if (label != null) {
            AutoSizeText(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = colors.onSurfaceVariant
            )
            Spacer(Modifier.height(3.dp))
        }
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            modifier = Modifier.fillMaxWidth(),
            textStyle = MaterialTheme.typography.bodyMedium.copy(color = colors.onSurface),
            keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
            singleLine = singleLine,
            minLines = minLines,
            maxLines = if (singleLine) 1 else 6,
            visualTransformation = visualTransformation,
            interactionSource = interaction,
            cursorBrush = SolidColor(colors.primary),
            decorationBox = { inner ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(colors.surface, shape)
                        .border(if (focused || isError) 1.5.dp else 1.dp, borderColor, shape)
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    leading?.invoke()
                    Box(Modifier.weight(1f), contentAlignment = Alignment.CenterStart) {
                        if (value.isEmpty() && placeholder.isNotEmpty()) {
                            Text(
                                placeholder,
                                style = MaterialTheme.typography.bodyMedium,
                                color = colors.onSurfaceVariant.copy(alpha = 0.6f),
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                        inner()
                    }
                    trailing?.invoke()
                }
            }
        )
        if (isError && errorText != null) {
            Text(
                errorText,
                style = MaterialTheme.typography.bodySmall,
                color = colors.error,
                modifier = Modifier.padding(top = 2.dp),
                maxLines = 1
            )
        }
    }
}

/**
 * Money input: keeps raw digits as state and renders them with thousands
 * separators WHILE the user types (1200000 -> 1,200,000).
 * Pass the raw digit string as [value]; read it back with String.toMoneyLong().
 */
@Composable
fun MoneyTextField(
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    label: String? = null,
    placeholder: String = "0"
) {
    CompactTextField(
        value = value,
        onValueChange = { input ->
            onValueChange(input.digitsOnly().take(15))
        },
        modifier = modifier,
        label = label,
        placeholder = placeholder,
        keyboardType = KeyboardType.Number,
        visualTransformation = ThousandSeparatorTransformation
    )
}

/** Inserts "," every 3 digits and keeps the cursor correctly mapped. */
object ThousandSeparatorTransformation : VisualTransformation {
    override fun filter(text: AnnotatedString): TransformedText {
        val digits = text.text
        val n = digits.length
        val out = StringBuilder()
        digits.forEachIndexed { i, c ->
            if (i > 0 && (n - i) % 3 == 0) out.append(',')
            out.append(c)
        }
        val mapping = object : OffsetMapping {
            override fun originalToTransformed(offset: Int): Int {
                val o = offset.coerceIn(0, n)
                // separators located before the cursor
                return o + (1 until o).count { (n - it) % 3 == 0 }
            }

            override fun transformedToOriginal(offset: Int): Int {
                var count = 0
                for (i in 0 until n) {
                    val transformedIndex = i + (1..i).count { (n - it) % 3 == 0 }
                    if (transformedIndex < offset) count = i + 1 else break
                }
                return count
            }
        }
        return TransformedText(AnnotatedString(out.toString()), mapping)
    }
}
