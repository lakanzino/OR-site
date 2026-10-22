<?php
/**
 * Master content roadmap catalog & live progress matcher for Hamyar Jarahi.
 *
 * Contains all 184 base target articles (+ 26 optional 4th surgery articles = 210)
 * across the 10 color-coded editorial sections + Glossary target tracking.
 *
 * @package Lax_Theme_Child
 * @since   0.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Color-coded editorial sections for the Goals & Progress Dashboard.
 *
 * @return array<string,array<string,mixed>>
 */
function hj_roadmap_sections(): array {
	return array(
		'pillars' => array(
			'key'         => 'pillars',
			'order'       => 1,
			'title'       => '۱. ستون‌های اصلی (Pillars)',
			'short_title' => 'ستون‌های اصلی',
			'post_type'   => 'patient_guide',
			'taxonomy'    => 'guide_stage',
			'term'        => 'pillars',
			'color'       => '#06265C',
			'bg'          => '#EEF2FA',
			'icon'        => '🏛️',
			'description' => 'چهار ستون مادر که تمام خوشه‌ها و مقالات سایت به آن‌ها لینک می‌دهند.',
		),
		'guide_before' => array(
			'key'         => 'guide_before',
			'order'       => 2,
			'title'       => '۲. راهنمای بیمار — قبل از عمل',
			'short_title' => 'قبل از عمل',
			'post_type'   => 'patient_guide',
			'taxonomy'    => 'guide_stage',
			'term'        => 'before',
			'color'       => '#0284C7',
			'bg'          => '#E0F2FE',
			'icon'        => '📋',
			'description' => 'آمادگی جسمی و روانی، آزمایش‌ها، ناشتایی، داروها، رضایت‌نامه و روز پذیرش.',
		),
		'guide_during' => array(
			'key'         => 'guide_during',
			'order'       => 3,
			'title'       => '۳. راهنمای بیمار — حین عمل و بیهوشی',
			'short_title' => 'حین عمل و بیهوشی',
			'post_type'   => 'patient_guide',
			'taxonomy'    => 'guide_stage',
			'term'        => 'during',
			'color'       => '#4F46E5',
			'bg'          => '#EEF2FF',
			'icon'        => '🩺',
			'description' => 'آنچه در اتاق عمل و ریکاوری می‌گذرد، انواع بیهوشی و ایمنی بیمار.',
		),
		'guide_after' => array(
			'key'         => 'guide_after',
			'order'       => 4,
			'title'       => '۴. راهنمای بیمار — بعد از عمل و ریکاوری',
			'short_title' => 'بعد از عمل',
			'post_type'   => 'patient_guide',
			'taxonomy'    => 'guide_stage',
			'term'        => 'after',
			'color'       => '#0D9488',
			'bg'          => '#CCFBF1',
			'icon'        => '🌿',
			'description' => 'کنترل درد، راه رفتن، تغذیه، مراقبت زخم، بخیه، علائم خطر و بازگشت به زندگی.',
		),
		'guide_special' => array(
			'key'         => 'guide_special',
			'order'       => 5,
			'title'       => '۵. گروه‌های ویژه و تصمیم‌گیری مسیر',
			'short_title' => 'گروه‌های ویژه و مسیر',
			'post_type'   => 'patient_guide',
			'taxonomy'    => 'guide_stage',
			'term'        => 'special-groups',
			'color'       => '#D97706',
			'bg'          => '#FEF3C7',
			'icon'        => '👨‍👩‍👧',
			'description' => 'کودکان، سالمندان، بارداری، اورژانسی/انتخابی، سرپایی/بستری، بیمه و نظر دوم.',
		),
		'or_env' => array(
			'key'         => 'or_env',
			'order'       => 6,
			'title'       => '۶. محیط، تیم و تجهیزات اتاق عمل',
			'short_title' => 'اتاق عمل و تجهیزات',
			'post_type'   => 'or_environment',
			'taxonomy'    => 'env_section',
			'term'        => 'team',
			'color'       => '#7C3AED',
			'bg'          => '#F3E8FF',
			'icon'        => '🏥',
			'description' => 'اعضای تیم جراحی، فضاهای اتاق عمل/PACU/CSSD و دستگاه‌های اتاق عمل به زبان ساده.',
		),
		'surgery_g1' => array(
			'key'         => 'surgery_g1',
			'order'       => 7,
			'title'       => '۷. جراحی‌ها — گروه ۱: ۱۲ عمل شایع',
			'short_title' => 'جراحی‌ها (گروه ۱)',
			'post_type'   => 'surgery',
			'taxonomy'    => 'surgery_stage',
			'term'        => 'surgery-overview',
			'color'       => '#E11D48',
			'bg'          => '#FFE4E6',
			'icon'        => '🩹',
			'description' => '۱۲ عمل پرتکرار (هر عمل ۳ مقاله‌ی معرفی، قبل و بعد + ۱ مقاله‌ی چهارم اختیاری).',
		),
		'surgery_g2' => array(
			'key'         => 'surgery_g2',
			'order'       => 8,
			'title'       => '۸. جراحی‌ها — گروه ۲: ۱۴ عمل رایج دیگر',
			'short_title' => 'جراحی‌ها (گروه ۲)',
			'post_type'   => 'surgery',
			'taxonomy'    => 'surgery_stage',
			'term'        => 'surgery-overview',
			'color'       => '#EA580C',
			'bg'          => '#FFEDD5',
			'icon'        => '🔬',
			'description' => '۱۴ عمل رایج تخصصی (هر عمل ۳ مقاله‌ی استاندارد + ۱ مقاله‌ی چهارم اختیاری).',
		),
		'surgery_g3' => array(
			'key'         => 'surgery_g3',
			'order'       => 9,
			'title'       => '۹. جراحی‌ها — گروه ۳: ۵ عمل فوق‌تخصصی',
			'short_title' => 'جراحی‌ها (گروه ۳)',
			'post_type'   => 'surgery',
			'taxonomy'    => 'surgery_stage',
			'term'        => 'surgery-overview',
			'color'       => '#9F1239',
			'bg'          => '#FCE7F3',
			'icon'        => '🫀',
			'description' => '۵ دسته‌ی جراحی سنگین و تخصصی (قلب باز، تعویض مفصل، مغز/ستون فقرات، شکستگی، پیوند).',
		),
		'student' => array(
			'key'         => 'student',
			'order'       => 10,
			'title'       => '۱۰. ویژه‌ی دانشجویان اتاق عمل و هوشبری',
			'short_title' => 'دانشجویان اتاق عمل',
			'post_type'   => 'student_content',
			'taxonomy'    => 'student_topic',
			'term'        => 'asepsis',
			'color'       => '#0F766E',
			'bg'          => '#CCFBF1',
			'icon'        => '🎓',
			'description' => '۱۴ مقاله‌ی آموزشی حرفه‌ای با برچسب مجزا برای دانشجویان اتاق عمل و هوشبری.',
		),
	);
}

/**
 * Full list of target roadmap items (184 base + 26 optional surgery extras = 210).
 *
 * @return array<int,array<string,mixed>>
 */
