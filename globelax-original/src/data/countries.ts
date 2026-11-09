// Globe.Lax – embedded, offline country dataset (195 countries:
// 193 UN member states + Vatican City + State of Palestine, the two UN observer states).
//
// Population: World Bank SP.POP.TOTL 2025; area: AG.SRF.TOTL.K2 2024
// (2023 for BA/NI); Vatican: original estimate. See audit/README.fa.md.
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
  ["AF", "افغانستان", "Afghanistan", "کابل", "Kabul", "AFN", 43844.111, 652870, "ps,prs", "AS"],
  ["AM", "ارمنستان", "Armenia", "ایروان", "Yerevan", "AMD", 3086.7, 29740, "hy", "AS"],
  ["AZ", "جمهوری آذربایجان", "Azerbaijan", "باکو", "Baku", "AZN", 10246.996, 86600, "az", "AS"],
  ["BH", "بحرین", "Bahrain", "منامه", "Manama", "BHD", 1600.366, 800, "ar", "AS"],
  ["BD", "بنگلادش", "Bangladesh", "داکا", "Dhaka", "BDT", 175686.899, 147570, "bn", "AS"],
  ["BT", "بوتان", "Bhutan", "تیمپو", "Thimphu", "BTN", 796.682, 38390, "dz", "AS"],
  ["BN", "برونئی", "Brunei", "بندر سری بگاوان", "Bandar Seri Begawan", "BND", 466.33, 5770, "ms", "AS"],
  ["KH", "کامبوج", "Cambodia", "پنوم‌پن", "Phnom Penh", "KHR", 17847.982, 181035, "km", "AS"],
  ["CN", "چین", "China", "پکن", "Beijing", "CNY", 1406585, 9562910, "zh", "AS"],
  ["GE", "گرجستان", "Georgia", "تفلیس", "Tbilisi", "GEL", 3935.766, 69700, "ka", "AS"],
  ["IN", "هند", "India", "دهلی نو", "New Delhi", "INR", 1463865.525, 3287263, "hi,en", "AS"],
  ["ID", "اندونزی", "Indonesia", "جاکارتا", "Jakarta", "IDR", 285721.236, 1916906.8, "id", "AS"],
  ["IR", "ایران", "Iran", "تهران", "Tehran", "IRR", 92417.681, 1745150, "fa", "AS"],
  ["IQ", "عراق", "Iraq", "بغداد", "Baghdad", "IQD", 47020.774, 435050, "ar,ku", "AS"],
  ["IL", "اسرائیل", "Israel", "بیت‌المقدس (اعلام‌شده)", "Jerusalem (declared)", "ILS", 10122.8, 22070, "he", "AS"],
  ["JP", "ژاپن", "Japan", "توکیو", "Tokyo", "JPY", 123366.734, 377974.71, "ja", "AS"],
  ["JO", "اردن", "Jordan", "امان", "Amman", "JOD", 11520.684, 89318, "ar", "AS"],
  ["KZ", "قزاقستان", "Kazakhstan", "آستانه", "Astana", "KZT", 20843.754, 2724902, "kk,ru", "AS"],
  ["KW", "کویت", "Kuwait", "شهر کویت", "Kuwait City", "KWD", 4865.298, 17820, "ar", "AS"],
  ["KG", "قرقیزستان", "Kyrgyzstan", "بیشکک", "Bishkek", "KGS", 7343.064, 199950, "ky,ru", "AS"],
  ["LA", "لائوس", "Laos", "وینتیان", "Vientiane", "LAK", 7873.046, 236800, "lo", "AS"],
  ["LB", "لبنان", "Lebanon", "بیروت", "Beirut", "LBP", 5849.421, 10452, "ar", "AS"],
  ["MY", "مالزی", "Malaysia", "کوالالامپور", "Kuala Lumpur", "MYR", 35977.838, 330534, "ms", "AS"],
  ["MV", "مالدیو", "Maldives", "ماله", "Malé", "MVR", 529.676, 300, "dv", "AS"],
  ["MN", "مغولستان", "Mongolia", "اولان‌باتور", "Ulaanbaatar", "MNT", 3568.978, 1564115.746, "mn", "AS"],
  ["MM", "میانمار", "Myanmar", "نایپیداو", "Naypyidaw", "MMK", 54850.648, 676578, "my", "AS"],
  ["NP", "نپال", "Nepal", "کاتماندو", "Kathmandu", "NPR", 29618.118, 147181, "ne", "AS"],
  ["KP", "کره شمالی", "North Korea", "پیونگ‌یانگ", "Pyongyang", "KPW", 26571.036, 120540, "ko", "AS"],
  ["OM", "عمان", "Oman", "مسقط", "Muscat", "OMR", 5494.691, 309500, "ar", "AS"],
  ["PK", "پاکستان", "Pakistan", "اسلام‌آباد", "Islamabad", "PKR", 255219.554, 796100, "ur,en", "AS"],
  ["PS", "فلسطین", "Palestine", "رام‌الله (مقر اداری)", "Ramallah (administrative seat)", "ILS", 5413.596, 6025, "ar", "AS"],
  ["PH", "فیلیپین", "Philippines", "مانیل", "Manila", "PHP", 116786.962, 300000, "fil,en", "AS"],
  ["QA", "قطر", "Qatar", "دوحه", "Doha", "QAR", 2972.215, 11636.8, "ar", "AS"],
  ["SA", "عربستان سعودی", "Saudi Arabia", "ریاض", "Riyadh", "SAR", 36973.555, 2149690, "ar", "AS"],
  ["SG", "سنگاپور", "Singapore", "سنگاپور", "Singapore", "SGD", 6111.175, 735.6, "en,ms,zh,ta", "AS"],
  ["KR", "کره جنوبی", "South Korea", "سئول", "Seoul", "KRW", 51684.564, 100460, "ko", "AS"],
  ["LK", "سری‌لانکا", "Sri Lanka", "سری جایاواردنه‌پورا کوته", "Sri Jayawardenepura Kotte", "LKR", 21756, 65610, "si,ta", "AS"],
  ["SY", "سوریه", "Syria", "دمشق", "Damascus", "SYP", 25620.427, 185180, "ar", "AS"],
  ["TJ", "تاجیکستان", "Tajikistan", "دوشنبه", "Dushanbe", "TJS", 10786.734, 141379, "tg", "AS"],
  ["TH", "تایلند", "Thailand", "بانکوک", "Bangkok", "THB", 71619.863, 513115, "th", "AS"],
  ["TL", "تیمور شرقی", "Timor-Leste", "دیلی", "Dili", "USD", 1418.517, 14870, "pt,tet", "AS"],
  ["TR", "ترکیه", "Türkiye", "آنکارا", "Ankara", "TRY", 85878.556, 785350, "tr", "AS"],
  ["TM", "ترکمنستان", "Turkmenistan", "عشق‌آباد", "Ashgabat", "TMT", 7618.847, 491209, "tk", "AS"],
  ["AE", "امارات متحده عربی", "United Arab Emirates", "ابوظبی", "Abu Dhabi", "AED", 11513.149, 98647.9, "ar", "AS"],
  ["UZ", "ازبکستان", "Uzbekistan", "تاشکند", "Tashkent", "UZS", 37053.428, 448923, "uz", "AS"],
  ["VN", "ویتنام", "Vietnam", "هانوی", "Hanoi", "VND", 101598.527, 331338.31, "vi", "AS"],
  ["YE", "یمن", "Yemen", "صنعا", "Sanaa", "YER", 41773.878, 527968, "ar", "AS"],

  // ───────── Europe ─────────
  ["AL", "آلبانی", "Albania", "تیرانا", "Tirana", "ALL", 2349.58, 28750, "sq", "EU"],
  ["AD", "آندورا", "Andorra", "آندورا لا ویا", "Andorra la Vella", "EUR", 82.904, 468, "ca", "EU"],
  ["AT", "اتریش", "Austria", "وین", "Vienna", "EUR", 9208.163, 83879, "de", "EU"],
  ["BY", "بلاروس", "Belarus", "مینسک", "Minsk", "BYN", 9085.991, 207629, "be,ru", "EU"],
  ["BE", "بلژیک", "Belgium", "بروکسل", "Brussels", "EUR", 11941.781, 30689.17, "nl,fr,de", "EU"],
  ["BA", "بوسنی و هرزگوین", "Bosnia and Herzegovina", "سارایوو", "Sarajevo", "BAM", 3140.095, 51210, "bs,hr,sr", "EU"],
  ["BG", "بلغارستان", "Bulgaria", "صوفیه", "Sofia", "EUR", 6433.302, 111000, "bg", "EU"],
  ["HR", "کرواسی", "Croatia", "زاگرب", "Zagreb", "EUR", 3876.2, 88070, "hr", "EU"],
  ["CY", "قبرس", "Cyprus", "نیکوزیا", "Nicosia", "EUR", 1370.754, 9250, "el,tr", "EU"],
  ["CZ", "چک", "Czechia", "پراگ", "Prague", "CZK", 10886.878, 78871.42, "cs", "EU"],
  ["DK", "دانمارک", "Denmark", "کپنهاگ", "Copenhagen", "DKK", 6009.169, 42920, "da", "EU"],
  ["EE", "استونی", "Estonia", "تالین", "Tallinn", "EUR", 1366.475, 45340, "et", "EU"],
  ["FI", "فنلاند", "Finland", "هلسینکی", "Helsinki", "EUR", 5646.436, 338485, "fi,sv", "EU"],
  ["FR", "فرانسه", "France", "پاریس", "Paris", "EUR", 68720.337, 606410, "fr", "EU"],
  ["DE", "آلمان", "Germany", "برلین", "Berlin", "EUR", 83491.249, 357680, "de", "EU"],
  ["GR", "یونان", "Greece", "آتن", "Athens", "EUR", 10413.962, 131960, "el", "EU"],
  ["HU", "مجارستان", "Hungary", "بوداپست", "Budapest", "HUF", 9514.251, 93030, "hu", "EU"],
  ["IS", "ایسلند", "Iceland", "ریکیاویک", "Reykjavík", "ISK", 392.404, 103000, "is", "EU"],
  ["IE", "ایرلند", "Ireland", "دوبلین", "Dublin", "EUR", 5484.367, 70280, "ga,en", "EU"],
  ["IT", "ایتالیا", "Italy", "رم", "Rome", "EUR", 58915.656, 302070, "it", "EU"],
  ["LV", "لتونی", "Latvia", "ریگا", "Riga", "EUR", 1847.785, 64590, "lv", "EU"],
  ["LI", "لیختن‌اشتاین", "Liechtenstein", "فادوتس", "Vaduz", "CHF", 41.024, 160, "de", "EU"],
  ["LT", "لیتوانی", "Lithuania", "ویلنیوس", "Vilnius", "EUR", 2888.774, 65290, "lt", "EU"],
  ["LU", "لوکزامبورگ", "Luxembourg", "شهر لوکزامبورگ", "Luxembourg City", "EUR", 686.97, 2590, "lb,fr,de", "EU"],
  ["MT", "مالت", "Malta", "والتا", "Valletta", "EUR", 579.704, 315.15, "mt,en", "EU"],
  ["MD", "مولداوی", "Moldova", "کیشینف", "Chișinău", "MDL", 2360.527, 33849, "ro", "EU"],
  ["MC", "موناکو", "Monaco", "موناکو", "Monaco", "EUR", 38.341, 74.9, "fr", "EU"],
  ["ME", "مونته‌نگرو", "Montenegro", "پودگوریتسا", "Podgorica", "EUR", 623.129, 13883, "cnr,sr", "EU"],
  ["NL", "هلند", "Netherlands", "آمستردام", "Amsterdam", "EUR", 18087.633, 41540, "nl", "EU"],
  ["MK", "مقدونیه شمالی", "North Macedonia", "اسکوپیه", "Skopje", "MKD", 1820.909, 25713, "mk,sq", "EU"],
  ["NO", "نروژ", "Norway", "اسلو", "Oslo", "NOK", 5610.87, 624500, "no", "EU"],
  ["PL", "لهستان", "Poland", "ورشو", "Warsaw", "PLN", 36435.861, 313940, "pl", "EU"],
  ["PT", "پرتغال", "Portugal", "لیسبون", "Lisbon", "EUR", 10804.871, 92230, "pt", "EU"],
  ["RO", "رومانی", "Romania", "بخارست", "Bucharest", "RON", 19020.271, 238397, "ro", "EU"],
  ["RU", "روسیه", "Russia", "مسکو", "Moscow", "RUB", 143513.328, 17125190, "ru", "EU"],
  ["SM", "سن‌مارینو", "San Marino", "سن‌مارینو", "San Marino", "EUR", 34.109, 61.2, "it", "EU"],
  ["RS", "صربستان", "Serbia", "بلگراد", "Belgrade", "RSD", 6549.143, 88490, "sr", "EU"],
  ["SK", "اسلواکی", "Slovakia", "براتیسلاوا", "Bratislava", "EUR", 5413.813, 49030, "sk", "EU"],
  ["SI", "اسلوونی", "Slovenia", "لیوبلیانا", "Ljubljana", "EUR", 2130.986, 20480, "sl", "EU"],
  ["ES", "اسپانیا", "Spain", "مادرید", "Madrid", "EUR", 49355.143, 505981.25, "es", "EU"],
  ["SE", "سوئد", "Sweden", "استکهلم", "Stockholm", "SEK", 10596.62, 528860, "sv", "EU"],
  ["CH", "سوئیس", "Switzerland", "برن", "Bern", "CHF", 9092.436, 41290.7, "de,fr,it,rm", "EU"],
  ["UA", "اوکراین", "Ukraine", "کیف", "Kyiv", "UAH", 38980.376, 603550, "uk", "EU"],
  ["GB", "بریتانیا", "United Kingdom", "لندن", "London", "GBP", 69487, 243610, "en", "EU"],
  ["VA", "واتیکان", "Vatican City", "شهر واتیکان", "Vatican City", "EUR", 0.8, 0.49, "it,la", "EU"],

  // ───────── Africa ─────────
  ["DZ", "الجزایر", "Algeria", "الجزیره", "Algiers", "DZD", 47435.312, 2381740, "ar,ber", "AF"],
  ["AO", "آنگولا", "Angola", "لوآندا", "Luanda", "AOA", 39040.039, 1246700, "pt", "AF"],
  ["BJ", "بنین", "Benin", "پورتو نوو", "Porto-Novo", "XOF", 14814.46, 114760, "fr", "AF"],
  ["BW", "بوتسوانا", "Botswana", "گابورون", "Gaborone", "BWP", 2562.122, 581730, "en,tn", "AF"],
  ["BF", "بورکینافاسو", "Burkina Faso", "اوآگادوگو", "Ouagadougou", "XOF", 24074.58, 274200, "fr", "AF"],
  ["BI", "بوروندی", "Burundi", "گیتگا", "Gitega", "BIF", 14390.003, 27834, "rn,fr,en", "AF"],
  ["CV", "کیپ ورد", "Cabo Verde", "پرایا", "Praia", "CVE", 527.326, 4030, "pt", "AF"],
  ["CM", "کامرون", "Cameroon", "یائونده", "Yaoundé", "XAF", 29879.337, 475442, "fr,en", "AF"],
  ["CF", "جمهوری آفریقای مرکزی", "Central African Republic", "بانگی", "Bangui", "XAF", 5513.282, 622980, "fr,sg", "AF"],
  ["TD", "چاد", "Chad", "انجامنا", "N'Djamena", "XAF", 21003.705, 1284000, "fr,ar", "AF"],
  ["KM", "کومور", "Comoros", "مورونی", "Moroni", "KMF", 882.847, 1869.7, "zdj,ar,fr", "AF"],
  ["CG", "جمهوری کنگو", "Republic of the Congo", "برازاویل", "Brazzaville", "XAF", 6484.437, 342000, "fr", "AF"],
  ["CD", "جمهوری دموکراتیک کنگو", "DR Congo", "کینشاسا", "Kinshasa", "CDF", 112832.473, 2345410, "fr", "AF"],
  ["CI", "ساحل عاج", "Côte d'Ivoire", "یاموسوکرو", "Yamoussoukro", "XOF", 32711.547, 322460, "fr", "AF"],
  ["DJ", "جیبوتی", "Djibouti", "جیبوتی", "Djibouti", "DJF", 1184.076, 23200, "fr,ar", "AF"],
  ["EG", "مصر", "Egypt", "قاهره", "Cairo", "EGP", 118365.995, 1001450, "ar", "AF"],
  ["GQ", "گینه استوایی", "Equatorial Guinea", "سیوداد د لا پاز", "Ciudad de la Paz", "XAF", 1938.431, 28051.5, "es,fr,pt", "AF"],
  ["ER", "اریتره", "Eritrea", "اسمره", "Asmara", "ERN", 3607.003, 121630, "ti,ar,en", "AF"],
  ["SZ", "اسواتینی", "Eswatini", "مبابانه", "Mbabane", "SZL", 1256.174, 17364, "ss,en", "AF"],
  ["ET", "اتیوپی", "Ethiopia", "آدیس‌آبابا", "Addis Ababa", "ETB", 135472.051, 1129818.8, "am", "AF"],
  ["GA", "گابن", "Gabon", "لیبرویل", "Libreville", "XAF", 2593.13, 267670, "fr", "AF"],
  ["GM", "گامبیا", "Gambia", "بانجول", "Banjul", "GMD", 2822.093, 11295, "en", "AF"],
  ["GH", "غنا", "Ghana", "آکرا", "Accra", "GHS", 35064.272, 238539, "en", "AF"],
  ["GN", "گینه", "Guinea", "کوناکری", "Conakry", "GNF", 15099.727, 245857, "fr", "AF"],
  ["GW", "گینه بیسائو", "Guinea-Bissau", "بیسائو", "Bissau", "XOF", 2249.515, 36125, "pt", "AF"],
  ["KE", "کنیا", "Kenya", "نایروبی", "Nairobi", "KES", 57532.493, 591970, "sw,en", "AF"],
  ["LS", "لسوتو", "Lesotho", "ماسرو", "Maseru", "LSL", 2363.325, 30360, "st,en", "AF"],
  ["LR", "لیبریا", "Liberia", "مونروویا", "Monrovia", "LRD", 5731.206, 111370, "en", "AF"],
  ["LY", "لیبی", "Libya", "طرابلس", "Tripoli", "LYD", 7458.555, 1759540, "ar", "AF"],
  ["MG", "ماداگاسکار", "Madagascar", "آنتاناناریوو", "Antananarivo", "MGA", 32740.678, 591896, "mg,fr", "AF"],
  ["MW", "مالاوی", "Malawi", "لیلانگوه", "Lilongwe", "MWK", 22216.12, 118484, "en,ny", "AF"],
  ["ML", "مالی", "Mali", "باماکو", "Bamako", "XOF", 25198.821, 1241238, "fr", "AF"],
  ["MR", "موریتانی", "Mauritania", "نواکشوط", "Nouakchott", "MRU", 5315.065, 1030700, "ar", "AF"],
  ["MU", "موریس", "Mauritius", "پورت‌لوئیس", "Port Louis", "MUR", 1243.741, 2010, "en,fr", "AF"],
  ["MA", "مراکش", "Morocco", "رباط", "Rabat", "MAD", 38430.77, 446550, "ar,ber", "AF"],
  ["MZ", "موزامبیک", "Mozambique", "ماپوتو", "Maputo", "MZN", 35631.653, 799380, "pt", "AF"],
  ["NA", "نامیبیا", "Namibia", "ویندهوک", "Windhoek", "NAD", 3092.816, 824292, "en", "AF"],
  ["NE", "نیجر", "Niger", "نیامی", "Niamey", "XOF", 27917.831, 1267000, "fr", "AF"],
  ["NG", "نیجریه", "Nigeria", "ابوجا", "Abuja", "NGN", 237527.782, 923768, "en", "AF"],
  ["RW", "رواندا", "Rwanda", "کیگالی", "Kigali", "RWF", 14569.341, 26340, "rw,en,fr,sw", "AF"],
  ["ST", "سائوتومه و پرنسیپ", "São Tomé and Príncipe", "سائوتومه", "São Tomé", "STN", 240.254, 960, "pt", "AF"],
  ["SN", "سنگال", "Senegal", "داکار", "Dakar", "XOF", 18931.966, 196710, "fr", "AF"],
  ["SC", "سیشل", "Seychelles", "ویکتوریا", "Victoria", "SCR", 122.73, 457, "crs,en,fr", "AF"],
  ["SL", "سیرالئون", "Sierra Leone", "فری‌تاون", "Freetown", "SLE", 8819.794, 72300, "en", "AF"],
  ["SO", "سومالی", "Somalia", "موگادیشو", "Mogadishu", "SOS", 19654.739, 637657, "so,ar", "AF"],
  ["ZA", "آفریقای جنوبی", "South Africa", "پرتوریا (اجرایی)", "Pretoria (executive)", "ZAR", 64747.319, 1219602, "en,zu,xh,af", "AF"],
  ["SS", "سودان جنوبی", "South Sudan", "جوبا", "Juba", "SSP", 12188.788, 646883, "en", "AF"],
  ["SD", "سودان", "Sudan", "خارطوم", "Khartoum", "SDG", 51662.147, 1878000, "ar,en", "AF"],
  ["TZ", "تانزانیا", "Tanzania", "دودوما", "Dodoma", "TZS", 70545.865, 945500, "sw,en", "AF"],
  ["TG", "توگو", "Togo", "لومه", "Lomé", "XOF", 8591.626, 56785, "fr", "AF"],
  ["TN", "تونس", "Tunisia", "تونس", "Tunis", "TND", 12348.573, 163610, "ar", "AF"],
  ["UG", "اوگاندا", "Uganda", "کامپالا", "Kampala", "UGX", 51384.894, 241551, "en,sw", "AF"],
  ["ZM", "زامبیا", "Zambia", "لوساکا", "Lusaka", "ZMW", 21913.874, 752612, "en", "AF"],
  ["ZW", "زیمبابوه", "Zimbabwe", "هراره", "Harare", "ZWG", 16950.795, 390760, "en,sn,nd", "AF"],

  // ───────── North America (incl. Central America & Caribbean) ─────────
  ["AG", "آنتیگوا و باربودا", "Antigua and Barbuda", "سنت جانز", "Saint John's", "XCD", 94.209, 440.298, "en", "NA"],
  ["BS", "باهاما", "Bahamas", "ناسائو", "Nassau", "BSD", 403.033, 13878, "en", "NA"],
  ["BB", "باربادوس", "Barbados", "بریج‌تاون", "Bridgetown", "BBD", 282.623, 431, "en", "NA"],
  ["BZ", "بلیز", "Belize", "بلموپان", "Belmopan", "BZD", 422.924, 22966, "en", "NA"],
  ["CA", "کانادا", "Canada", "اتاوا", "Ottawa", "CAD", 41651.653, 15634410, "en,fr", "NA"],
  ["CR", "کاستاریکا", "Costa Rica", "سن‌خوسه", "San José", "CRC", 5152.95, 51100, "es", "NA"],
  ["CU", "کوبا", "Cuba", "هاوانا", "Havana", "CUP", 10937.203, 109884.01, "es", "NA"],
  ["DM", "دومینیکا", "Dominica", "روسو", "Roseau", "XCD", 65.871, 751, "en", "NA"],
  ["DO", "جمهوری دومینیکن", "Dominican Republic", "سانتو دومینگو", "Santo Domingo", "DOP", 11520.487, 146839.46, "es", "NA"],
  ["SV", "السالوادور", "El Salvador", "سن‌سالوادور", "San Salvador", "USD", 6365.503, 21040, "es", "NA"],
  ["GD", "گرنادا", "Grenada", "سنت جورجز", "St. George's", "XCD", 117.303, 344, "en", "NA"],
  ["GT", "گواتمالا", "Guatemala", "شهر گواتمالا", "Guatemala City", "GTQ", 18687.881, 108889, "es", "NA"],
  ["HT", "هائیتی", "Haiti", "پورتوپرنس", "Port-au-Prince", "HTG", 11906.095, 27750, "fr,ht", "NA"],
  ["HN", "هندوراس", "Honduras", "تگوسیگالپا", "Tegucigalpa", "HNL", 11005.85, 112492, "es", "NA"],
  ["JM", "جامائیکا", "Jamaica", "کینگستون", "Kingston", "JMD", 2837.077, 10991, "en", "NA"],
  ["MX", "مکزیک", "Mexico", "مکزیکوسیتی", "Mexico City", "MXN", 131946.9, 1957193.9, "es", "NA"],
  ["NI", "نیکاراگوئه", "Nicaragua", "ماناگوئه", "Managua", "NIO", 7007.502, 130370, "es", "NA"],
  ["PA", "پاناما", "Panama", "شهر پاناما", "Panama City", "PAB", 4571.189, 75320, "es", "NA"],
  ["KN", "سنت کیتس و نویس", "Saint Kitts and Nevis", "باستر", "Basseterre", "XCD", 46.922, 261, "en", "NA"],
  ["LC", "سنت لوسیا", "Saint Lucia", "کاستریز", "Castries", "XCD", 180.149, 616, "en", "NA"],
  ["VC", "سنت وینسنت و گرنادین‌ها", "Saint Vincent and the Grenadines", "کینگزتاون", "Kingstown", "XCD", 99.924, 389, "en", "NA"],
  ["TT", "ترینیداد و توباگو", "Trinidad and Tobago", "پورت آو اسپین", "Port of Spain", "TTD", 1367.764, 5128, "en", "NA"],
  ["US", "ایالات متحده آمریکا", "United States", "واشینگتن دی‌سی", "Washington, D.C.", "USD", 341784.857, 9831510, "en", "NA"],

  // ───────── South America ─────────
  ["AR", "آرژانتین", "Argentina", "بوئنوس آیرس", "Buenos Aires", "ARS", 45851.378, 2780400, "es", "SA"],
  ["BO", "بولیوی", "Bolivia", "سوکره (قانونی)", "Sucre (constitutional)", "BOB", 12581.843, 1098580, "es,qu,ay", "SA"],
  ["BR", "برزیل", "Brazil", "برازیلیا", "Brasília", "BRL", 212812.405, 8510420, "pt", "SA"],
  ["CL", "شیلی", "Chile", "سانتیاگو", "Santiago", "CLP", 19859.921, 756556.4, "es", "SA"],
  ["CO", "کلمبیا", "Colombia", "بوگوتا", "Bogotá", "COP", 53425.635, 1140619.1, "es", "SA"],
  ["EC", "اکوادور", "Ecuador", "کیتو", "Quito", "USD", 18289.896, 256370, "es", "SA"],
  ["GY", "گویان", "Guyana", "جورج‌تاون", "Georgetown", "GYD", 835.986, 214970, "en", "SA"],
  ["PY", "پاراگوئه", "Paraguay", "آسونسیون", "Asunción", "PYG", 7013.078, 406752, "es,gn", "SA"],
  ["PE", "پرو", "Peru", "لیما", "Lima", "PEN", 34576.665, 1285215.6, "es,qu,ay", "SA"],
  ["SR", "سورینام", "Suriname", "پاراماریبو", "Paramaribo", "SRD", 639.85, 163820, "nl", "SA"],
  ["UY", "اروگوئه", "Uruguay", "مونته‌ویدئو", "Montevideo", "UYU", 3384.688, 176215, "es", "SA"],
  ["VE", "ونزوئلا", "Venezuela", "کاراکاس", "Caracas", "VES", 28516.896, 912050, "es", "SA"],

  // ───────── Oceania ─────────
  ["AU", "استرالیا", "Australia", "کانبرا", "Canberra", "AUD", 27614.411, 7741220, "en", "OC"],
  ["FJ", "فیجی", "Fiji", "سوآ", "Suva", "FJD", 933.154, 18333, "en,fj,hif", "OC"],
  ["KI", "کیریباتی", "Kiribati", "تاراوای جنوبی", "South Tarawa", "AUD", 136.488, 811, "en,gil", "OC"],
  ["MH", "جزایر مارشال", "Marshall Islands", "ماجورو", "Majuro", "USD", 36.282, 181, "mh,en", "OC"],
  ["FM", "میکرونزی", "Micronesia", "پالیکیر", "Palikir", "USD", 113.683, 701, "en", "OC"],
  ["NR", "نائورو", "Nauru", "یارن (عملاً)", "Yaren (de facto)", "AUD", 12.025, 21, "na,en", "OC"],
  ["NZ", "نیوزیلند", "New Zealand", "ولینگتون", "Wellington", "NZD", 5324.7, 267710, "en,mi", "OC"],
  ["PW", "پالائو", "Palau", "انگرولمود", "Ngerulmud", "USD", 17.663, 460, "pau,en", "OC"],
  ["PG", "پاپوآ گینه نو", "Papua New Guinea", "پورت مورزبی", "Port Moresby", "PGK", 10762.817, 462840, "en,tpi,ho", "OC"],
  ["WS", "ساموآ", "Samoa", "آپیا", "Apia", "WST", 219.306, 2840, "sm,en", "OC"],
  ["SB", "جزایر سلیمان", "Solomon Islands", "هونیارا", "Honiara", "SBD", 838.645, 28446, "en", "OC"],
  ["TO", "تونگا", "Tonga", "نوکوالوفا", "Nukuʻalofa", "TOP", 103.742, 749, "to,en", "OC"],
  ["TV", "تووالو", "Tuvalu", "فونافوتی", "Funafuti", "AUD", 9.492, 26, "tvl,en", "OC"],
  ["VU", "وانواتو", "Vanuatu", "پورت ویلا", "Port Vila", "VUV", 335.169, 12189, "bi,en,fr", "OC"],
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
built.forEach((c) => {
  c.popRank = 1 + built.filter((other) => other.population > c.population).length;
  c.areaRank = 1 + built.filter((other) => other.areaKm2 > c.areaKm2).length;
});

/** All countries, alphabetical by English name (stable, deterministic). */
export const COUNTRIES: Country[] = built.sort((a, b) => a.nameEn.localeCompare(b.nameEn, "en"));
export const COUNTRY_MAP: Record<string, Country> = Object.fromEntries(COUNTRIES.map((c) => [c.isoCode, c]));
