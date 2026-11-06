package ir.clinic.secretary.util

import java.util.Locale

object PatientText {
    fun searchable(value: String): String = Fa.normalize(value)
        .replace('ي', 'ی').replace('ك', 'ک')
        .lowercase(Locale.ROOT).replace(Regex("\\s+"), " ").trim()

    /** Optional phone: 7–15 digits, with an optional leading + and common visual separators. */
    fun normalizePhone(input: String): String? {
        val compact = Fa.normalize(input).filterNot { it.isWhitespace() || it in "-()" }
        if (compact.isEmpty()) return ""
        val digits = compact.removePrefix("+")
        return if (digits.length in 7..15 && digits.all { it in '0'..'9' } &&
            (!compact.startsWith('+') || compact.indexOf('+') == 0)
        ) compact else null
    }
}

object InsuranceCatalog {
    val DEFAULTS = listOf("تامین اجتماعی", "خدمات درمانی", "آزاد", "سایر")
    private const val MAX_LENGTH = 80

    fun normalize(name: String): String = PatientText.searchable(name)

    fun validName(name: String): Boolean {
        val trimmed = name.trim()
        return trimmed.isNotEmpty() && trimmed.length <= MAX_LENGTH &&
            trimmed.none { it == '\n' || it == '\r' || it == '\t' }
    }

    fun options(custom: List<String>): List<String> =
        (DEFAULTS + custom.filter { validName(it) && DEFAULTS.none { d -> normalize(d) == normalize(it) } })
            .distinctBy { normalize(it) }

    fun duplicate(name: String, options: List<String>): Boolean =
        options.any { normalize(it) == normalize(name) }
}
