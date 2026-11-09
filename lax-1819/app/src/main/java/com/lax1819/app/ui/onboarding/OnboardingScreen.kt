package com.lax1819.app.ui.onboarding

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowForward
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material.icons.filled.WifiOff
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.lax1819.app.R
import com.lax1819.app.ui.components.GlassCard
import com.lax1819.app.ui.components.GlassFab
import com.lax1819.app.ui.components.IconBadge
import kotlinx.coroutines.launch

private data class OnbPage(val icon: ImageVector, val title: Int, val body: Int)

private val pages = listOf(
    OnbPage(Icons.Filled.CalendarMonth, R.string.onb1_title, R.string.onb1_body),
    OnbPage(Icons.Filled.Tune, R.string.onb2_title, R.string.onb2_body),
    OnbPage(Icons.Filled.Payments, R.string.onb3_title, R.string.onb3_body),
    OnbPage(Icons.Filled.WifiOff, R.string.onb4_title, R.string.onb4_body),
)

@Composable
fun OnboardingScreen(onFinish: () -> Unit) {
    val pager = rememberPagerState(pageCount = { pages.size })
    val scope = rememberCoroutineScope()
    val last = pager.currentPage == pages.size - 1

    Column(Modifier.fillMaxSize().statusBarsPadding().navigationBarsPadding().padding(20.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
            if (!last) TextButton(onClick = onFinish) { Text(stringResource(R.string.skip)) }
            else Spacer(Modifier.height(48.dp))
        }
        HorizontalPager(pager, Modifier.weight(1f)) { i ->
            val p = pages[i]
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                GlassCard(Modifier.fillMaxWidth(), elevation = 12.dp) {
                    Column(
                        Modifier.padding(28.dp).fillMaxWidth(),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp),
                    ) {
                        IconBadge(p.icon, Modifier.size(96.dp))
                        Text(stringResource(p.title), style = MaterialTheme.typography.headlineSmall, textAlign = TextAlign.Center)
                        Text(
                            stringResource(p.body),
                            style = MaterialTheme.typography.bodyLarge,
                            textAlign = TextAlign.Center,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }
        Row(Modifier.fillMaxWidth().padding(vertical = 16.dp), horizontalArrangement = Arrangement.Center) {
            repeat(pages.size) { i ->
                val sel = pager.currentPage == i
                val w by animateDpAsState(if (sel) 28.dp else 10.dp, label = "dotW")
                val c by animateColorAsState(
                    if (sel) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline,
                    label = "dotC",
                )
                Box(Modifier.padding(horizontal = 4.dp).height(10.dp).width(w).clip(CircleShape).background(c))
            }
        }
        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            if (last) GlassFab(stringResource(R.string.start_using), Icons.Filled.Check, onFinish)
            else GlassFab(stringResource(R.string.next), Icons.AutoMirrored.Filled.ArrowForward, onClick = {
                scope.launch { pager.animateScrollToPage(pager.currentPage + 1) }
            })
        }
    }
}
