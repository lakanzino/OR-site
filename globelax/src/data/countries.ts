// Globe.Lax – embedded, offline country dataset (195 countries:
// 193 UN member states + Vatican City + State of Palestine, the two UN observer states).
//
// Sources cross-checked (verified 2026): World Bank Open Data population series for 2025
// (SP.POP.TOTL, 3-significant-digit educational rounding), UN Statistics Division total area,
// CIA World Factbook, ISO 3166-1 / ISO 4217, UN member-state roster (193 + Vatican City + Palestine).
// Population is stored in THOUSANDS in the raw table; areas are total area in km².
// Continent follows common geographic/educational convention (Cyprus → Europe, Turkey/Georgia/Armenia/
// Azerbaijan/Kazakhstan → Asia, Russia → Europe, Central America & Caribbean → North America).

import { CONTINENTS, CURRENCIES, LANGS, type Continent } from "./dictionaries";
import { FACTS, NOTES } from "./facts";

type Row = [
  iso: string, fa: string, en: string, capFa: string, capEn: string,
  cur: string, popK: number, area: number, langs: string, cont: Continent,
];

const RAW: Row[] = [
  // ───────── Asia ─────────
  ["AF", "افغانستان", "Afghanistan", "کابل", "Kabul", "AFN", 43800, 652230, "ps,prs", "AS"],
  ["AM", "ارمنستان", "Armenia", "ایروان", "Yerevan", "AMD", 3090, 29743, "hy", "AS"],
  ["AZ", "جمهوری آذربایجان", "Azerbaijan", "باکو", "Baku", "AZN", 10200, 86600, "az", "AS"],
  ["BH", "بحرین", "Bahrain", "منامه", "Manama", "BHD", 1600, 765, "ar", "AS"],
  ["BD", "بنگلادش", "Bangladesh", "داکا", "Dhaka", "BDT", 176000, 147570, "bn", "AS"],
  ["BT", "بوتان", "Bhutan", "تیمپو", "Thimphu", "BTN", 797, 38394, "dz", "AS"],
  ["BN", "برونئی", "Brunei", "بندر سری بگاوان", "Bandar Seri Begawan", "BND", 466, 5765, "ms", "AS"],
  ["KH", "کامبوج", "Cambodia", "پنوم‌پن", "Phnom Penh", "KHR", 17800, 181035, "km", "AS"],
  ["CN", "چین", "China", "پکن", "Beijing", "CNY", 1410000, 9596960, "zh", "AS"],
  ["GE", "گرجستان", "Georgia", "تفلیس", "Tbilisi", "GEL", 3940, 69700, "ka", "AS"],
  ["IN", "هند", "India", "دهلی نو", "New Delhi", "INR", 1463870, 3287263, "hi,en", "AS"],
  ["ID", "اندونزی", "Indonesia", "جاکارتا", "Jakarta", "IDR", 286000, 1904569, "id", "AS"],
  ["IR", "ایران", "Iran", "تهران", "Tehran", "IRR", 92400, 1648195, "fa", "AS"],
  ["IQ", "عراق", "Iraq", "بغداد", "Baghdad", "IQD", 47000, 438317, "ar,ku", "AS"],
  ["IL", "اسرائیل", "Israel", "بیت‌المقدس (اعلام‌شده)", "Jerusalem (declared)", "ILS", 10100, 21937, "he", "AS"],
  ["JP", "ژاپن", "Japan", "توکیو", "Tokyo", "JPY", 123000, 377915, "ja", "AS"],
  ["JO", "اردن", "Jordan", "امان", "Amman", "JOD", 11500, 89342, "ar", "AS"],
  ["KZ", "قزاقستان", "Kazakhstan", "آستانه", "Astana", "KZT", 20800, 2724900, "kk,ru", "AS"],
  ["KW", "کویت", "Kuwait", "شهر کویت", "Kuwait City", "KWD", 4870, 17818, "ar", "AS"],
  ["KG", "قرقیزستان", "Kyrgyzstan", "بیشکک", "Bishkek", "KGS", 7340, 199951, "ky,ru", "AS"],
  ["LA", "لائوس", "Laos", "وینتیان", "Vientiane", "LAK", 7870, 236800, "lo", "AS"],
  ["LB", "لبنان", "Lebanon", "بیروت", "Beirut", "LBP", 5850, 10452, "ar", "AS"],
  ["MY", "مالزی", "Malaysia", "کوالالامپور", "Kuala Lumpur", "MYR", 36000, 330803, "ms", "AS"],
  ["MV", "مالدیو", "Maldives", "ماله", "Malé", "MVR", 530, 298, "dv", "AS"],
  ["MN", "مغولستان", "Mongolia", "اولان‌باتور", "Ulaanbaatar", "MNT", 3570, 1564116, "mn", "AS"],
  ["MM", "میانمار", "Myanmar", "نایپیداو", "Naypyidaw", "MMK", 54900, 676578, "my", "AS"],
  ["NP", "نپال", "Nepal", "کاتماندو", "Kathmandu", "NPR", 29600, 147181, "ne", "AS"],
  ["KP", "کره شمالی", "North Korea", "پیونگ‌یانگ", "Pyongyang", "KPW", 26600, 120538, "ko", "AS"],
  ["OM", "عمان", "Oman", "مسقط", "Muscat", "OMR", 5490, 309500, "ar", "AS"],
  ["PK", "پاکستان", "Pakistan", "اسلام‌آباد", "Islamabad", "PKR", 255000, 796095, "ur,en", "AS"],
  ["PS", "فلسطین", "Palestine", "رام‌الله (مقر اداری)", "Ramallah (administrative seat)", "ILS", 5410, 6020, "ar", "AS"],
  ["PH", "فیلیپین", "Philippines", "مانیل", "Manila", "PHP", 117000, 300000, "fil,en", "AS"],
  ["QA", "قطر", "Qatar", "دوحه", "Doha", "QAR", 2970, 11586, "ar", "AS"],
  ["SA", "عربستان سعودی", "Saudi Arabia", "ریاض", "Riyadh", "SAR", 37000, 2149690, "ar", "AS"],
  ["SG", "سنگاپور", "Singapore", "سنگاپور", "Singapore", "SGD", 6110, 734, "en,ms,zh,ta", "AS"],
  ["KR", "کره جنوبی", "South Korea", "سئول", "Seoul", "KRW", 51700, 100210, "ko", "AS"],
  ["LK", "سری‌لانکا", "Sri Lanka", "سری جایاواردنه‌پورا کوته", "Sri Jayawardenepura Kotte", "LKR", 21800, 65610, "si,ta", "AS"],
  ["SY", "سوریه", "Syria", "دمشق", "Damascus", "SYP", 25600, 185180, "ar", "AS"],
  ["TJ", "تاجیکستان", "Tajikistan", "دوشنبه", "Dushanbe", "TJS", 10800, 144100, "tg", "AS"],
  ["TH", "تایلند", "Thailand", "بانکوک", "Bangkok", "THB", 71600, 513120, "th", "AS"],
  ["TL", "تیمور شرقی", "Timor-Leste", "دیلی", "Dili", "USD", 1420, 14874, "pt,tet", "AS"],
  ["TR", "ترکیه", "Türkiye", "آنکارا", "Ankara", "TRY", 85900, 783562, "tr", "AS"],
  ["TM", "ترکمنستان", "Turkmenistan", "عشق‌آباد", "Ashgabat", "TMT", 7620, 488100, "tk", "AS"],
  ["AE", "امارات متحده عربی", "United Arab Emirates", "ابوظبی", "Abu Dhabi", "AED", 11500, 83600, "ar", "AS"],
  ["UZ", "ازبکستان", "Uzbekistan", "تاشکند", "Tashkent", "UZS", 37100, 447400, "uz", "AS"],
  ["VN", "ویتنام", "Vietnam", "هانوی", "Hanoi", "VND", 102000, 331212, "vi", "AS"],
  ["YE", "یمن", "Yemen", "صنعا", "Sanaa", "YER", 41800, 527968, "ar", "AS"],

  // ───────── Europe ─────────
  ["AL", "آلبانی", "Albania", "تیرانا", "Tirana", "ALL", 2350, 28748, "sq", "EU"],
  ["AD", "آندورا", "Andorra", "آندورا لا ویا", "Andorra la Vella", "EUR", 82.9, 468, "ca", "EU"],
  ["AT", "اتریش", "Austria", "وین", "Vienna", "EUR", 9210, 83871, "de", "EU"],
  ["BY", "بلاروس", "Belarus", "مینسک", "Minsk", "BYN", 9090, 207600, "be,ru", "EU"],
  ["BE", "بلژیک", "Belgium", "بروکسل", "Brussels", "EUR", 11900, 30528, "nl,fr,de", "EU"],
  ["BA", "بوسنی و هرزگوین", "Bosnia and Herzegovina", "سارایوو", "Sarajevo", "BAM", 3140, 51197, "bs,hr,sr", "EU"],
  ["BG", "بلغارستان", "Bulgaria", "صوفیه", "Sofia", "EUR", 6430, 110879, "bg", "EU"],
  ["HR", "کرواسی", "Croatia", "زاگرب", "Zagreb", "EUR", 3880, 56594, "hr", "EU"],
  ["CY", "قبرس", "Cyprus", "نیکوزیا", "Nicosia", "EUR", 1370, 9251, "el,tr", "EU"],
  ["CZ", "چک", "Czechia", "پراگ", "Prague", "CZK", 10900, 78867, "cs", "EU"],
  ["DK", "دانمارک", "Denmark", "کپنهاگ", "Copenhagen", "DKK", 6010, 43094, "da", "EU"],
  ["EE", "استونی", "Estonia", "تالین", "Tallinn", "EUR", 1370, 45228, "et", "EU"],
  ["FI", "فنلاند", "Finland", "هلسینکی", "Helsinki", "EUR", 5650, 338145, "fi,sv", "EU"],
  ["FR", "فرانسه", "France", "پاریس", "Paris", "EUR", 68700, 643801, "fr", "EU"],
  ["DE", "آلمان", "Germany", "برلین", "Berlin", "EUR", 83500, 357022, "de", "EU"],
  ["GR", "یونان", "Greece", "آتن", "Athens", "EUR", 10400, 131957, "el", "EU"],
  ["HU", "مجارستان", "Hungary", "بوداپست", "Budapest", "HUF", 9510, 93028, "hu", "EU"],
  ["IS", "ایسلند", "Iceland", "ریکیاویک", "Reykjavík", "ISK", 392, 103000, "is", "EU"],
  ["IE", "ایرلند", "Ireland", "دوبلین", "Dublin", "EUR", 5480, 70273, "ga,en", "EU"],
  ["IT", "ایتالیا", "Italy", "رم", "Rome", "EUR", 58900, 301340, "it", "EU"],
  ["LV", "لتونی", "Latvia", "ریگا", "Riga", "EUR", 1850, 64589, "lv", "EU"],
  ["LI", "لیختن‌اشتاین", "Liechtenstein", "فادوتس", "Vaduz", "CHF", 41, 160, "de", "EU"],
  ["LT", "لیتوانی", "Lithuania", "ویلنیوس", "Vilnius", "EUR", 2890, 65300, "lt", "EU"],
  ["LU", "لوکزامبورگ", "Luxembourg", "شهر لوکزامبورگ", "Luxembourg City", "EUR", 687, 2586, "lb,fr,de", "EU"],
  ["MT", "مالت", "Malta", "والتا", "Valletta", "EUR", 580, 316, "mt,en", "EU"],
  ["MD", "مولداوی", "Moldova", "کیشینف", "Chișinău", "MDL", 2360, 33851, "ro", "EU"],
  ["MC", "موناکو", "Monaco", "موناکو", "Monaco", "EUR", 38.3, 2.02, "fr", "EU"],
  ["ME", "مونته‌نگرو", "Montenegro", "پودگوریتسا", "Podgorica", "EUR", 623, 13812, "cnr,sr", "EU"],
  ["NL", "هلند", "Netherlands", "آمستردام", "Amsterdam", "EUR", 18100, 41850, "nl", "EU"],
  ["MK", "مقدونیه شمالی", "North Macedonia", "اسکوپیه", "Skopje", "MKD", 1820, 25713, "mk,sq", "EU"],
  ["NO", "نروژ", "Norway", "اسلو", "Oslo", "NOK", 5610, 385207, "no", "EU"],
  ["PL", "لهستان", "Poland", "ورشو", "Warsaw", "PLN", 36400, 312696, "pl", "EU"],
  ["PT", "پرتغال", "Portugal", "لیسبون", "Lisbon", "EUR", 10800, 92090, "pt", "EU"],
  ["RO", "رومانی", "Romania", "بخارست", "Bucharest", "RON", 19000, 238397, "ro", "EU"],
  ["RU", "روسیه", "Russia", "مسکو", "Moscow", "RUB", 144000, 17098246, "ru", "EU"],
  ["SM", "سن‌مارینو", "San Marino", "سن‌مارینو", "San Marino", "EUR", 34.1, 61, "it", "EU"],
  ["RS", "صربستان", "Serbia", "بلگراد", "Belgrade", "RSD", 6550, 88361, "sr", "EU"],
  ["SK", "اسلواکی", "Slovakia", "براتیسلاوا", "Bratislava", "EUR", 5410, 49035, "sk", "EU"],
  ["SI", "اسلوونی", "Slovenia", "لیوبلیانا", "Ljubljana", "EUR", 2130, 20273, "sl", "EU"],
  ["ES", "اسپانیا", "Spain", "مادرید", "Madrid", "EUR", 49400, 505990, "es", "EU"],
  ["SE", "سوئد", "Sweden", "استکهلم", "Stockholm", "SEK", 10600, 450295, "sv", "EU"],
  ["CH", "سوئیس", "Switzerland", "برن", "Bern", "CHF", 9090, 41284, "de,fr,it,rm", "EU"],
  ["UA", "اوکراین", "Ukraine", "کیف", "Kyiv", "UAH", 39000, 603550, "uk", "EU"],
  ["GB", "بریتانیا", "United Kingdom", "لندن", "London", "GBP", 69500, 242495, "en", "EU"],
  ["VA", "واتیکان", "Vatican City", "شهر واتیکان", "Vatican City", "EUR", 0.8, 0.49, "it,la", "EU"],

  // ───────── Africa ─────────
  ["DZ", "الجزایر", "Algeria", "الجزیره", "Algiers", "DZD", 47400, 2381741, "ar,ber", "AF"],
  ["AO", "آنگولا", "Angola", "لوآندا", "Luanda", "AOA", 39000, 1246700, "pt", "AF"],
  ["BJ", "بنین", "Benin", "پورتو نوو", "Porto-Novo", "XOF", 14800, 112622, "fr", "AF"],
  ["BW", "بوتسوانا", "Botswana", "گابورون", "Gaborone", "BWP", 2560, 581730, "en,tn", "AF"],
  ["BF", "بورکینافاسو", "Burkina Faso", "اوآگادوگو", "Ouagadougou", "XOF", 24100, 272967, "fr", "AF"],
  ["BI", "بوروندی", "Burundi", "گیتگا", "Gitega", "BIF", 14400, 27830, "rn,fr,en", "AF"],
  ["CV", "کیپ ورد", "Cabo Verde", "پرایا", "Praia", "CVE", 527, 4033, "pt", "AF"],
  ["CM", "کامرون", "Cameroon", "یائونده", "Yaoundé", "XAF", 29900, 475440, "fr,en", "AF"],
  ["CF", "جمهوری آفریقای مرکزی", "Central African Republic", "بانگی", "Bangui", "XAF", 5510, 622984, "fr,sg", "AF"],
  ["TD", "چاد", "Chad", "انجامنا", "N'Djamena", "XAF", 21000, 1284000, "fr,ar", "AF"],
  ["KM", "کومور", "Comoros", "مورونی", "Moroni", "KMF", 883, 2235, "zdj,ar,fr", "AF"],
  ["CG", "جمهوری کنگو", "Republic of the Congo", "برازاویل", "Brazzaville", "XAF", 6480, 342000, "fr", "AF"],
  ["CD", "جمهوری دموکراتیک کنگو", "DR Congo", "کینشاسا", "Kinshasa", "CDF", 113000, 2344858, "fr", "AF"],
  ["CI", "ساحل عاج", "Côte d'Ivoire", "یاموسوکرو", "Yamoussoukro", "XOF", 32700, 322463, "fr", "AF"],
  ["DJ", "جیبوتی", "Djibouti", "جیبوتی", "Djibouti", "DJF", 1180, 23200, "fr,ar", "AF"],
  ["EG", "مصر", "Egypt", "قاهره", "Cairo", "EGP", 118000, 1002000, "ar", "AF"],
  ["GQ", "گینه استوایی", "Equatorial Guinea", "سیوداد د لا پاز", "Ciudad de la Paz", "XAF", 1940, 28051, "es,fr,pt", "AF"],
  ["ER", "اریتره", "Eritrea", "اسمره", "Asmara", "ERN", 3610, 117600, "ti,ar,en", "AF"],
  ["SZ", "اسواتینی", "Eswatini", "مبابانه", "Mbabane", "SZL", 1260, 17364, "ss,en", "AF"],
  ["ET", "اتیوپی", "Ethiopia", "آدیس‌آبابا", "Addis Ababa", "ETB", 135000, 1104300, "am", "AF"],
  ["GA", "گابن", "Gabon", "لیبرویل", "Libreville", "XAF", 2590, 267668, "fr", "AF"],
  ["GM", "گامبیا", "Gambia", "بانجول", "Banjul", "GMD", 2820, 11300, "en", "AF"],
  ["GH", "غنا", "Ghana", "آکرا", "Accra", "GHS", 35100, 238533, "en", "AF"],
  ["GN", "گینه", "Guinea", "کوناکری", "Conakry", "GNF", 15100, 245857, "fr", "AF"],
  ["GW", "گینه بیسائو", "Guinea-Bissau", "بیسائو", "Bissau", "XOF", 2250, 36125, "pt", "AF"],
  ["KE", "کنیا", "Kenya", "نایروبی", "Nairobi", "KES", 57500, 580367, "sw,en", "AF"],
  ["LS", "لسوتو", "Lesotho", "ماسرو", "Maseru", "LSL", 2360, 30355, "st,en", "AF"],
  ["LR", "لیبریا", "Liberia", "مونروویا", "Monrovia", "LRD", 5730, 111369, "en", "AF"],
  ["LY", "لیبی", "Libya", "طرابلس", "Tripoli", "LYD", 7460, 1759540, "ar", "AF"],
  ["MG", "ماداگاسکار", "Madagascar", "آنتاناناریوو", "Antananarivo", "MGA", 32700, 587041, "mg,fr", "AF"],
  ["MW", "مالاوی", "Malawi", "لیلانگوه", "Lilongwe", "MWK", 22200, 118484, "en,ny", "AF"],
  ["ML", "مالی", "Mali", "باماکو", "Bamako", "XOF", 25200, 1240192, "fr", "AF"],
  ["MR", "موریتانی", "Mauritania", "نواکشوط", "Nouakchott", "MRU", 5320, 1030700, "ar", "AF"],
  ["MU", "موریس", "Mauritius", "پورت‌لوئیس", "Port Louis", "MUR", 1240, 2040, "en,fr", "AF"],
  ["MA", "مراکش", "Morocco", "رباط", "Rabat", "MAD", 38400, 446550, "ar,ber", "AF"],
  ["MZ", "موزامبیک", "Mozambique", "ماپوتو", "Maputo", "MZN", 35600, 799380, "pt", "AF"],
  ["NA", "نامیبیا", "Namibia", "ویندهوک", "Windhoek", "NAD", 3090, 825615, "en", "AF"],
  ["NE", "نیجر", "Niger", "نیامی", "Niamey", "XOF", 27900, 1267000, "fr", "AF"],
  ["NG", "نیجریه", "Nigeria", "ابوجا", "Abuja", "NGN", 238000, 923768, "en", "AF"],
  ["RW", "رواندا", "Rwanda", "کیگالی", "Kigali", "RWF", 14600, 26338, "rw,en,fr,sw", "AF"],
  ["ST", "سائوتومه و پرنسیپ", "São Tomé and Príncipe", "سائوتومه", "São Tomé", "STN", 240, 964, "pt", "AF"],
  ["SN", "سنگال", "Senegal", "داکار", "Dakar", "XOF", 18900, 196722, "fr", "AF"],
  ["SC", "سیشل", "Seychelles", "ویکتوریا", "Victoria", "SCR", 123, 457, "crs,en,fr", "AF"],
  ["SL", "سیرالئون", "Sierra Leone", "فری‌تاون", "Freetown", "SLE", 8820, 71740, "en", "AF"],
  ["SO", "سومالی", "Somalia", "موگادیشو", "Mogadishu", "SOS", 19700, 637657, "so,ar", "AF"],
  ["ZA", "آفریقای جنوبی", "South Africa", "پرتوریا (اجرایی)", "Pretoria (executive)", "ZAR", 64700, 1219090, "en,zu,xh,af", "AF"],
  ["SS", "سودان جنوبی", "South Sudan", "جوبا", "Juba", "SSP", 12200, 644329, "en", "AF"],
  ["SD", "سودان", "Sudan", "خارطوم", "Khartoum", "SDG", 51700, 1861484, "ar,en", "AF"],
  ["TZ", "تانزانیا", "Tanzania", "دودوما", "Dodoma", "TZS", 70500, 947300, "sw,en", "AF"],
  ["TG", "توگو", "Togo", "لومه", "Lomé", "XOF", 8590, 56785, "fr", "AF"],
  ["TN", "تونس", "Tunisia", "تونس", "Tunis", "TND", 12300, 163610, "ar", "AF"],
  ["UG", "اوگاندا", "Uganda", "کامپالا", "Kampala", "UGX", 51400, 241038, "en,sw", "AF"],
  ["ZM", "زامبیا", "Zambia", "لوساکا", "Lusaka", "ZMW", 21900, 752612, "en", "AF"],
  ["ZW", "زیمبابوه", "Zimbabwe", "هراره", "Harare", "ZWG", 17000, 390757, "en,sn,nd", "AF"],

  // ───────── North America (incl. Central America & Caribbean) ─────────
  ["AG", "آنتیگوا و باربودا", "Antigua and Barbuda", "سنت جانز", "Saint John's", "XCD", 94.2, 442, "en", "NA"],
  ["BS", "باهاما", "Bahamas", "ناسائو", "Nassau", "BSD", 403, 13943, "en", "NA"],
  ["BB", "باربادوس", "Barbados", "بریج‌تاون", "Bridgetown", "BBD", 283, 430, "en", "NA"],
  ["BZ", "بلیز", "Belize", "بلموپان", "Belmopan", "BZD", 423, 22966, "en", "NA"],
  ["CA", "کانادا", "Canada", "اتاوا", "Ottawa", "CAD", 41700, 9984670, "en,fr", "NA"],
  ["CR", "کاستاریکا", "Costa Rica", "سن‌خوسه", "San José", "CRC", 5150, 51100, "es", "NA"],
  ["CU", "کوبا", "Cuba", "هاوانا", "Havana", "CUP", 10900, 109884, "es", "NA"],
  ["DM", "دومینیکا", "Dominica", "روسو", "Roseau", "XCD", 65.9, 751, "en", "NA"],
  ["DO", "جمهوری دومینیکن", "Dominican Republic", "سانتو دومینگو", "Santo Domingo", "DOP", 11500, 48671, "es", "NA"],
  ["SV", "السالوادور", "El Salvador", "سن‌سالوادور", "San Salvador", "USD", 6370, 21041, "es", "NA"],
  ["GD", "گرنادا", "Grenada", "سنت جورجز", "St. George's", "XCD", 117, 344, "en", "NA"],
  ["GT", "گواتمالا", "Guatemala", "شهر گواتمالا", "Guatemala City", "GTQ", 18700, 108889, "es", "NA"],
  ["HT", "هائیتی", "Haiti", "پورتوپرنس", "Port-au-Prince", "HTG", 11900, 27750, "fr,ht", "NA"],
  ["HN", "هندوراس", "Honduras", "تگوسیگالپا", "Tegucigalpa", "HNL", 11000, 112492, "es", "NA"],
  ["JM", "جامائیکا", "Jamaica", "کینگستون", "Kingston", "JMD", 2840, 10991, "en", "NA"],
  ["MX", "مکزیک", "Mexico", "مکزیکوسیتی", "Mexico City", "MXN", 132000, 1964375, "es", "NA"],
  ["NI", "نیکاراگوئه", "Nicaragua", "ماناگوئه", "Managua", "NIO", 7010, 130370, "es", "NA"],
  ["PA", "پاناما", "Panama", "شهر پاناما", "Panama City", "PAB", 4570, 75417, "es", "NA"],
  ["KN", "سنت کیتس و نویس", "Saint Kitts and Nevis", "باستر", "Basseterre", "XCD", 46.9, 261, "en", "NA"],
  ["LC", "سنت لوسیا", "Saint Lucia", "کاستریز", "Castries", "XCD", 180, 616, "en", "NA"],
  ["VC", "سنت وینسنت و گرنادین‌ها", "Saint Vincent and the Grenadines", "کینگزتاون", "Kingstown", "XCD", 99.9, 389, "en", "NA"],
  ["TT", "ترینیداد و توباگو", "Trinidad and Tobago", "پورت آو اسپین", "Port of Spain", "TTD", 1370, 5130, "en", "NA"],
  ["US", "ایالات متحده آمریکا", "United States", "واشینگتن دی‌سی", "Washington, D.C.", "USD", 342000, 9833517, "en", "NA"],

  // ───────── South America ─────────
  ["AR", "آرژانتین", "Argentina", "بوئنوس آیرس", "Buenos Aires", "ARS", 45900, 2780400, "es", "SA"],
  ["BO", "بولیوی", "Bolivia", "سوکره (قانونی)", "Sucre (constitutional)", "BOB", 12600, 1098581, "es,qu,ay", "SA"],
  ["BR", "برزیل", "Brazil", "برازیلیا", "Brasília", "BRL", 213000, 8515767, "pt", "SA"],
  ["CL", "شیلی", "Chile", "سانتیاگو", "Santiago", "CLP", 19900, 756102, "es", "SA"],
  ["CO", "کلمبیا", "Colombia", "بوگوتا", "Bogotá", "COP", 53400, 1141748, "es", "SA"],
  ["EC", "اکوادور", "Ecuador", "کیتو", "Quito", "USD", 18300, 283561, "es", "SA"],
  ["GY", "گویان", "Guyana", "جورج‌تاون", "Georgetown", "GYD", 836, 214969, "en", "SA"],
  ["PY", "پاراگوئه", "Paraguay", "آسونسیون", "Asunción", "PYG", 7010, 406752, "es,gn", "SA"],
  ["PE", "پرو", "Peru", "لیما", "Lima", "PEN", 34600, 1285216, "es,qu,ay", "SA"],
  ["SR", "سورینام", "Suriname", "پاراماریبو", "Paramaribo", "SRD", 640, 163820, "nl", "SA"],
  ["UY", "اروگوئه", "Uruguay", "مونته‌ویدئو", "Montevideo", "UYU", 3380, 176215, "es", "SA"],
  ["VE", "ونزوئلا", "Venezuela", "کاراکاس", "Caracas", "VES", 28500, 916445, "es", "SA"],

  // ───────── Oceania ─────────
  ["AU", "استرالیا", "Australia", "کانبرا", "Canberra", "AUD", 27600, 7692024, "en", "OC"],
  ["FJ", "فیجی", "Fiji", "سوآ", "Suva", "FJD", 933, 18274, "en,fj,hif", "OC"],
  ["KI", "کیریباتی", "Kiribati", "تاراوای جنوبی", "South Tarawa", "AUD", 136, 811, "en,gil", "OC"],
  ["MH", "جزایر مارشال", "Marshall Islands", "ماجورو", "Majuro", "USD", 36.3, 181, "mh,en", "OC"],
  ["FM", "میکرونزی", "Micronesia", "پالیکیر", "Palikir", "USD", 114, 702, "en", "OC"],
  ["NR", "نائورو", "Naoero", "یارن (عملاً)", "Yaren (de facto)", "AUD", 12, 21, "na,en", "OC"],
  ["NZ", "نیوزیلند", "New Zealand", "ولینگتون", "Wellington", "NZD", 5320, 268838, "en,mi", "OC"],
  ["PW", "پالائو", "Palau", "انگرولمود", "Ngerulmud", "USD", 17.7, 459, "pau,en", "OC"],
  ["PG", "پاپوآ گینه نو", "Papua New Guinea", "پورت مورزبی", "Port Moresby", "PGK", 10800, 462840, "en,tpi,ho", "OC"],
  ["WS", "ساموآ", "Samoa", "آپیا", "Apia", "WST", 219, 2831, "sm,en", "OC"],
  ["SB", "جزایر سلیمان", "Solomon Islands", "هونیارا", "Honiara", "SBD", 839, 28896, "en", "OC"],
  ["TO", "تونگا", "Tonga", "نوکوالوفا", "Nukuʻalofa", "TOP", 104, 747, "to,en", "OC"],
  ["TV", "تووالو", "Tuvalu", "فونافوتی", "Funafuti", "AUD", 9.49, 26, "tvl,en", "OC"],
  ["VU", "وانواتو", "Vanuatu", "پورت ویلا", "Port Vila", "VUV", 335, 12189, "bi,en,fr", "OC"],
];

