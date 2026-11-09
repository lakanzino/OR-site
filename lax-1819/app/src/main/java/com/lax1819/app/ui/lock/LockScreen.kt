package com.lax1819.app.ui.lock

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Fingerprint
import androidx.compose.material.icons.filled.LockOpen
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.lax1819.app.R
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.components.GlassFab
import com.lax1819.app.util.Fa

@Composable
fun LockScreen(
    biometricEnabled: Boolean,
    onPin: (String) -> Boolean,
    onBiometric: () -> Unit,
) {
    var pin by rememberSaveable { mutableStateOf("") }
    var error by rememberSaveable { mutableStateOf(false) }

    LaunchedEffect(Unit) { if (biometricEnabled) onBiometric() }

    Column(
        Modifier.fillMaxSize().padding(24.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Image(painterResource(R.drawable.app_logo), null, Modifier.size(88.dp))
        Spacer(Modifier.size(12.dp))
        Text(stringResource(R.string.app_name), style = MaterialTheme.typography.headlineSmall)
        Spacer(Modifier.size(24.dp))
        GlassCard(Modifier.padding(horizontal = 8.dp)) {
            Column(Modifier.padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(stringResource(R.string.enter_pin), style = MaterialTheme.typography.titleMedium)
                OutlinedTextField(
                    value = pin,
                    onValueChange = {
                        pin = Fa.onlyDigits(it).take(8)
                        error = false
                        // Try automatically once the user typed at least 4 digits
                        if (pin.length >= 4 && onPin(pin)) return@OutlinedTextField
                        if (pin.length == 8) { error = true; pin = "" }
                    },
                    singleLine = true,
                    isError = error,
                    supportingText = if (error) ({ Text(stringResource(R.string.wrong_pin)) }) else null,
                    visualTransformation = PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                    textStyle = MaterialTheme.typography.titleLarge.copy(textAlign = TextAlign.Center),
                )
                GlassFab(stringResource(R.string.unlock_app), Icons.Filled.LockOpen, onClick = {
                    if (!onPin(pin)) { error = true; pin = "" }
                })
                if (biometricEnabled) {
                    TextButton(onClick = onBiometric) {
                        Icon(Icons.Filled.Fingerprint, null)
                        Text("  " + stringResource(R.string.use_biometric))
                    }
                }
            }
        }
    }
}
