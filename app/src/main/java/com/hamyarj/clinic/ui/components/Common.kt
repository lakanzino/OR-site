package com.hamyarj.clinic.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.hamyarj.clinic.ui.theme.AppTypography

/** Card with a clear, distinct border and no shadow. */
@Composable
fun AppCard(
    modifier: Modifier = Modifier,
    containerColor: Color = MaterialTheme.colorScheme.surface,
    content: @Composable ColumnScope.() -> Unit
) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = containerColor),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
        content = content
    )
}

/** Button whose label never wraps (shrinks instead). */
@Composable
fun AppButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    outlined: Boolean = false
) {
    val content: @Composable androidx.compose.foundation.layout.RowScope.() -> Unit = {
        if (icon != null) {
            Icon(icon, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(6.dp))
        }
        AutoSizeText(
            text = text,
            style = MaterialTheme.typography.labelLarge,
            fontWeight = FontWeight.Bold
        )
    }
    val shape = RoundedCornerShape(12.dp)
    val padding = PaddingValues(horizontal = 12.dp, vertical = 0.dp)
    if (outlined) {
        OutlinedButton(
            onClick = onClick,
            modifier = modifier.height(44.dp),
            shape = shape,
            contentPadding = padding,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
            content = content
        )
    } else {
        Button(
            onClick = onClick,
            modifier = modifier.height(44.dp),
            shape = shape,
            contentPadding = padding,
            colors = ButtonDefaults.buttonColors(),
            content = content
        )
    }
}

/** Tinted summary tile with a small label and a big bold number. */
@Composable
fun StatCard(label: String, value: String, modifier: Modifier = Modifier) {
    AppCard(modifier = modifier, containerColor = MaterialTheme.colorScheme.primaryContainer) {
        Column(Modifier.padding(horizontal = 12.dp, vertical = 10.dp)) {
            AutoSizeText(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onPrimaryContainer
            )
            Spacer(Modifier.height(2.dp))
            AutoSizeText(
                text = value,
                style = AppTypography.numberStyle,
                color = MaterialTheme.colorScheme.onPrimaryContainer
            )
        }
    }
}
