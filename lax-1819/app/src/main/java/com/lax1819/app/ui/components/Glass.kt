package com.lax1819.app.ui.components

import android.os.Build
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.InteractionSource
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.ripple
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.composed
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.lax1819.app.ui.theme.Amber
import com.lax1819.app.ui.theme.Beige
import com.lax1819.app.ui.theme.DarkBg2
import com.lax1819.app.ui.theme.Gold
import com.lax1819.app.ui.theme.LocalIsDark
import com.lax1819.app.ui.theme.SoftOrange

/** Elevation levels used across the app for a consistent sense of depth. */
object Elev {
    val Low = 2.dp
    val Mid = 6.dp
    val High = 12.dp
}

/** Subtle spring scale while pressed (3D "push" feeling). */
fun Modifier.pressScale(source: InteractionSource, target: Float = 0.97f): Modifier = composed {
    val pressed by source.collectIsPressedAsState()
    val scale by animateFloatAsState(
        targetValue = if (pressed) target else 1f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessMedium),
        label = "pressScale",
    )
    graphicsLayer { scaleX = scale; scaleY = scale }
}

/**
 * Layered background: warm gradient + soft glowing blobs. On API 31+ the blobs
 * are truly blurred with RenderEffect; older devices fall back to the radial
 * gradient softness (still looks layered).
 */
@Composable
fun AppBackground(modifier: Modifier = Modifier, content: @Composable BoxScope.() -> Unit) {
    val dark = LocalIsDark.current
    val cs = MaterialTheme.colorScheme
    val bottom = if (dark) DarkBg2 else Beige
    Box(
        modifier
            .fillMaxSize()
            .background(Brush.verticalGradient(listOf(cs.background, bottom)))
    ) {
        val blobAlpha = if (dark) 0.18f else 0.38f
        Box(
            Modifier
                .fillMaxSize()
                .then(if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) Modifier.blur(40.dp) else Modifier)
                .drawBehind {
                    val r = size.width * 0.75f
                    val c1 = Offset(size.width * 0.95f, size.height * 0.04f)
                    drawCircle(Brush.radialGradient(listOf(Amber.copy(alpha = blobAlpha), Color.Transparent), c1, r), r, c1)
                    val c2 = Offset(size.width * 0.05f, size.height * 0.92f)
                    drawCircle(Brush.radialGradient(listOf(SoftOrange.copy(alpha = blobAlpha * 0.8f), Color.Transparent), c2, r), r, c2)
                    val c3 = Offset(size.width * 0.15f, size.height * 0.35f)
                    drawCircle(Brush.radialGradient(listOf(Gold.copy(alpha = blobAlpha * 0.5f), Color.Transparent), c3, r * 0.6f), r * 0.6f, c3)
                }
        )
        content()
    }
}

/** Frosted-glass card: translucent gradient fill, highlight border, soft warm shadow. */
@Composable
fun GlassCard(
    modifier: Modifier = Modifier,
    elevation: Dp = Elev.Mid,
    shape: Shape = RoundedCornerShape(22.dp),
    alpha: Float = 0.88f,
    onClick: (() -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    val dark = LocalIsDark.current
    val cs = MaterialTheme.colorScheme
    val source = remember { MutableInteractionSource() }
    val shadowColor = if (dark) Color.Black else Color(0xFF8A5A1E)
    val fill = Brush.verticalGradient(
        listOf(
            cs.surface.copy(alpha = alpha),
            (if (dark) cs.surfaceVariant else cs.background).copy(alpha = (alpha - 0.1f).coerceAtLeast(0.6f)),
        )
    )
    val border = Brush.verticalGradient(
        listOf(
            Color.White.copy(alpha = if (dark) 0.16f else 0.95f),
            cs.outline.copy(alpha = 0.35f),
        )
    )
    var m = modifier
    if (onClick != null) m = m.pressScale(source)
    m = m
        .shadow(elevation, shape, ambientColor = shadowColor, spotColor = shadowColor)
        .clip(shape)
        .background(fill)
        .border(1.dp, border, shape)
    if (onClick != null) m = m.clickable(interactionSource = source, indication = ripple(), onClick = onClick)
    Column(m, content = content)
}

/** Sticky glass bar (bottom summary / navigation). */
@Composable
fun GlassBar(
    modifier: Modifier = Modifier,
    content: @Composable RowScope.() -> Unit,
) {
    val dark = LocalIsDark.current
    val cs = MaterialTheme.colorScheme
    val shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp)
    val shadowColor = if (dark) Color.Black else Color(0xFF8A5A1E)
    Row(
        modifier
            .fillMaxWidth()
            .shadow(Elev.High, shape, ambientColor = shadowColor, spotColor = shadowColor)
            .clip(shape)
            .background(Brush.verticalGradient(listOf(cs.surface.copy(alpha = 0.95f), cs.surface.copy(alpha = 0.86f))))
            .border(BorderStroke(1.dp, Color.White.copy(alpha = if (dark) 0.12f else 0.8f)), shape),
        verticalAlignment = Alignment.CenterVertically,
        content = content,
    )
}

/** Elevated gradient button / extended FAB. */
@Composable
fun GlassFab(
    text: String,
    icon: ImageVector,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val source = remember { MutableInteractionSource() }
    val shape = RoundedCornerShape(24.dp)
    Row(
        modifier
            .pressScale(source, 0.95f)
            .shadow(Elev.High, shape, ambientColor = SoftOrange, spotColor = SoftOrange)
            .clip(shape)
            .background(Brush.linearGradient(listOf(Gold, Amber, SoftOrange)))
            .border(1.dp, Color.White.copy(alpha = 0.55f), shape)
            .clickable(interactionSource = source, indication = ripple(), onClick = onClick)
            .padding(horizontal = 22.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.Center,
    ) {
        Icon(icon, contentDescription = null, tint = Color(0xFF3E2200), modifier = Modifier.size(22.dp))
        Spacer(Modifier.width(8.dp))
        Text(text, color = Color(0xFF3E2200), fontWeight = FontWeight.Bold, style = MaterialTheme.typography.labelLarge)
    }
}

/** Round tinted icon "chip" used as card leading icon. */
@Composable
fun IconBadge(icon: ImageVector, modifier: Modifier = Modifier, tint: Color = Color(0xFF3E2200)) {
    Box(
        modifier
            .size(44.dp)
            .shadow(Elev.Low, CircleShape)
            .clip(CircleShape)
            .background(Brush.linearGradient(listOf(Gold, Amber))),
        contentAlignment = Alignment.Center,
    ) { Icon(icon, null, tint = tint, modifier = Modifier.size(24.dp)) }
}

@Composable
fun EmptyState(icon: ImageVector, title: String, body: String, modifier: Modifier = Modifier) {
    Column(
        modifier
            .fillMaxWidth()
            .padding(horizontal = 32.dp, vertical = 28.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        IconBadge(icon, Modifier.size(64.dp))
        Spacer(Modifier.size(14.dp))
        Text(title, style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center)
        Spacer(Modifier.size(6.dp))
        Text(
            body,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
        )
    }
}

@Composable
fun SectionTitle(text: String, modifier: Modifier = Modifier) {
    Text(
        text,
        modifier = modifier.padding(horizontal = 20.dp, vertical = 8.dp),
        style = MaterialTheme.typography.titleMedium,
        color = MaterialTheme.colorScheme.onBackground,
    )
}