function hj_roadmap_items(): array {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	$items = array();
	$num   = 0;

	$add = static function (
		string $section,
		string $slug,
		string $title,
		string $post_type,
		string $tax,
		string $term,
		int $phase = 1,
		array $aliases = array(),
		array $keywords = array(),
		bool $optional = false,
		array $extra_tax = array()
	) use ( &$items, &$num ) {
		if ( ! $optional ) {
			++$num;
			$display_num = (string) $num;
		} else {
			$display_num = $num . '+';
		}
		$items[] = array(
			'id'          => 'hj-' . $slug,
			'num'         => $display_num,
			'base_index'  => $optional ? 0 : $num,
			'section'     => $section,
			'slug'        => $slug,
			'aliases'     => $aliases,
			'title'       => $title,
			'post_type'   => $post_type,
			'taxonomy'    => $tax,
			'term'        => $term,
			'extra_tax'   => $extra_tax,
			'phase'       => $phase,
			'optional'    => $optional,
			'keywords'    => $keywords,
		);
	};

	// --- ۱. ستون‌های اصلی (4) ---
	$add( 'pillars', 'patient-journey', 'از تصمیم به جراحی تا بازگشت به زندگی: راهنمای همدلانه مسیر بیمار و همراه', 'patient_guide', 'guide_stage', 'pillars', 0, array(), array( 'مسیر بیمار', 'تصمیم به جراحی' ) );
	$add( 'pillars', 'what-is-operating-room', 'اتاق عمل چیست و داخل آن چه می‌گذرد؟', 'patient_guide', 'guide_stage', 'pillars', 0, array(), array( 'اتاق عمل چیست' ) );
	$add( 'pillars', 'surgical-care-team', 'همکاری تیم درمان: نقش‌ها، ارتباط مؤثر و نقش شما در موفقیت عمل جراحی', 'patient_guide', 'guide_stage', 'pillars', 0, array(), array( 'همکاری تیم درمان' ) );
	$add( 'pillars', 'glossary-surgical-terms', 'از اسکراب تا مانیتور: راهنمای اصطلاحات اتاق عمل برای بیمار و همراه', 'patient_guide', 'guide_stage', 'pillars', 0, array(), array( 'اصطلاحات اتاق عمل', 'اسکراب تا مانیتور' ) );

	// --- ۲. راهنمای بیمار: قبل از عمل (15) ---
	$add( 'guide_before', 'preoperative-fasting', 'ناشتایی قبل از عمل جراحی: چرا مهم است و چطور رعایت کنیم؟', 'patient_guide', 'guide_stage', 'before', 1, array( 'why-fasting-before-surgery' ), array( 'ناشتایی قبل از عمل', 'ناشتا' ) );
	$add( 'guide_before', 'what-not-to-eat-before-surgery', 'قبل از عمل چه چیزهایی نباید بخوریم و بنوشیم؟', 'patient_guide', 'guide_stage', 'before', 1, array(), array( 'نباید بخوریم', 'چه چیزهایی قبل از عمل نخوریم' ) );
	$add( 'guide_before', 'medications-before-surgery', 'داروهای قبل از عمل: پرونده‌ی دارویی‌تان را چطور تحویل تیم درمان بدهید؟', 'patient_guide', 'guide_stage', 'before', 1, array( 'medication-list-handover' ), array( 'داروهای قبل از عمل', 'پرونده دارویی' ) );
	$add( 'guide_before', 'informed-consent-anesthesia-consult', 'رضایت آگاهانه و مشاوره بیهوشی پیش از جراحی', 'patient_guide', 'guide_stage', 'consent-anesthesia', 1, array( 'informed-consent' ), array( 'رضایت آگاهانه', 'رضایت‌نامه' ) );
	$add( 'guide_before', 'preoperative-tests', 'آزمایش‌های قبل از عمل: چرا انجام می‌شوند و چه اطلاعاتی باید بدهیم؟', 'patient_guide', 'guide_stage', 'before', 2, array( 'blood-tests-before-surgery' ), array( 'آزمایش‌های قبل از عمل', 'آزمایش خون قبل از عمل' ) );
	$add( 'guide_before', 'companion-role-admission-day', 'نقش همراه در روز عمل: از پذیرش تا اتاق عمل و فهرست وسایل بستری', 'patient_guide', 'guide_stage', 'day-of-surgery', 1, array(), array( 'نقش همراه در روز عمل', 'وسایل بستری' ) );
	$add( 'guide_before', 'general-preparation-before-surgery', 'قبل از عمل چه آمادگی‌های عمومی لازم است؟', 'patient_guide', 'guide_stage', 'general-preparation', 4, array(), array( 'آمادگی‌های عمومی قبل از عمل' ) );
	$add( 'guide_before', 'questions-before-surgery', 'چه سؤال‌هایی را قبل از عمل از پزشک یا تیم درمان بپرسیم؟', 'patient_guide', 'guide_stage', 'questions-to-ask', 4, array(), array( 'چه سؤال‌هایی را قبل از عمل' ) );
	$add( 'guide_before', 'mental-preparation-before-surgery', 'آمادگی روانی قبل از عمل: چطور با ترس و اضطراب جراحی کنار بیاییم؟', 'patient_guide', 'guide_stage', 'anxiety-support', 1, array( 'pre-surgery-anxiety' ), array( 'آمادگی روانی قبل از عمل', 'اضطراب قبل از عمل' ) );
	$add( 'guide_before', 'medical-history-disclosure', 'چه اطلاعاتی را باید حتماً به تیم درمان بگوییم؟ (حساسیت‌ها و بیماری‌های زمینه‌ای)', 'patient_guide', 'guide_stage', 'questions-to-ask', 4, array(), array( 'چه اطلاعاتی را باید', 'حساسیت‌ها و بیماری‌های زمینه‌ای' ) );
	$add( 'guide_before', 'anesthesia-consultation', 'مشاوره‌ی بیهوشی پیش از عمل: متخصص بیهوشی دقیقاً چه چیزهایی را بررسی می‌کند؟', 'patient_guide', 'guide_stage', 'consent-anesthesia', 1, array(), array( 'مشاوره‌ی بیهوشی چیست', 'مشاوره بیهوشی پیش از عمل' ) );
	$add( 'guide_before', 'blood-donation-reserve-before-surgery', 'اهدای خون و ذخیره‌ی خون قبل از عمل: چه زمانی لازم می‌شود؟', 'patient_guide', 'guide_stage', 'general-preparation', 3, array(), array( 'اهدای خون قبل از عمل', 'ذخیره خون قبل از عمل' ) );
	$add( 'guide_before', 'smoking-and-surgery', 'سیگار و قلیان پیش از جراحی: چرا تیم درمان روی ترک تأکید می‌کند؟', 'patient_guide', 'guide_stage', 'general-preparation', 3, array(), array( 'سیگار و جراحی', 'ترک سیگار قبل از عمل' ) );
	$add( 'guide_before', 'diabetes-and-surgery', 'دیابت و جراحی: نکته‌های عمومی و سؤال‌های رایج پیش از عمل', 'patient_guide', 'guide_stage', 'general-preparation', 3, array(), array( 'دیابت و جراحی' ) );
	$add( 'guide_before', 'obesity-and-anesthesia', 'چاقی و بیهوشی: تفاوت‌های ارزیابی پیش از عمل', 'patient_guide', 'guide_stage', 'general-preparation', 3, array(), array( 'چاقی و بیهوشی' ) );

	// --- ۳. راهنمای بیمار: حین عمل و بیهوشی (15) ---
	$add( 'guide_during', 'operating-room-moment-by-moment', 'اتاق عمل واقعاً چه شکلی است؟ روایت لحظه‌به‌لحظه از ورود تا ریکاوری', 'patient_guide', 'guide_stage', 'during', 1, array(), array( 'روایت لحظه‌به‌لحظه', 'اتاق عمل واقعاً چه شکلی است' ) );
	$add( 'guide_during', 'general-anesthesia', 'بیهوشی عمومی چیست و چگونه عمل می‌کند؟', 'patient_guide', 'guide_stage', 'anesthesia-types', 1, array(), array( 'بیهوشی عمومی چیست' ) );
	$add( 'guide_during', 'general-vs-spinal-anesthesia', 'تفاوت بیهوشی عمومی و بی‌حسی نخاعی: کدام برای شما انتخاب می‌شود؟', 'patient_guide', 'guide_stage', 'anesthesia-types', 1, array(), array( 'بیهوشی عمومی و بی‌حسی نخاعی', 'بیهوشی عمومی در برابر' ) );
	$add( 'guide_during', 'fear-of-anesthesia-awareness', 'ترس از بیدار شدن در بیهوشی: واقعیت علمی و راه‌های آرامش', 'patient_guide', 'guide_stage', 'anesthesia-types', 1, array(), array( 'بیدار شدن در بیهوشی', 'آگاهی حین بیهوشی' ) );
	$add( 'guide_during', 'pacu-recovery', 'ریکاوری (PACU) چیست و در اتاق ریکاوری چه می‌گذرد؟', 'patient_guide', 'guide_stage', 'during', 1, array( 'recovery-pacu' ), array( 'ریکاوری (PACU) چیست', 'اتاق ریکاوری چه می‌گذرد' ) );
	$add( 'guide_during', 'anesthesia-side-effects', 'عوارض شایع بعد از بیهوشی: تهوع، گلودرد، لرز و گیجی', 'patient_guide', 'guide_stage', 'anesthesia-types', 1, array(), array( 'عوارض شایع بعد از بیهوشی' ) );
	$add( 'guide_during', 'intubation-explained', 'اینتوباسیون (لوله‌گذاری تنفسی) چیست و چرا انجام می‌شود؟', 'patient_guide', 'guide_stage', 'anesthesia-types', 2, array(), array( 'اینتوباسیون', 'لوله‌گذاری تنفسی' ) );
	$add( 'guide_during', 'anesthesia-and-memory', 'آیا بیهوشی روی حافظه و تمرکز اثر می‌گذارد؟', 'patient_guide', 'guide_stage', 'anesthesia-types', 2, array(), array( 'بیهوشی روی حافظه' ) );
	$add( 'guide_during', 'why-operating-room-is-sterile', 'چرا اتاق عمل استریل است؟', 'patient_guide', 'guide_stage', 'sterile-environment', 4, array(), array( 'چرا اتاق عمل استریل است' ) );
	$add( 'guide_during', 'why-operating-room-is-cold', 'چرا هوای اتاق عمل خنک است؟', 'patient_guide', 'guide_stage', 'sterile-environment', 4, array(), array( 'چرا هوای اتاق عمل خنک است', 'دمای اتاق عمل' ) );
	$add( 'guide_during', 'devices-patient-sees-in-or', 'بیمار در اتاق عمل چه دستگاه‌ها و تجهیزاتی را می‌بیند؟', 'patient_guide', 'guide_stage', 'general-steps', 4, array(), array( 'چه دستگاه‌ها و تجهیزاتی را می‌بیند' ) );
	$add( 'guide_during', 'scrub-gown-sterile-field-patient', 'اسکراب، گان و میدان استریل به زبان ساده یعنی چه؟', 'patient_guide', 'guide_stage', 'sterile-environment', 4, array(), array( 'اسکراب، گان و میدان استریل' ) );
	$add( 'guide_during', 'surgical-safety-checklist-patient', 'چک‌لیست ایمنی جراحی سازمان جهانی بهداشت (WHO) چیست و چگونه از بیمار محافظت می‌کند؟', 'patient_guide', 'guide_stage', 'patient-safety', 4, array(), array( 'چک‌لیست ایمنی جراحی' ) );
	$add( 'guide_during', 'patient-site-identification', 'شناسایی صحیح بیمار و محل عمل: چرا چند بار نام و نوع عمل را می‌پرسند؟', 'patient_guide', 'guide_stage', 'patient-safety', 4, array(), array( 'شناسایی صحیح بیمار', 'چند بار نام و نوع عمل' ) );
	$add( 'guide_during', 'monitoring-under-anesthesia-and-family', 'بیمار بیهوش چگونه در طول عمل پایش می‌شود و خانواده در زمان انتظار چه کند؟', 'patient_guide', 'guide_stage', 'patient-safety', 4, array(), array( 'بیمار بیهوش چگونه مراقبت', 'پایش می‌شود و خانواده' ) );

	// --- ۴. راهنمای بیمار: بعد از عمل و ریکاوری (14) ---
	$add( 'guide_after', 'post-surgery-pain', 'بعد از عمل چقدر درد خواهم داشت و چطور آن را گزارش بدهم؟', 'patient_guide', 'guide_stage', 'normal-symptoms', 1, array( 'pain-after-surgery' ), array( 'بعد از عمل چقدر درد', 'درد بعد از عمل' ) );
	$add( 'guide_after', 'early-walking-after-surgery', 'کی می‌توانم بعد از عمل راه بروم؟ مزایا و اصول راه رفتن ایمن', 'patient_guide', 'guide_stage', 'recovery', 1, array( 'early-mobilization' ), array( 'بعد از عمل راه بروم', 'راه رفتن زودهنگام' ) );
	$add( 'guide_after', 'post-surgery-nutrition', 'تغذیه بعد از عمل جراحی: اصول کلی و بازگشت تدریجی به رژیم معمول', 'patient_guide', 'guide_stage', 'return-to-life', 1, array( 'nutrition-after-surgery' ), array( 'تغذیه بعد از عمل' ) );
	$add( 'guide_after', 'surgical-site-infection', 'عفونت بعد از عمل: علائم و راه‌های پیشگیری', 'patient_guide', 'guide_stage', 'infection-prevention', 1, array(), array( 'عفونت بعد از عمل', 'عفونت محل جراحی' ) );
	$add( 'guide_after', 'suture-removal', 'بخیه‌ها چند روز بعد از عمل کشیده می‌شوند؟', 'patient_guide', 'guide_stage', 'recovery', 1, array( 'stitches-removal', 'surgical-stitches-removal' ), array( 'بخیه‌ها چند روز بعد از عمل', 'کشیدن بخیه' ) );
	$add( 'guide_after', 'warning-signs-after-surgery', 'علائم خطر بعد از عمل: چه زمانی فوراً با پزشک یا اورژانس ۱۱۵ تماس بگیریم؟', 'patient_guide', 'guide_stage', 'warning-signs', 1, array(), array( 'علائم خطر بعد از عمل', 'فوراً با ۱۱۵ تماس' ) );
	$add( 'guide_after', 'home-wound-care', 'مراقبت از زخم و پانسمان جراحی در خانه: چارچوب کلی و مرز ارجاع', 'patient_guide', 'guide_stage', 'infection-prevention', 2, array(), array( 'مراقبت از زخم جراحی در خانه' ) );
	$add( 'guide_after', 'dvt-pulmonary-embolism-prevention', 'ترومبوز ورید عمقی (DVT) و آمبولی ریه بعد از جراحی: خطر و پیشگیری', 'patient_guide', 'guide_stage', 'warning-signs', 2, array(), array( 'ترومبوز ورید عمقی', 'آمبولی ریه بعد از جراحی' ) );
	$add( 'guide_after', 'discharge-checklist', 'چک‌لیست ترخیص: هنگام خروج از بیمارستان چه سؤال‌هایی بپرسیم؟', 'patient_guide', 'guide_stage', 'discharge', 2, array(), array( 'چک‌لیست ترخیص', 'هنگام ترخیص چه سؤال‌هایی' ) );
	$add( 'guide_after', 'recovery-to-ward-and-discharge', 'مسیر بیمار از ریکاوری تا بخش بستری و ترخیص چگونه است؟', 'patient_guide', 'guide_stage', 'discharge', 4, array(), array( 'از ریکاوری تا بخش و ترخیص' ) );
	$add( 'guide_after', 'general-post-op-care', 'مراقبت‌های عمومی بعد از عمل در خانه: چارچوب کلی', 'patient_guide', 'guide_stage', 'recovery', 4, array(), array( 'مراقبت‌های عمومی بعد از عمل' ) );
	$add( 'guide_after', 'return-to-work-after-surgery', 'بازگشت به کار و زندگی عادی بعد از جراحی: چرا زمان آن برای هر بیمار فرق دارد؟', 'patient_guide', 'guide_stage', 'return-to-life', 3, array(), array( 'بازگشت به کار بعد از عمل', 'بازگشت به کار و زندگی' ) );
	$add( 'guide_after', 'exercise-activity-after-surgery', 'فعالیت بدنی و ورزش بعد از جراحی: چارچوب بازگشت تدریجی', 'patient_guide', 'guide_stage', 'return-to-life', 3, array(), array( 'فعالیت و ورزش بعد از جراحی', 'ورزش بعد از جراحی' ) );
	$add( 'guide_after', 'cast-splint-bandage-drain-guide', 'پروتز، گچ، بانداژ و درن: این واژه‌ها برای بیمار یعنی چه؟', 'patient_guide', 'guide_stage', 'recovery', 5, array(), array( 'پروتز، گچ، بانداژ و درن' ) );

	// --- ۵. گروه‌های ویژه و تصمیم‌گیری مسیر (8) ---
	$add( 'guide_special', 'children-and-anesthesia', 'کودک و بیهوشی / اتاق عمل: راهنمای والدین', 'patient_guide', 'guide_stage', 'children', 2, array(), array( 'کودک و بیهوشی', 'کودک و اتاق عمل' ) );
	$add( 'guide_special', 'elderly-and-surgery', 'سالمندان و جراحی: نکات عمومی برای بیمار و همراه', 'patient_guide', 'guide_stage', 'elderly', 2, array(), array( 'سالمندان و جراحی', 'سالمندان و اتاق عمل' ) );
	$add( 'guide_special', 'emergency-vs-elective-surgery', 'جراحی اورژانسی در برابر جراحی انتخابی: تفاوت مسیر بیمار', 'patient_guide', 'guide_stage', 'emergency-elective', 2, array(), array( 'جراحی اورژانسی در برابر', 'اورژانس و جراحی انتخابی' ) );
	$add( 'guide_special', 'outpatient-vs-inpatient-surgery', 'عمل سرپایی در برابر عمل بستری: تفاوت مسیر بیمار', 'patient_guide', 'guide_stage', 'special-groups', 2, array(), array( 'عمل سرپایی در برابر', 'سرپایی و عمل بستری' ) );
	$add( 'guide_special', 'surgery-during-pregnancy', 'جراحی در دوران بارداری: آیا ممکن است و چه ملاحظاتی دارد؟', 'patient_guide', 'guide_stage', 'special-groups', 3, array(), array( 'جراحی در دوران بارداری' ) );
	$add( 'guide_special', 'laparoscopic-robotic-open-surgery', 'جراحی لاپاروسکوپی، روبوتیک و باز: تفاوت‌ها به زبان ساده', 'patient_guide', 'guide_stage', 'special-groups', 3, array(), array( 'لاپاروسکوپی، روبوتیک و جراحی باز', 'لاپاروسکوپی، روبوتیک و باز' ) );
	$add( 'guide_special', 'medical-second-opinion', 'نظر دوم پزشکی پیش از جراحی: چه زمانی و چگونه؟', 'patient_guide', 'guide_stage', 'questions-to-ask', 3, array(), array( 'نظر دوم پزشکی' ) );
	$add( 'guide_special', 'surgery-cost-and-insurance-questions', 'هزینه و بیمه‌ی جراحی: چه سؤال‌هایی از بیمارستان و بیمه بپرسیم؟ (بدون مبلغ)', 'patient_guide', 'guide_stage', 'questions-to-ask', 3, array(), array( 'هزینه و بیمه‌ی جراحی', 'بیمه و هزینه‌ی جراحی' ) );

	// --- ۶. محیط، تیم و تجهیزات اتاق عمل (21) ---
	$add( 'or_env', 'surgical-team-roles', 'تیم اتاق عمل: هر کس چه کاری انجام می‌دهد؟', 'or_environment', 'env_section', 'team', 1, array(), array( 'تیم اتاق عمل: هر کس چه کاری' ) );
	$add( 'or_env', 'surgeon-and-assistant-role', 'جراح و دستیار جراح چه می‌کنند؟', 'or_environment', 'env_section', 'surgeon', 4, array(), array( 'جراح و دستیار جراح' ) );
	$add( 'or_env', 'anesthesiologist-and-technician-role', 'متخصص بیهوشی و تکنسین بیهوشی چه نقشی دارند؟', 'or_environment', 'env_section', 'anesthesia-team', 4, array(), array( 'متخصص بیهوشی و تکنسین بیهوشی' ) );
	$add( 'or_env', 'scrub-technician-role', 'تکنسین اسکراب (سرگروه ابزار استریل) چه می‌کند؟', 'or_environment', 'env_section', 'scrub-technician', 4, array(), array( 'تکنسین اسکراب چه می‌کند', 'تکنیسین اسکراب' ) );
	$add( 'or_env', 'circulating-technician-role', 'تکنسین سیرکولر (سیار اتاق عمل) چه می‌کند؟', 'or_environment', 'env_section', 'circulator', 4, array(), array( 'تکنسین سیرکولر چه می‌کند', 'تکنیسین سیرکولر' ) );
	$add( 'or_env', 'recovery-and-ward-nurse-role', 'پرستار ریکاوری و پرستار بخش جراحی چه نقشی دارند؟', 'or_environment', 'env_section', 'recovery-nurse', 4, array(), array( 'پرستار ریکاوری و پرستار بخش' ) );
	$add( 'or_env', 'cssd-and-support-staff-role', 'پرسنل استریلیزاسیون (CSSD) و خدمات اتاق عمل چه نقشی دارند؟', 'or_environment', 'env_section', 'support-staff', 4, array(), array( 'پرسنل استریلیزاسیون' ) );
	$add( 'or_env', 'operating-room-zones-spaces', 'معرفی فضاهای مرتبط با اتاق عمل (ورودی، القای بیهوشی، سالن عمل، ریکاوری)', 'or_environment', 'env_section', 'operating-room', 4, array(), array( 'معرفی اتاق عمل و فضاهای مرتبط', 'فضاهای مرتبط با اتاق عمل' ) );
	$add( 'or_env', 'pacu-environment-and-equipment', 'واحد مراقبت پس از بیهوشی (PACU) از نگاه فضا و تجهیزات', 'or_environment', 'env_section', 'pacu', 4, array(), array( 'واحد مراقبت پس از بیهوشی (PACU) از نگاه فضا' ) );
	$add( 'or_env', 'cssd-central-sterile-unit', 'واحد استریل مرکزی (CSSD) چه کاری انجام می‌دهد؟', 'or_environment', 'env_section', 'cssd', 4, array(), array( 'واحد استریل مرکزی (CSSD)' ) );
	$add( 'or_env', 'operating-room-access-control', 'چرا ورود به اتاق عمل محدود است؟ (لباس، ماسک، خط قرمز و کنترل رفت‌وآمد)', 'or_environment', 'env_section', 'spaces', 4, array(), array( 'چرا ورود به اتاق عمل محدود است' ) );
	$add( 'or_env', 'operating-room-ventilation-airflow', 'سیستم تهویه و فشار مثبت هوای اتاق عمل چگونه کار می‌کند؟', 'or_environment', 'env_section', 'ventilation', 4, array(), array( 'سیستم تهویه و فشار مثبت', 'تهویه و هوای اتاق عمل' ) );
	$add( 'or_env', 'vital-signs-monitor-explained', 'مانیتور علائم حیاتی در اتاق عمل چه اعداد و موج‌هایی را نشان می‌دهد؟', 'or_environment', 'env_section', 'vital-monitor', 4, array(), array( 'مانیتور علائم حیاتی چه' ) );
	$add( 'or_env', 'anesthesia-machine-explained', 'دستگاه بیهوشی به زبان ساده چیست؟', 'or_environment', 'env_section', 'anesthesia-machine', 4, array(), array( 'دستگاه بیهوشی به زبان ساده' ) );
	$add( 'or_env', 'surgical-light-and-table', 'چراغ سقفی و تخت جراحی چه کاربرد عمومی دارند؟', 'or_environment', 'env_section', 'light-and-table', 4, array(), array( 'چراغ و تخت جراحی', 'چراغ سقفی و تخت جراحی' ) );
	$add( 'or_env', 'surgical-suction-device', 'دستگاه ساکشن در اتاق عمل چه کاری می‌کند؟', 'or_environment', 'env_section', 'suction', 4, array(), array( 'ساکشن در اتاق عمل' ) );
	$add( 'or_env', 'electrosurgery-cautery-explained', 'الکتروسرجری (کوتر) چیست و چرا استفاده می‌شود؟', 'or_environment', 'env_section', 'electrosurgery', 4, array(), array( 'الکتروسرجری', 'کوتر چیست' ) );
	$add( 'or_env', 'surgical-instrument-set-intro', 'ست ابزار جراحی چیست؟ (معرفی عمومی)', 'or_environment', 'env_section', 'instrument-set', 4, array(), array( 'ست ابزار جراحی چیست' ) );
	$add( 'or_env', 'sponge-and-instrument-count', 'شمارش گاز و ابزار جراحی: چرا قبل و بعد از عمل انجام می‌شود؟', 'or_environment', 'env_section', 'counting', 4, array(), array( 'شمارش گاز و ابزار' ) );
	$add( 'or_env', 'tourniquet-pads-body-attachments', 'تورنیکه، پد و وسایل اتصال به بدن: بیمار چه چیزهایی روی بدن خود می‌بیند؟', 'or_environment', 'env_section', 'devices', 4, array(), array( 'تورنیکه، پد و وسایل اتصال' ) );
	$add( 'or_env', 'laparoscopy-tower-explained', 'دستگاه و برج لاپاروسکوپی چیست؟', 'or_environment', 'env_section', 'laparoscopy-tower', 4, array(), array( 'برج لاپاروسکوپی چیست' ) );

	// --- ۷، ۸، ۹. جراحی‌ها: گروه ۱ (۱۲ عمل)، گروه ۲ (۱۴ عمل)، گروه ۳ (۵ عمل) ---
	$surgeries = array(
		// Group 1 (12 surgeries -> 36 base + 12 optional)
		array( 'surgery_g1', 'appendectomy', 'آپاندکتومی (عمل آپاندیس)', 'appendectomy', 1, 'چه زمانی درد شکم نیاز به مراجعه‌ی فوری دارد؟', 'appendectomy-when-emergency' ),
		array( 'surgery_g1', 'cesarean', 'سزارین', 'cesarean', 1, 'همراه بیمار و نوزاد در اتاق عمل سزارین', 'cesarean-partner-newborn' ),
		array( 'surgery_g1', 'hernia', 'جراحی فتق (هرنی)', 'hernia-repair', 1, 'چرا بعد از عمل فتق از بلند کردن اجسام سنگین منع می‌شوند؟', 'hernia-heavy-lifting' ),
		array( 'surgery_g1', 'laparoscopy', 'لاپاروسکوپی عمومی', 'general-laparoscopy', 2, 'لاپاروسکوپی یا جراحی باز: تفاوت مسیر بیمار چیست؟', 'laparoscopy-vs-open-path' ),
		array( 'surgery_g1', 'cholecystectomy', 'کوله‌سیستکتومی (کیسه صفرا)', 'cholecystectomy', 2, 'بعد از برداشتن کیسه صفرا تغذیه چه تغییری می‌کند؟', 'cholecystectomy-diet-changes' ),
		array( 'surgery_g1', 'cataract', 'کاتاراکت (آب مروارید)', 'cataract', 2, 'روز عمل چشم (آب مروارید): از پذیرش تا ترخیص', 'cataract-day-of-surgery' ),
		array( 'surgery_g1', 'thyroidectomy', 'تیروئیدکتومی (جراحی تیروئید)', 'thyroidectomy', 2, 'مراقبت از محل عمل در ناحیه‌ی گردن پس از تیروئیدکتومی', 'thyroidectomy-neck-care' ),
		array( 'surgery_g1', 'kidney-stone', 'جراحی سنگ کلیه و حالب', 'kidney-stone', 3, 'تفاوت روش‌های سنگ کلیه: لیتوتریپسی، اندوسکوپی و جراحی', 'kidney-stone-methods' ),
		array( 'surgery_g1', 'tonsillectomy', 'جراحی لوزه و آدنوئید کودکان', 'tonsillectomy', 4, 'کودک و اتاق عمل در جراحی لوزه: راهنمای والدین', 'tonsillectomy-parents-guide' ),
		array( 'surgery_g1', 'circumcision', 'ختنه‌ی کودکان', 'circumcision', 4, 'آماده‌سازی کودک برای عمل سرپایی ختنه', 'circumcision-outpatient-prep' ),
		array( 'surgery_g1', 'hemorrhoid', 'هموروئید و جراحی‌های ناحیه‌ی مقعد', 'hemorrhoid-anal', 4, 'حریم خصوصی و کاهش خجالت بیمار در جراحی‌های ناحیه‌ی مقعد', 'hemorrhoid-patient-privacy' ),
		array( 'surgery_g1', 'septoplasty', 'سپتوپلاستی و جراحی انحراف بینی/سینوس', 'septoplasty-sinus', 4, 'بعد از عمل بینی و سینوس چه حس‌هایی طبیعی است؟', 'septoplasty-normal-feelings' ),

		// Group 2 (14 surgeries -> 42 base + 14 optional)
		array( 'surgery_g2', 'rhinoplasty', 'رینوپلاستی و عمل زیبایی بینی', 'rhinoplasty', 2, 'انتظارات واقع‌بینانه و ایمنی در مراکز جراحی زیبایی', 'rhinoplasty-safety-expectations' ),
		array( 'surgery_g2', 'prostate-turp', 'جراحی پروستات (TURP)', 'prostate-turp', 2, 'تجربه‌ی بی‌حسی نخاعی در جراحی پروستات چه حسی دارد؟', 'prostate-turp-spinal-experience' ),
		array( 'surgery_g2', 'hysterectomy', 'هیسترکتومی (برداشتن رحم)', 'hysterectomy', 3, 'انواع روش‌های هیسترکتومی (شکمی، واژینال و لاپاروسکوپی) به زبان ساده', 'hysterectomy-methods' ),
		array( 'surgery_g2', 'myomectomy-cyst', 'میومکتومی و جراحی کیست تخمدان', 'myomectomy-cyst', 5, 'لاپاروسکوپی زنان: آنچه بیمار باید بداند', 'gynecologic-laparoscopy-guide' ),
		array( 'surgery_g2', 'tubal-vasectomy', 'بستن لوله‌ها و وازکتومی', 'tubal-ligation', 5, 'تفاوت بیهوشی و بی‌حسی در عمل‌های بستن لوله و وازکتومی', 'tubal-vasectomy-anesthesia' ),
		array( 'surgery_g2', 'varicocele', 'واریکوسل و جراحی‌های کیسه‌ی بیضه', 'varicocele', 5, 'مسیر عمل سرپایی در جراحی واریکوسل', 'varicocele-outpatient-guide' ),
		array( 'surgery_g2', 'knee-arthroscopy', 'آرتروسکوپی زانو', 'knee-arthroscopy', 5, 'آماده‌سازی زانو و اصول کلی حرکت پس از آرتروسکوپی', 'knee-arthroscopy-mobility' ),
		array( 'surgery_g2', 'carpal-tunnel', 'جراحی سندروم تونل کارپ مچ دست', 'carpal-tunnel', 5, 'جراحی تونل کارپ با بی‌حسی موضعی در هوشیاری کامل', 'carpal-tunnel-local-anesthesia' ),
		array( 'surgery_g2', 'anal-fistula-fissure', 'جراحی فیستول و شقاق مقعد', 'fistula-fissure', 5, 'اصول کلی مراقبت از زخم باز پس از جراحی مقعد', 'fistula-open-wound-care' ),
		array( 'surgery_g2', 'breast-biopsy', 'بیوپسی پستان و جراحی توده‌های خوش‌خیم', 'breast-biopsy', 5, 'مدیریت اضطراب انتظار نتیجه‌ی پاتولوژی و بیوپسی پستان', 'breast-biopsy-waiting-anxiety' ),
		array( 'surgery_g2', 'mastectomy', 'ماستکتومی (جراحی پستان)', 'mastectomy', 5, 'همراهی و حمایت روانی بیمار در مسیر ماستکتومی', 'mastectomy-emotional-support' ),
		array( 'surgery_g2', 'bariatric-surgery', 'جراحی چاقی (اسلیو و بای‌پس معده)', 'bariatric', 5, 'نقش لاپاروسکوپی در جراحی‌های چاقی', 'bariatric-laparoscopy' ),
		array( 'surgery_g2', 'dialysis-fistula', 'جراحی فیستول دیالیز', 'dialysis-fistula', 5, 'مراقبت روزمره از دست دارای فیستول پس از جراحی', 'dialysis-fistula-arm-care' ),
		array( 'surgery_g2', 'varicose-veins', 'جراحی و درمان واریس پا', 'varicose-veins', 5, 'معرفی کلی انواع روش‌های درمان و جراحی واریس', 'varicose-treatment-methods' ),

		// Group 3 (5 specialized surgeries -> 15 base, 0 optional)
		array( 'surgery_g3', 'cabg-open-heart', 'جراحی قلب باز و بای‌پس عروق کرونر', 'cabg', 3, '', '' ),
		array( 'surgery_g3', 'joint-replacement', 'تعویض مفصل زانو و ران', 'joint-replacement', 3, '', '' ),
		array( 'surgery_g3', 'brain-spine-disc', 'جراحی مغز، ستون فقرات و دیسک کمر', 'spine-disc', 5, '', '' ),
		array( 'surgery_g3', 'fracture-plating', 'جراحی شکستگی‌ها و پلاتین‌گذاری ارتوپدی', 'fracture-fixation', 5, '', '' ),
		array( 'surgery_g3', 'organ-transplant', 'جراحی‌های پیوند اعضا', 'general-surgery', 5, '', '' ),
	);

	foreach ( $surgeries as $s ) {
		list( $sec, $s_slug, $s_name, $spec_term, $phase, $opt_title, $opt_slug ) = $s;
		$extra = array(
			'taxonomy' => 'surgery_specialty',
			'term'     => $spec_term,
		);
		$add(
			$sec,
			$s_slug . '-overview',
			$s_name . ' چیست و چه مراحلی دارد؟',
			'surgery',
			'surgery_stage',
			'surgery-overview',
			$phase,
			array( $s_slug ),
			array( $s_name . ' چیست', 'مراحل ' . $s_name ),
			false,
			$extra
		);
		$add(
			$sec,
			$s_slug . '-preparation',
			'قبل از ' . $s_name . ' چه آمادگی‌هایی لازم است؟',
			'surgery',
			'surgery_stage',
			'before-surgery',
			$phase,
			array( $s_slug . '-before' ),
			array( 'قبل از ' . $s_name, 'آمادگی قبل از ' . $s_name ),
			false,
			$extra
		);
		$add(
			$sec,
			$s_slug . '-recovery',
			'بعد از ' . $s_name . ' چه مراقبت‌هایی لازم است؟',
			'surgery',
			'surgery_stage',
			'after-surgery',
			$phase,
			array( $s_slug . '-after' ),
			array( 'بعد از ' . $s_name, 'مراقبت بعد از ' . $s_name ),
			false,
			$extra
		);
		if ( '' !== $opt_title && '' !== $opt_slug ) {
			$add(
				$sec,
				$opt_slug,
				$opt_title,
				'surgery',
				'surgery_stage',
				'surgery-extra',
				$phase,
				array(),
				array( $opt_title ),
				true,
				$extra
			);
		}
	}

	// --- ۱۰. ویژه‌ی دانشجویان اتاق عمل و هوشبری (14) ---
	$add( 'student', 'student-scrub-vs-circulator', 'تفاوت نقش تکنسین اسکراب و سیرکولر در اتاق عمل', 'student_content', 'student_topic', 'scrub-vs-circulator', 6, array(), array( 'تفاوت نقش اسکراب و سیرکولر', 'اسکراب و سیرکولر' ) );
	$add( 'student', 'student-sterile-field-principles', 'اصول میدان استریل در اتاق عمل', 'student_content', 'student_topic', 'sterile-field', 6, array(), array( 'اصول میدان استریل' ) );
	$add( 'student', 'student-surgical-hand-scrub', 'شست‌وشوی جراحی دست‌ها (اسکراب جراحی) به‌صورت کلی', 'student_content', 'student_topic', 'surgical-scrub', 6, array(), array( 'شست‌وشوی جراحی دست‌ها', 'اسکراب جراحی دست' ) );
	$add( 'student', 'student-closed-gowning-gloving', 'پوشیدن گان و دستکش استریل به روش بسته: مراحل عمومی', 'student_content', 'student_topic', 'gowning-gloving', 6, array(), array( 'پوشیدن گان و دستکش' ) );
	$add( 'student', 'student-surgical-instrument-classes', 'دسته‌بندی عمومی ابزارهای جراحی (برنده، گیرنده، نگه‌دارنده، کنارزننده)', 'student_content', 'student_topic', 'instrument-classes', 6, array(), array( 'دسته‌بندی عمومی ابزارهای جراحی' ) );
	$add( 'student', 'student-mayo-back-table-setup', 'آشنایی با چیدمان کلی میز مایو و میز ابزار جراحی', 'student_content', 'student_topic', 'table-setup', 6, array(), array( 'چیدمان کلی میز ابزار', 'میز مایو' ) );
	$add( 'student', 'student-or-safety-hazards', 'ایمنی عمومی در اتاق عمل (آتش، برق، تشعشع و اجسام تیز)', 'student_content', 'student_topic', 'fire-electric-radiation', 6, array(), array( 'ایمنی عمومی در اتاق عمل' ) );
	$add( 'student', 'student-sponge-instrument-counting', 'شمارش گاز، سوآپ و ابزار جراحی: اهمیت و روش کلی برای دانشجو', 'student_content', 'student_topic', 'student-counting', 6, array(), array( 'شمارش گاز و ابزار؛ اهمیت' ) );
	$add( 'student', 'student-patient-positioning', 'وضعیت‌دهی (پوزیشن‌دهی) بیمار روی تخت عمل و اصول ایمنی', 'student_content', 'student_topic', 'patient-positioning', 6, array(), array( 'وضعیت‌دهی بیمار روی تخت' ) );
	$add( 'student', 'student-sterilization-vs-disinfection', 'استریلیزاسیون و ضدعفونی: تفاوت و روش‌های رایج', 'student_content', 'student_topic', 'sterilization', 6, array(), array( 'استریلیزاسیون و ضدعفونی' ) );
	$add( 'student', 'student-who-checklist-technician', 'چک‌لیست ایمنی جراحی سازمان جهانی بهداشت از دید تکنسین اتاق عمل', 'student_content', 'student_topic', 'who-checklist', 6, array(), array( 'چک‌لیست ایمنی جراحی سازمان جهانی بهداشت از دید' ) );
	$add( 'student', 'student-or-team-communication', 'ارتباط مؤثر و حرفه‌ای در تیم اتاق عمل', 'student_content', 'student_topic', 'team-communication', 6, array(), array( 'ارتباط مؤثر در تیم اتاق عمل' ) );
	$add( 'student', 'student-managing-first-days-stress', 'مدیریت استرس دانشجو در اولین روزهای ورود به اتاق عمل', 'student_content', 'student_topic', 'professional-skills', 6, array(), array( 'مدیریت استرس دانشجو' ) );
	$add( 'student', 'student-or-internship-preparation', 'چگونه برای کارآموزی در اتاق عمل آماده شویم؟', 'student_content', 'student_topic', 'internship-prep', 6, array(), array( 'کارآموزی در اتاق عمل آماده شویم' ) );

	$cached = $items;
	return $cached;
}

