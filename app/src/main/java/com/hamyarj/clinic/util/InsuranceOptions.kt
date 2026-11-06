package com.hamyarj.clinic.util

/** Dropdown options for the patient form. Edit these lists to add/remove insurers. */
object InsuranceOptions {
    val basic = listOf(
        "سازمان تأمین اجتماعی",
        "سازمان بیمه سلامت ایران",
        "خدمات درمانی نیروهای مسلح",
        "کمیته امداد",
        "بهزیستی",
        "صندوق‌های درمان"
    )

    val supplemental = listOf(
        "ایران", "دانا", "آسیا", "البرز", "سامان", "پاسارگاد", "دی", "پارسیان",
        "کارآفرین", "ملت", "کوثر", "معلم", "نوین", "میهن", "آرمان",
        "کمک‌رسان ایران", "آتیه‌سازان حافظ", "بیماری‌های خاص"
    )
}
