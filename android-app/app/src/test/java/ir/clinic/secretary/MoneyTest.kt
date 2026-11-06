package ir.clinic.secretary

import ir.clinic.secretary.util.Currency
import ir.clinic.secretary.util.Money
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class MoneyTest {
    @Test fun parsesPersianArabicAndGroupedAmounts() {
        assertEquals(1_500_000L, Money.parseToRials("۱۵۰٬۰۰۰", Currency.TOMAN))
        assertEquals(1_500_000L, Money.parseToRials("١٥٠٠٠", Currency.RIAL))
        assertEquals(12L, Money.parseToRials("۱٫۲", Currency.TOMAN))
        assertEquals(12L, Money.parseToRials("1.2", Currency.TOMAN))
    }
    @Test fun rejectsInvalidNegativeAndOverflowAmounts() {
        assertNull(Money.parseToRials("-1", Currency.TOMAN))
        assertNull(Money.parseToRials("1.2", Currency.RIAL))
        assertNull(Money.parseToRials("1.23", Currency.TOMAN))
        assertNull(Money.parseToRials("abc", Currency.TOMAN))
        assertNull(Money.parseToRials("9223372036854775807", Currency.TOMAN))
        assertNull(Money.rialsOf(Long.MAX_VALUE, Currency.TOMAN))
    }
    @Test fun canonicalRialsRoundTripWithoutLosingFractionalToman() {
        for (rials in listOf(0L, 1L, 9L, 10L, 11L, 1_500_001L)) {
            for (unit in Currency.entries) {
                val text = Money.inputAmount(rials, unit)
                assertEquals(rials, Money.parseToRials(text, unit))
            }
        }
        assertEquals("0.1", Money.inputAmount(1L, Currency.TOMAN))
        assertEquals("1", Money.inputAmount(1L, Currency.RIAL))
    }
    @Test fun totalDetectsOverflow() {
        assertEquals(30L, Money.totalRials(listOf(10L, 20L)))
        assertNull(Money.totalRials(listOf(Long.MAX_VALUE, 1L)))
    }
}
