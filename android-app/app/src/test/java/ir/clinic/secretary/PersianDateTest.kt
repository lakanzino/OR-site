package ir.clinic.secretary

import ir.clinic.secretary.util.PersianDate
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test
import java.util.Calendar
import java.util.Locale
import java.util.TimeZone

class PersianDateTest {
    private fun utc(y: Int, m: Int, d: Int): Calendar = Calendar.getInstance(TimeZone.getTimeZone("UTC"), Locale.US).apply {
        clear(); set(y, m - 1, d)
    }
    @Test fun knownNowruzAndWeekday() {
        val date = PersianDate.from(utc(2024, 3, 20))
        assertEquals("1403/01/01", date.format())
        assertEquals("چهارشنبه", date.weekdayName)
        assertEquals(2024, date.toCalendar().get(Calendar.YEAR))
        assertEquals(Calendar.MARCH, date.toCalendar().get(Calendar.MONTH))
        assertEquals(20, date.toCalendar().get(Calendar.DAY_OF_MONTH))
    }
    @Test fun leapDayValidation() {
        assertNotNull(PersianDate.parseOrNull("1403/12/30"))
        assertNull(PersianDate.parseOrNull("1404/12/30"))
        assertNull(PersianDate.parseOrNull("1403/06/31"))
        assertNull(PersianDate.parseOrNull("1403/13/01"))
        assertNull(PersianDate.parseOrNull("1299/01/01"))
    }
    @Test fun roundTripAcrossTwoCenturies() {
        val cal = utc(1921, 3, 21)
        val end = utc(2121, 3, 20)
        while (!cal.after(end)) {
            val date = PersianDate.from(cal)
            val back = date.toCalendar()
            assertEquals(cal.get(Calendar.YEAR), back.get(Calendar.YEAR))
            assertEquals(cal.get(Calendar.MONTH), back.get(Calendar.MONTH))
            assertEquals(cal.get(Calendar.DAY_OF_MONTH), back.get(Calendar.DAY_OF_MONTH))
            cal.add(Calendar.DAY_OF_YEAR, 11)
        }
    }
}
