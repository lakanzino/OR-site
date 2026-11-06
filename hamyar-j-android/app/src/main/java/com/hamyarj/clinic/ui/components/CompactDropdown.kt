package com.hamyarj.clinic.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp

/**
 * Compact dropdown that looks like [CompactTextField] (same border and height).
 * The popup is exactly as wide as the field.
 */
@Composable
fun CompactDropdown(
    label: String,
    selected: String,
    options: List<String>,
    onSelected: (String) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String = "انتخاب کنید",
    clearLabel: String? = "بدون انتخاب"
) {
    var expanded by remember { mutableStateOf(false) }
    var fieldWidthPx by remember { mutableIntStateOf(0) }
    val colors = MaterialTheme.colorScheme
    val shape = RoundedCornerShape(10.dp)

    Column(modifier) {
        AutoSizeText(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            color = colors.onSurfaceVariant
        )
        Spacer(Modifier.height(3.dp))
        Box {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .onSizeChanged { fieldWidthPx = it.width }
                    .clip(shape)
                    .background(colors.surface)
                    .border(if (expanded) 1.5.dp else 1.dp, if (expanded) colors.primary else colors.outline, shape)
                    .clickable { expanded = true }
                    .padding(start = 10.dp, end = 4.dp, top = 6.dp, bottom = 6.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = selected.ifEmpty { placeholder },
                    modifier = Modifier.weight(1f),
                    style = MaterialTheme.typography.bodyMedium,
                    color = if (selected.isEmpty()) colors.onSurfaceVariant.copy(alpha = 0.6f) else colors.onSurface,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Icon(Icons.Filled.ArrowDropDown, contentDescription = null, tint = colors.onSurfaceVariant)
            }

            DropdownMenu(
                expanded = expanded,
                onDismissRequest = { expanded = false },
                modifier = Modifier
                    .width(with(LocalDensity.current) { fieldWidthPx.toDp() })
                    .heightIn(max = 300.dp)
                    .background(colors.surface)
            ) {
                if (clearLabel != null && selected.isNotEmpty()) {
                    DropdownMenuItem(
                        text = { Text(clearLabel, color = colors.onSurfaceVariant, maxLines = 1) },
                        onClick = { onSelected(""); expanded = false }
                    )
                }
                options.forEach { option ->
                    DropdownMenuItem(
                        text = {
                            Text(
                                option,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                                style = if (option == selected) MaterialTheme.typography.labelLarge
                                else MaterialTheme.typography.bodyMedium
                            )
                        },
                        onClick = { onSelected(option); expanded = false }
                    )
                }
            }
        }
    }
}