// Latin is not in the main dictionary (only Vatican uses it) – add it here.
const EXTRA_LANGS: Record<string, [string, string]> = { la: ["لاتین", "Latin"] };

export interface Country {
  isoCode: string;
  nameFa: string;
  nameEn: string;
  capitalFa: string;
  capitalEn: string;
  currencyCode: string;
  currencyNameFa: string;
  currencyNameEn: string;
  population: number;
  areaKm2: number;
  languagesFa: string;
  languagesEn: string;
  continent: Continent;
  continentFa: string;
  continentEn: string;
  flagEmoji: string;
  shortFactFa?: string;
  noteFa?: string;
  /** lower-cased, normalised haystack used by search */
  searchKey: string;
  popRank: number;
  areaRank: number;
}

/** Normalise Persian/Arabic variants so that search is forgiving. */
export function normalize(s: string): string {
  return s
    .toLowerCase()
    .replace(/[\u064A\u0649]/g, "ی")
    .replace(/\u0643/g, "ک")
    .replace(/[\u064B-\u065F\u0670\u200c\u200f]/g, "")
    .replace(/[آأإ]/g, "ا")
    .replace(/\u00e9|\u00e8/g, "e")
    .replace(/[^\p{L}\p{N} ]/gu, " ")
    .replace(/\s+/g, " ")
    .trim();
}

