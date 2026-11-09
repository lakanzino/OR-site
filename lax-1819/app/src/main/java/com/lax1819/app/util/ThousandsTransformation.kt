package com.lax1819.app.util

import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.input.OffsetMapping
import androidx.compose.ui.text.input.TransformedText
import androidx.compose.ui.text.input.VisualTransformation

/**
 * Live thousands grouping while typing: the field holds raw ASCII digits
 * ("1234567") and this transformation renders "۱٬۲۳۴٬۵۶۷" with a correct
 * cursor offset mapping.
 */
class ThousandsTransformation : VisualTransformation {
    override fun filter(text: AnnotatedString): TransformedText {
        val raw = text.text
        val n = raw.length
        val sb = StringBuilder()
        val o2t = IntArray(n + 1)
        for (i in 0 until n) {
            if (i > 0 && (n - i) % 3 == 0) sb.append(Fa.SEP)
            o2t[i] = sb.length
            val ch = raw[i]
            sb.append(if (ch in '0'..'9') '۰' + (ch - '0') else ch)
        }
        o2t[n] = sb.length
        val out = sb.toString()
        val t2o = IntArray(out.length + 1)
        var c = 0
        for (t in out.indices) {
            t2o[t] = c
            if (out[t] != Fa.SEP) c++
        }
        t2o[out.length] = c
        val mapping = object : OffsetMapping {
            override fun originalToTransformed(offset: Int): Int = o2t[offset.coerceIn(0, n)]
            override fun transformedToOriginal(offset: Int): Int = t2o[offset.coerceIn(0, out.length)]
        }
        return TransformedText(AnnotatedString(out), mapping)
    }
}

/** Shows ASCII digits as Persian digits (no grouping) – used for numbers, quantity, time. */
class PersianDigitsTransformation : VisualTransformation {
    override fun filter(text: AnnotatedString): TransformedText {
        val mapped = buildString {
            for (ch in text.text) append(if (ch in '0'..'9') '۰' + (ch - '0') else if (ch == '.') Fa.DECIMAL else ch)
        }
        return TransformedText(AnnotatedString(mapped), OffsetMapping.Identity)
    }
}
