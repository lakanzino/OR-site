package com.lax1819.app.ui.about

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDirection
import androidx.compose.ui.unit.dp
import com.lax1819.app.AppInfo
import com.lax1819.app.BuildConfig
import com.lax1819.app.R
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.theme.accentText
import com.lax1819.app.util.Fa

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AboutScreen(onBack: () -> Unit) {
    var showLicense by rememberSaveable { mutableStateOf(false) }
    Scaffold(
        containerColor = Color.Transparent,
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.Transparent),
                title = { Text(stringResource(R.string.about)) },
                navigationIcon = {
                    IconButton(onClick = onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, stringResource(R.string.back)) }
                },
            )
        },
    ) { padding ->
        Column(
            Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Image(painterResource(R.drawable.app_logo), null, Modifier.size(112.dp))
            Text(AppInfo.NAME, style = MaterialTheme.typography.headlineMedium)
            Text(
                stringResource(R.string.version_label) + " " + Fa.digits(BuildConfig.VERSION_NAME.removeSuffix("-debug")),
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            GlassCard(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(stringResource(R.string.designed_by), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.accentText)
                    Text(stringResource(R.string.about_desc), style = MaterialTheme.typography.bodyLarge)
                }
            }
            GlassCard(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(stringResource(R.string.license_title), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.accentText)
                    Text(stringResource(R.string.license_short), style = MaterialTheme.typography.bodyLarge)
                    Text(AppInfo.COPYRIGHT, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    TextButton(onClick = { showLicense = !showLicense }) {
                        Text(stringResource(if (showLicense) R.string.hide_license else R.string.show_license))
                    }
                    AnimatedVisibility(showLicense) {
                        Text(
                            AppInfo.LICENSE_TEXT,
                            style = MaterialTheme.typography.bodySmall.copy(textDirection = TextDirection.Ltr, textAlign = TextAlign.Start),
                        )
                    }
                }
            }
            GlassCard(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(stringResource(R.string.credits), style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.accentText)
                    Text(stringResource(R.string.credits_body), style = MaterialTheme.typography.bodyMedium)
                }
            }
        }
    }
}
