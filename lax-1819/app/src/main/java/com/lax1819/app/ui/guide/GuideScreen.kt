package com.lax1819.app.ui.guide

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.lax1819.app.R
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.components.MainBottomBar
import com.lax1819.app.ui.nav.Routes
import com.lax1819.app.ui.theme.accentText
import com.lax1819.app.util.Fa

@Composable
fun GuideScreen(onNavigate: (String) -> Unit) {
    Scaffold(
        containerColor = Color.Transparent,
        bottomBar = { MainBottomBar(Routes.GUIDE, onNavigate) },
    ) { padding ->
        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(
                start = 16.dp, end = 16.dp,
                top = padding.calculateTopPadding() + 16.dp,
                bottom = padding.calculateBottomPadding() + 24.dp,
            ),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item(key = "head") {
                Column(Modifier.padding(horizontal = 4.dp)) {
                    Text(stringResource(R.string.nav_guide), style = MaterialTheme.typography.headlineSmall)
                    Text(
                        stringResource(R.string.guide_intro),
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            itemsIndexed(GuideContent.sections, key = { _, s -> s.title }, contentType = { _, _ -> "guide" }) { i, s ->
                GuideCard(index = i + 1, section = s)
            }
        }
    }
}

@Composable
private fun GuideCard(index: Int, section: GuideSection) {
    var open by rememberSaveable(section.title) { mutableStateOf(index == 1) }
    GlassCard(Modifier.fillMaxWidth().animateContentSize(), onClick = { open = !open }) {
        Column(Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    Fa.int(index) + ". " + section.title,
                    modifier = Modifier.weight(1f),
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.accentText,
                )
                Icon(if (open) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore, null)
            }
            AnimatedVisibility(open, enter = expandVertically() + fadeIn(), exit = shrinkVertically() + fadeOut()) {
                Text(
                    section.body,
                    modifier = Modifier.padding(top = 10.dp),
                    style = MaterialTheme.typography.bodyLarge,
                )
            }
        }
    }
}