/** Unicode regional-indicator flag emoji from ISO alpha-2 code. */
function emojiFlag(iso: string): string {
  return String.fromCodePoint(...[...iso].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
}

const built: Country[] = RAW.map(([iso, fa, en, capFa, capEn, cur, popK, area, langs, cont]) => {
  const l = langs.split(",").map((k) => LANGS[k] ?? EXTRA_LANGS[k]);
  const c = CURRENCIES[cur];
  const country: Country = {
    isoCode: iso,
    nameFa: fa,
    nameEn: en,
    capitalFa: capFa,
    capitalEn: capEn,
    currencyCode: cur,
    currencyNameFa: c[0],
    currencyNameEn: c[1],
    population: Math.round(popK * 1000),
    areaKm2: area,
    languagesFa: l.map((x) => x[0]).join("، "),
    languagesEn: l.map((x) => x[1]).join(", "),
    continent: cont,
    continentFa: CONTINENTS[cont].fa,
    continentEn: CONTINENTS[cont].en,
    flagEmoji: emojiFlag(iso),
    shortFactFa: FACTS[iso],
    noteFa: NOTES[iso],
    searchKey: "",
    popRank: 0,
    areaRank: 0,
  };
  country.searchKey = normalize(
    [fa, en, capFa, capEn, c[0], c[1], cur, iso, country.languagesFa, country.languagesEn].join(" "),
  );
  return country;
});

// Global ranks (1 = largest)
[...built].sort((a, b) => b.population - a.population).forEach((c, i) => (c.popRank = i + 1));
[...built].sort((a, b) => b.areaKm2 - a.areaKm2).forEach((c, i) => (c.areaRank = i + 1));

/** All countries, alphabetical by English name (stable, deterministic). */
export const COUNTRIES: Country[] = built.sort((a, b) => a.nameEn.localeCompare(b.nameEn, "en"));
export const COUNTRY_MAP: Record<string, Country> = Object.fromEntries(COUNTRIES.map((c) => [c.isoCode, c]));