/**
 * Compute live roadmap status by matching all existing WordPress posts across the 5 CPTs
 * against the target roadmap catalog.
 *
 * @param bool $include_optional Whether optional 4th surgery articles count toward totals.
 * @return array<string,mixed>
 */
function hj_roadmap_compute_progress( bool $include_optional = false ): array {
	$sections = hj_roadmap_sections();
	$catalog  = hj_roadmap_items();
	$cpts     = array( 'patient_guide', 'or_environment', 'surgery', 'student_content' );

	$posts = get_posts(
		array(
			'post_type'              => $cpts,
			'post_status'            => array( 'publish', 'draft', 'pending', 'future' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	$by_roadmap_id = array();
	$by_slug       = array();
	$all_posts     = array();

	foreach ( $posts as $p ) {
		$rid = (string) get_post_meta( $p->ID, '_hj_roadmap_id', true );
		if ( '' !== $rid && ! isset( $by_roadmap_id[ $rid ] ) ) {
			$by_roadmap_id[ $rid ] = $p;
		}
		$slug_key = urldecode( (string) $p->post_name );
		// Prefer published post if duplicate slug exists across statuses.
		if ( ! isset( $by_slug[ $slug_key ] ) || ( 'publish' === $p->post_status && 'publish' !== $by_slug[ $slug_key ]->post_status ) ) {
			$by_slug[ $slug_key ] = $p;
		}
		$all_posts[ $p->ID ] = $p;
	}

	$matched_post_ids = array();
	$enriched_items   = array();

	$sec_stats = array();
	foreach ( $sections as $s_key => $s_meta ) {
		$sec_stats[ $s_key ] = array_merge(
			$s_meta,
			array(
				'total'     => 0,
				'published' => 0,
				'draft'     => 0,
				'remaining' => 0,
				'percent'   => 0.0,
				'items'     => array(),
			)
		);
	}

	$totals = array(
		'total'           => 0,
		'published'       => 0,
		'draft'           => 0,
		'written'         => 0,
		'remaining'       => 0,
		'percent_publish' => 0.0,
		'percent_written' => 0.0,
		'phase1_total'    => 0,
		'phase1_publish'  => 0,
		'phase1_percent'  => 0.0,
	);

	foreach ( $catalog as $item ) {
		if ( ! empty( $item['optional'] ) && ! $include_optional ) {
			continue;
		}

		$matched = null;
		if ( isset( $by_roadmap_id[ $item['id'] ] ) ) {
			$matched = $by_roadmap_id[ $item['id'] ];
		} elseif ( isset( $by_slug[ $item['slug'] ] ) ) {
			$matched = $by_slug[ $item['slug'] ];
		} else {
			foreach ( $item['aliases'] as $al ) {
				if ( isset( $by_slug[ $al ] ) ) {
					$matched = $by_slug[ $al ];
					break;
				}
			}
		}

		// Smart fallback match by distinctive Persian keyword inside same post_type.
		if ( ! $matched && ! empty( $item['keywords'] ) ) {
			foreach ( $all_posts as $pid => $cand ) {
				if ( isset( $matched_post_ids[ $pid ] ) || $cand->post_type !== $item['post_type'] ) {
					continue;
				}
				foreach ( $item['keywords'] as $kw ) {
					if ( '' !== $kw && false !== strpos( $cand->post_title, $kw ) ) {
						$matched = $cand;
						break 2;
					}
				}
			}
		}

		$status_code = 'todo';
		$post_info   = null;
		if ( $matched ) {
			$matched_post_ids[ $matched->ID ] = true;
			$status_code = ( 'publish' === $matched->post_status ) ? 'publish' : 'draft';
			$thumb_id    = (int) get_post_thumbnail_id( $matched->ID );
			$meta_desc   = (string) get_post_meta( $matched->ID, '_hj_meta_description', true );
			if ( '' === $meta_desc ) {
				$meta_desc = (string) $matched->post_excerpt;
			}
			$faq_meta = get_post_meta( $matched->ID, '_hj_faq', true );
			$has_faq  = is_array( $faq_meta ) && ! empty( $faq_meta );
			$alt_txt  = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';

			$post_info = array(
				'id'         => (int) $matched->ID,
				'title'      => $matched->post_title,
				'slug'       => urldecode( (string) $matched->post_name ),
				'status'     => $matched->post_status,
				'edit_link'  => (string) get_edit_post_link( $matched->ID, 'raw' ),
				'permalink'  => (string) get_permalink( $matched->ID ),
				'has_image'  => $thumb_id > 0,
				'has_alt'    => '' !== trim( $alt_txt ),
				'has_meta'   => '' !== trim( $meta_desc ),
				'has_faq'    => $has_faq,
				'updated_at' => function_exists( 'hj_get_jalali_post_modified_date' )
					? hj_get_jalali_post_modified_date( $matched->ID, 'numeric' )
					: get_post_modified_time( 'Y-m-d', false, $matched ),
			);
		}

		$row = array_merge(
			$item,
			array(
				'status' => $status_code,
				'post'   => $post_info,
			)
		);

		$s_key = $item['section'];
		++$sec_stats[ $s_key ]['total'];
		++$totals['total'];

		if ( $item['phase'] <= 1 ) {
			++$totals['phase1_total'];
		}

		if ( 'publish' === $status_code ) {
			++$sec_stats[ $s_key ]['published'];
			++$totals['published'];
			++$totals['written'];
			if ( $item['phase'] <= 1 ) {
				++$totals['phase1_publish'];
			}
		} elseif ( 'draft' === $status_code ) {
			++$sec_stats[ $s_key ]['draft'];
			++$totals['draft'];
			++$totals['written'];
		} else {
			++$sec_stats[ $s_key ]['remaining'];
			++$totals['remaining'];
		}

		$sec_stats[ $s_key ]['items'][] = $row;
		$enriched_items[]               = $row;
	}

	// Count any extra WordPress posts not mapped to a predefined catalog slug.
	$unmapped = array();
	foreach ( $all_posts as $pid => $p ) {
		if ( ! isset( $matched_post_ids[ $pid ] ) ) {
			$unmapped[] = array(
				'id'        => (int) $p->ID,
				'title'     => $p->post_title,
				'slug'      => urldecode( (string) $p->post_name ),
				'post_type' => $p->post_type,
				'status'    => $p->post_status,
				'edit_link' => (string) get_edit_post_link( $p->ID, 'raw' ),
				'permalink' => (string) get_permalink( $p->ID ),
			);
		}
	}

	foreach ( $sec_stats as $s_key => &$st ) {
		$st['percent']       = $st['total'] > 0 ? round( ( $st['published'] / $st['total'] ) * 100, 1 ) : 0.0;
		$st['draft_percent'] = $st['total'] > 0 ? round( ( $st['draft'] / $st['total'] ) * 100, 1 ) : 0.0;
		$st['badge']         = hj_roadmap_progress_badge( $st['percent'], $st['draft'] );
	}
	unset( $st );

	$totals['percent_publish'] = $totals['total'] > 0 ? round( ( $totals['published'] / $totals['total'] ) * 100, 1 ) : 0.0;
	$totals['percent_written'] = $totals['total'] > 0 ? round( ( $totals['written'] / $totals['total'] ) * 100, 1 ) : 0.0;
	$totals['phase1_percent']  = $totals['phase1_total'] > 0 ? round( ( $totals['phase1_publish'] / $totals['phase1_total'] ) * 100, 1 ) : 0.0;
	$totals['badge']           = hj_roadmap_progress_badge( $totals['percent_publish'], $totals['draft'] );

	// Glossary engine stats (5,000+ terms bank, tracked separately from the 184/210 long-form articles).
	$glossary_counts = wp_count_posts( 'glossary' );
	$cpt_pub         = isset( $glossary_counts->publish ) ? (int) $glossary_counts->publish : 0;
	$cpt_draft       = ( isset( $glossary_counts->draft ) ? (int) $glossary_counts->draft : 0 ) + ( isset( $glossary_counts->pending ) ? (int) $glossary_counts->pending : 0 );
	$fast_stats      = function_exists( 'hj_glossary_get_stats' ) ? hj_glossary_get_stats() : array( 'published' => 0, 'draft' => 0 );
	$glossary_pub    = max( $cpt_pub, (int) ( $fast_stats['published'] ?? 0 ) );
	$glossary_draft  = max( $cpt_draft, (int) ( $fast_stats['draft'] ?? 0 ) );
	$glossary_target = 5000;
	$glossary_pct    = min( 100.0, round( ( $glossary_pub / $glossary_target ) * 100, 1 ) );

	return array(
		'include_optional' => $include_optional,
		'totals'           => $totals,
		'sections'         => $sec_stats,
		'items'            => $enriched_items,
		'unmapped_posts'   => $unmapped,
		'glossary'         => array(
			'published' => $glossary_pub,
			'draft'     => $glossary_draft,
			'target'    => $glossary_target,
			'percent'   => $glossary_pct,
			'badge'     => hj_roadmap_progress_badge( $glossary_pct, $glossary_draft ),
		),
	);
}

/**
 * Return visual progress badge symbol, label, colors, and status class for a percentage.
 *
 * @param float $percent     Published percentage (0-100).
 * @param int   $draft_count Number of draft posts in section.
 * @return array<string,string>
 */
function hj_roadmap_progress_badge( float $percent, int $draft_count = 0 ): array {
	if ( $percent >= 100.0 ) {
		return array(
			'symbol' => '🏆',
			'label'  => 'تکمیل ۱۰۰٪',
			'tone'   => 'complete',
			'bg'     => '#DCFCE7',
			'color'  => '#166534',
		);
	}
	if ( $percent >= 75.0 ) {
		return array(
			'symbol' => '🚀',
			'label'  => 'پیشتاز',
			'tone'   => 'strong',
			'bg'     => '#DBEAFE',
			'color'  => '#1E40AF',
		);
	}
	if ( $percent >= 40.0 ) {
		return array(
			'symbol' => '⚡',
			'label'  => 'نیمه‌راه',
			'tone'   => 'mid',
			'bg'     => '#E0F2FE',
			'color'  => '#0369A1',
		);
	}
	if ( $percent > 0.0 ) {
		return array(
			'symbol' => '🌱',
			'label'  => 'در جریان',
			'tone'   => 'active',
			'bg'     => '#FEF9C3',
			'color'  => '#854D0E',
		);
	}
	if ( $draft_count > 0 ) {
		return array(
			'symbol' => '📝',
			'label'  => 'در پیش‌نویس',
			'tone'   => 'draft',
			'bg'     => '#FEF3C7',
			'color'  => '#92400E',
		);
	}
	return array(
		'symbol' => '⏳',
		'label'  => 'در انتظار شروع',
		'tone'   => 'empty',
		'bg'     => '#F1F5F9',
		'color'  => '#475569',
	);
}
