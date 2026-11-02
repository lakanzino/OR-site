<?php
/**
 * ماژول ۸: کتابخانهٔ جراحی‌ها و راهنمای تعاملی.
 *
 * ۷۲ عمل جراحی در ۹ دسته، هر کدام با نام فارسی، نام انگلیسی، توضیح کوتاه و
 * یک آیکون SVG مستقل در assets/icons/procedures/<slug>.svg.
 *
 * آیکون‌ها با currentColor رسم شده‌اند؛ به‌صورت درون‌خطی چاپ می‌شوند تا رنگ
 * خودکار با پوسته هماهنگ شود و هیچ درخواست شبکه‌ای اضافه نشود.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * فهرست دسته‌ها و جراحی‌ها.
 *
 * @return array<string,array<string,mixed>>
 */
function hj_surgery_catalog() {
	static $catalog = null;
	if ( null !== $catalog ) {
		return $catalog;
	}

	$catalog = array(
		'ortho' => array(
			'fa'   => 'جراحی ارتوپدی',
			'en'   => 'Orthopedics',
			'sub'  => 'استخوان، مفصل و ستون فقرات حرکتی',
			'procedures' => array(
				array(
					'slug' => 'total-knee-arthroplasty',
					'fa'   => 'تعویض مفصل زانو',
					'en'   => 'Total Knee Arthroplasty',
					'desc' => 'جایگزینی سطوح فرسوده زانو با پروتز، معمولاً در آرتروز پیشرفته.',
				),
				array(
					'slug' => 'total-hip-arthroplasty',
					'fa'   => 'تعویض مفصل لگن',
					'en'   => 'Total Hip Arthroplasty',
					'desc' => 'جایگزینی سر استخوان ران و حفره لگن با مفصل مصنوعی.',
				),
				array(
					'slug' => 'knee-arthroscopy',
					'fa'   => 'آرتروسکوپی زانو',
					'en'   => 'Knee Arthroscopy',
					'desc' => 'بررسی و ترمیم داخل زانو با دوربین از چند برش کوچک.',
				),
				array(
					'slug' => 'acl-reconstruction',
					'fa'   => 'جراحی رباط صلیبی',
					'en'   => 'ACL Reconstruction',
					'desc' => 'بازسازی رباط صلیبی پاره‌شده با پیوند تاندون، به روش آرتروسکوپی.',
				),
				array(
					'slug' => 'meniscus-repair',
					'fa'   => 'جراحی منیسک زانو',
					'en'   => 'Meniscus Repair',
					'desc' => 'ترمیم یا برداشتن بخش پاره‌شده منیسک (ضربه‌گیر زانو) با دوربین.',
				),
				array(
					'slug' => 'fracture-fixation',
					'fa'   => 'درمان شکستگی',
					'en'   => 'Fracture Fixation',
					'desc' => 'جااندازی استخوان شکسته و تثبیت آن با پیچ، پلاک یا میله.',
				),
				array(
					'slug' => 'joint-preservation',
					'fa'   => 'درمان آرتروز و حفظ مفصل',
					'en'   => 'Joint Preservation',
					'desc' => 'ترمیم غضروف و روش‌های کمکی برای عقب انداختن تعویض مفصل.',
				),
				array(
					'slug' => 'osteotomy',
					'fa'   => 'جراحی اصلاح بدشکلی',
					'en'   => 'Osteotomy',
					'desc' => 'برش و تغییر زاویه استخوان برای صاف کردن محور اندام، مثل پای پرانتزی.',
				),
			),
		),
		'plastic' => array(
			'fa'   => 'جراحی پلاستیک و ترمیمی',
			'en'   => 'Plastic & Reconstructive',
			'sub'  => 'اصلاح فرم و بازسازی بافت',
			'procedures' => array(
				array(
					'slug' => 'abdominoplasty',
					'fa'   => 'ابدومینوپلاستی',
					'en'   => 'Abdominoplasty',
					'desc' => 'برداشتن پوست و چربی اضافه شکم و سفت کردن عضلات آن.',
				),
				array(
					'slug' => 'liposuction',
					'fa'   => 'لیپوساکشن',
					'en'   => 'Liposuction',
					'desc' => 'خارج کردن چربی موضعی با کانول از برش‌های ریز.',
				),
				array(
					'slug' => 'mammoplasty',
					'fa'   => 'ماموپلاستی',
					'en'   => 'Mammoplasty',
					'desc' => 'کوچک کردن، بزرگ کردن یا بالا کشیدن پستان.',
				),
				array(
					'slug' => 'rhinoplasty-plastic',
					'fa'   => 'رینوپلاستی',
					'en'   => 'Rhinoplasty',
					'desc' => 'اصلاح فرم بینی و در صورت نیاز بهبود تنفس.',
				),
				array(
					'slug' => 'blepharoplasty',
					'fa'   => 'بلفاروپلاستی',
					'en'   => 'Blepharoplasty',
					'desc' => 'برداشتن پوست و چربی اضافه پلک بالا یا پایین.',
				),
				array(
					'slug' => 'autograft-flap',
					'fa'   => 'اتوگرافت و فلپ',
					'en'   => 'Autograft & Flap',
					'desc' => 'انتقال پوست یا بافت از بدن خود بیمار برای پوشاندن زخم یا نقص.',
				),
				array(
					'slug' => 'breast-reconstruction',
					'fa'   => 'بازسازی پستان',
					'en'   => 'Breast Reconstruction',
					'desc' => 'بازسازی پستان بعد از ماستکتومی با پروتز یا بافت خود بیمار.',
				),
				array(
					'slug' => 'scar-revision',
					'fa'   => 'جراحی ترمیم اسکار',
					'en'   => 'Scar Revision',
					'desc' => 'کم‌رنگ و صاف کردن جای زخم با برش و بخیه دقیق یا لیزر.',
				),
			),
		),
		'cardio' => array(
			'fa'   => 'جراحی قلب و عروق',
			'en'   => 'Cardiovascular',
			'sub'  => 'قلب، دریچه و رگ‌های خونی',
			'procedures' => array(
				array(
					'slug' => 'cabg',
					'fa'   => 'بای‌پس عروق کرونر',
					'en'   => 'CABG',
					'desc' => 'ساخت مسیر تازه خون‌رسانی به قلب با رگ پیوندی از پا یا سینه.',
				),
				array(
					'slug' => 'valve-replacement',
					'fa'   => 'تعویض دریچه قلب',
					'en'   => 'Valve Replacement',
					'desc' => 'ترمیم یا تعویض دریچه تنگ یا نشت‌دار با دریچه مکانیکی یا بیولوژیک.',
				),
				array(
					'slug' => 'angioplasty-stent',
					'fa'   => 'آنژیوپلاستی و استنت',
					'en'   => 'Angioplasty & Stent',
					'desc' => 'باز کردن رگ تنگ قلب با بالون و گذاشتن فنر از راه کاتتر.',
				),
				array(
					'slug' => 'aortic-surgery',
					'fa'   => 'جراحی آئورت',
					'en'   => 'Aortic Surgery',
					'desc' => 'ترمیم آنوریسم یا پارگی آئورت با گرافت، باز یا از داخل رگ.',
				),
				array(
					'slug' => 'varicose-vein',
					'fa'   => 'جراحی واریس',
					'en'   => 'Varicose Vein Surgery',
					'desc' => 'بستن یا خارج کردن وریدهای گشاد پا با لیزر، RF یا جراحی.',
				),
				array(
					'slug' => 'thrombectomy',
					'fa'   => 'برداشتن لخته عروق',
					'en'   => 'Thrombectomy',
					'desc' => 'خارج کردن لخته از شریان یا ورید، اغلب از راه کاتتر.',
				),
				array(
					'slug' => 'asd-vsd-closure',
					'fa'   => 'بستن سوراخ قلبی',
					'en'   => 'ASD/VSD Closure',
					'desc' => 'بستن نقص دیواره قلب با وسیله کاتتری یا جراحی باز.',
				),
				array(
					'slug' => 'peripheral-bypass',
					'fa'   => 'جراحی عروق محیطی',
					'en'   => 'Peripheral Bypass',
					'desc' => 'دور زدن شریان مسدود پا با پیوند رگ یا لوله مصنوعی.',
				),
			),
		),
		'neuro' => array(
			'fa'   => 'جراحی مغز و اعصاب',
			'en'   => 'Neurosurgery',
			'sub'  => 'مغز، نخاع و عصب',
			'procedures' => array(
				array(
					'slug' => 'brain-tumor-surgery',
					'fa'   => 'جراحی تومور مغزی',
					'en'   => 'Brain Tumor Surgery',
					'desc' => 'برداشتن تومور از راه باز کردن جمجمه با هدایت تصویربرداری.',
				),
				array(
					'slug' => 'aneurysm-clipping',
					'fa'   => 'آنوریسم مغزی',
					'en'   => 'Aneurysm Clipping',
					'desc' => 'بستن برآمدگی رگ مغز با کلیپ فلزی یا کویل از داخل رگ.',
				),
				array(
					'slug' => 'spine-discectomy',
					'fa'   => 'دیسککتومی ستون فقرات',
					'en'   => 'Spine Discectomy',
					'desc' => 'برداشتن بخش بیرون‌زده دیسک که به عصب فشار می‌آورد.',
				),
				array(
					'slug' => 'laminectomy',
					'fa'   => 'لامینکتومی',
					'en'   => 'Laminectomy',
					'desc' => 'برداشتن بخشی از مهره برای باز کردن کانال تنگ نخاع.',
				),
				array(
					'slug' => 'epilepsy-surgery',
					'fa'   => 'جراحی صرع',
					'en'   => 'Epilepsy Surgery',
					'desc' => 'برداشتن یا جدا کردن کانون تشنج، وقتی دارو جواب نمی‌دهد.',
				),
				array(
					'slug' => 'shunt-surgery',
					'fa'   => 'شنت مغزی',
					'en'   => 'Shunt Surgery',
					'desc' => 'لوله باریکی که مایع اضافه مغز (هیدروسفالی) را به شکم تخلیه می‌کند.',
				),
				array(
					'slug' => 'nerve-surgery',
					'fa'   => 'جراحی عصب محیطی',
					'en'   => 'Nerve Surgery',
					'desc' => 'آزادسازی یا ترمیم عصب، مثل سندرم تونل کارپال.',
				),
				array(
					'slug' => 'spinal-fusion',
					'fa'   => 'جراحی ستون فقرات',
					'en'   => 'Spinal Fusion',
					'desc' => 'جوش دادن دو یا چند مهره با پیچ و پیوند استخوان برای ثبات ستون.',
				),
			),
		),
		'gyn' => array(
			'fa'   => 'جراحی زنان و زایمان',
			'en'   => 'Obstetrics & Gynecology',
			'sub'  => 'رحم، تخمدان و بارداری',
			'procedures' => array(
				array(
					'slug' => 'cesarean-section',
					'fa'   => 'سزارین',
					'en'   => 'Cesarean Section',
					'desc' => 'تولد نوزاد از راه برش شکم و رحم.',
				),
				array(
					'slug' => 'hysterectomy',
					'fa'   => 'هیسترکتومی',
					'en'   => 'Hysterectomy',
					'desc' => 'برداشتن رحم به روش باز، واژینال یا لاپاراسکوپی.',
				),
				array(
					'slug' => 'myomectomy',
					'fa'   => 'میومکتومی',
					'en'   => 'Myomectomy',
					'desc' => 'برداشتن فیبروم‌های رحم با حفظ رحم.',
				),
				array(
					'slug' => 'laparoscopic-gyn',
					'fa'   => 'لاپاراسکوپی زنان',
					'en'   => 'Laparoscopic GYN',
					'desc' => 'تشخیص و درمان مشکلات لگن با دوربین از برش‌های کوچک.',
				),
				array(
					'slug' => 'endometriosis',
					'fa'   => 'اندومتریوز',
					'en'   => 'Endometriosis Surgery',
					'desc' => 'برداشتن یا سوزاندن کانون‌های اندومتریوز، اغلب با لاپاراسکوپی.',
				),
				array(
					'slug' => 'vaginal-tightening',
					'fa'   => 'تنگی واژن',
					'en'   => 'Vaginal Tightening',
					'desc' => 'ترمیم و سفت کردن عضلات و بافت واژن.',
				),
				array(
					'slug' => 'ovarian-cystectomy',
					'fa'   => 'برداشت کیست تخمدان',
					'en'   => 'Ovarian Cystectomy',
					'desc' => 'خارج کردن کیست با حفظ تخمدان، معمولاً با لاپاراسکوپی.',
				),
				array(
					'slug' => 'ivf-infertility',
					'fa'   => 'IVF و ناباروری',
					'en'   => 'IVF & Infertility',
					'desc' => 'تخمک‌گیری، لقاح در آزمایشگاه و انتقال جنین به رحم.',
				),
			),
		),
		'uro' => array(
			'fa'   => 'جراحی ارولوژی',
			'en'   => 'Urology',
			'sub'  => 'کلیه، مثانه و دستگاه ادراری',
			'procedures' => array(
				array(
					'slug' => 'kidney-stone-surgery',
					'fa'   => 'برداشت سنگ کلیه',
					'en'   => 'Kidney Stone Surgery',
					'desc' => 'خرد کردن سنگ با لیزر از راه مجرا یا خارج کردن آن از راه پوست.',
				),
				array(
					'slug' => 'turp-prostate',
					'fa'   => 'پروستات TURP',
					'en'   => 'TURP (Prostate)',
					'desc' => 'تراشیدن بافت بزرگ‌شده پروستات از راه مجرا، بدون برش.',
				),
				array(
					'slug' => 'varicocele-surgery',
					'fa'   => 'واریکوسل',
					'en'   => 'Varicocele Surgery',
					'desc' => 'بستن وریدهای گشاد بیضه، اغلب زیر میکروسکوپ.',
				),
				array(
					'slug' => 'urethral-stricture',
					'fa'   => 'تنگی مجرا',
					'en'   => 'Urethral Stricture',
					'desc' => 'باز کردن یا بازسازی تنگی مجرای ادرار.',
				),
				array(
					'slug' => 'neobladder-surgery',
					'fa'   => 'نئوبلادر مثانه',
					'en'   => 'Neobladder Surgery',
					'desc' => 'ساخت مثانه تازه از روده بعد از برداشتن مثانه.',
				),
				array(
					'slug' => 'orchiectomy',
					'fa'   => 'جراحی بیضه',
					'en'   => 'Orchiectomy',
					'desc' => 'برداشتن یک یا هر دو بیضه، مثلاً در سرطان.',
				),
				array(
					'slug' => 'prostate-cancer-surgery',
					'fa'   => 'سرطان پروستات',
					'en'   => 'Prostate Cancer Surgery',
					'desc' => 'برداشتن کامل پروستات به روش باز، لاپاراسکوپی یا رباتیک.',
				),
				array(
					'slug' => 'nephrectomy',
					'fa'   => 'جراحی کلیه (نفرکتومی)',
					'en'   => 'Nephrectomy',
					'desc' => 'برداشتن تمام یا بخشی از کلیه.',
				),
			),
		),
		'general' => array(
			'fa'   => 'جراحی عمومی',
			'en'   => 'General Surgery',
			'sub'  => 'شکم، گوارش و بافت نرم',
			'procedures' => array(
				array(
					'slug' => 'appendectomy',
					'fa'   => 'آپاندکتومی',
					'en'   => 'Appendectomy',
					'desc' => 'برداشتن آپاندیس ملتهب، اغلب با لاپاراسکوپی.',
				),
				array(
					'slug' => 'cholecystectomy',
					'fa'   => 'کوله‌سیستکتومی',
					'en'   => 'Cholecystectomy',
					'desc' => 'برداشتن کیسه صفرا به علت سنگ یا التهاب.',
				),
				array(
					'slug' => 'hernia-repair',
					'fa'   => 'هرنیوپلاستی',
					'en'   => 'Hernia Repair',
					'desc' => 'برگرداندن فتق و تقویت دیواره شکم با مش.',
				),
				array(
					'slug' => 'general-laparoscopy',
					'fa'   => 'لاپاراسکوپی عمومی',
					'en'   => 'General Laparoscopy',
					'desc' => 'تشخیص و جراحی داخل شکم با دوربین و ابزار ظریف.',
				),
				array(
					'slug' => 'hemorrhoidectomy',
					'fa'   => 'هموروئیدکتومی',
					'en'   => 'Hemorrhoidectomy',
					'desc' => 'برداشتن هموروئیدهای بزرگ یا دردناک.',
				),
				array(
					'slug' => 'fissurectomy',
					'fa'   => 'فیشرکتومی',
					'en'   => 'Fissurectomy',
					'desc' => 'درمان شقاق مزمن مقعد با برداشتن زخم یا شل کردن اسفنکتر.',
				),
				array(
					'slug' => 'lipoma-excision',
					'fa'   => 'برداشت لیپوم',
					'en'   => 'Lipoma Excision',
					'desc' => 'خارج کردن توده چربی خوش‌خیم زیر پوست.',
				),
				array(
					'slug' => 'skin-lesion-excision',
					'fa'   => 'برداشت پوست و ضایعات',
					'en'   => 'Skin & Lesion Excision',
					'desc' => 'برداشتن خال، کیست یا ضایعه پوستی و فرستادن آن برای پاتولوژی.',
				),
			),
		),
		'eye' => array(
			'fa'   => 'جراحی چشم',
			'en'   => 'Ophthalmology',
			'sub'  => 'قرنیه، شبکیه و عدسی',
			'procedures' => array(
				array(
					'slug' => 'cataract-surgery',
					'fa'   => 'فیکو و لیزر آب مروارید',
					'en'   => 'Cataract Surgery',
					'desc' => 'خرد کردن لنز کدر با امواج فراصوت و کاشت لنز مصنوعی.',
				),
				array(
					'slug' => 'lasik',
					'fa'   => 'لیزیک',
					'en'   => 'LASIK',
					'desc' => 'اصلاح نزدیک‌بینی، دوربینی یا آستیگمات با تراش لیزری قرنیه.',
				),
				array(
					'slug' => 'pterygium-surgery',
					'fa'   => 'پیترژیوم',
					'en'   => 'Pterygium Surgery',
					'desc' => 'برداشتن ناخنک چشم و پیوند ملتحمه برای جلوگیری از عود.',
				),
				array(
					'slug' => 'retinal-surgery',
					'fa'   => 'جراحی شبکیه',
					'en'   => 'Retinal Surgery',
					'desc' => 'ترمیم پارگی یا جداشدگی شبکیه با ویترکتومی، لیزر یا گاز.',
				),
				array(
					'slug' => 'glaucoma-surgery',
					'fa'   => 'جراحی گلوکوم',
					'en'   => 'Glaucoma Surgery',
					'desc' => 'ساخت مسیر تخلیه مایع چشم برای پایین آوردن فشار آن.',
				),
				array(
					'slug' => 'strabismus-surgery',
					'fa'   => 'استرابیسم',
					'en'   => 'Strabismus Surgery',
					'desc' => 'اصلاح انحراف چشم با جابه‌جا کردن عضلات آن.',
				),
				array(
					'slug' => 'eyelid-surgery',
					'fa'   => 'بلفاروپلاستی چشم',
					'en'   => 'Eyelid Surgery',
					'desc' => 'اصلاح افتادگی یا بدشکلی پلک برای دید بهتر یا زیبایی.',
				),
				array(
					'slug' => 'iol-implantation',
					'fa'   => 'جاگذاری عدسی',
					'en'   => 'IOL Implantation',
					'desc' => 'کاشت لنز داخل چشمی برای اصلاح نمره‌های بالا.',
				),
			),
		),
		'ent' => array(
			'fa'   => 'جراحی گوش و حلق و بینی',
			'en'   => 'ENT',
			'sub'  => 'گوش، سینوس، لوزه و حنجره',
			'procedures' => array(
				array(
					'slug' => 'tonsillectomy',
					'fa'   => 'تانسیلکتومی',
					'en'   => 'Tonsillectomy',
					'desc' => 'برداشتن لوزه‌های کامی به علت عفونت مکرر یا خروپف.',
				),
				array(
					'slug' => 'septoplasty',
					'fa'   => 'سپتوپلاستی',
					'en'   => 'Septoplasty',
					'desc' => 'صاف کردن تیغه انحرافی بینی برای تنفس راحت‌تر.',
				),
				array(
					'slug' => 'rhinoplasty-ent',
					'fa'   => 'رینوپلاستی',
					'en'   => 'Rhinoplasty',
					'desc' => 'اصلاح ساختار بینی برای تنفس بهتر و فرم مناسب.',
				),
				array(
					'slug' => 'sinus-surgery',
					'fa'   => 'جراحی سینوس',
					'en'   => 'Sinus Surgery',
					'desc' => 'باز کردن مسیر سینوس‌ها با آندوسکوپ در سینوزیت مزمن.',
				),
				array(
					'slug' => 'ossiculoplasty',
					'fa'   => 'اتصال استخوانچه گوش',
					'en'   => 'Ossiculoplasty',
					'desc' => 'بازسازی زنجیره استخوانچه‌های گوش میانی برای شنوایی بهتر.',
				),
				array(
					'slug' => 'adenoidectomy',
					'fa'   => 'جراحی لوزه سوم',
					'en'   => 'Adenoidectomy',
					'desc' => 'برداشتن آدنوئید پشت بینی، بیشتر در کودکان.',
				),
				array(
					'slug' => 'cochlear-implant',
					'fa'   => 'کاشت حلزون',
					'en'   => 'Cochlear Implant',
					'desc' => 'کاشت پروتز الکترونیکی برای ناشنوایی شدید؛ حدود ۴ هفته بعد روشن می‌شود.',
				),
				array(
					'slug' => 'laryngeal-surgery',
					'fa'   => 'جراحی حنجره',
					'en'   => 'Laryngeal Surgery',
					'desc' => 'برداشتن ضایعه تارهای صوتی یا حنجره، اغلب با میکروسکوپ یا لیزر.',
				),
			),
		),	);

	return $catalog;
}

/**
 * یافتن یک جراحی با اسلاگ.
 *
 * @param string $slug اسلاگ.
 * @return array<string,string>|null
 */
function hj_surgery_find( $slug ) {
	$slug = sanitize_title( (string) $slug );
	foreach ( hj_surgery_catalog() as $cat_id => $cat ) {
		foreach ( $cat['procedures'] as $proc ) {
			if ( $proc['slug'] === $slug ) {
				$proc['category']    = $cat_id;
				$proc['category_fa'] = $cat['fa'];
				return $proc;
			}
		}
	}
	return null;
}

/**
 * مسیر فایل آیکون یک جراحی.
 *
 * @param string $slug اسلاگ.
 * @return string
 */
function hj_surgery_icon_path( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) {
		return '';
	}
	$path = HJ_DOCTORS_DIR . 'assets/icons/procedures/' . $slug . '.svg';
	return is_readable( $path ) ? $path : '';
}

/**
 * چاپ درون‌خطی آیکون یک جراحی.
 *
 * فایل‌ها در مخزن افزونه‌اند و ورودی کاربر نیستند؛ با این حال پیش از چاپ،
 * تگ‌های اجرایی حذف می‌شوند تا حتی در صورت دست‌کاری فایل هم خطر نباشد.
 *
 * @param string $slug  اسلاگ جراحی.
 * @param string $class کلاس اضافه.
 * @return string
 */
function hj_surgery_icon( $slug, $class = '' ) {
	static $cache = array();

	$path = hj_surgery_icon_path( $slug );
	if ( '' === $path ) {
		return '';
	}

	if ( ! isset( $cache[ $path ] ) ) {
		$svg = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$svg = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $svg );
		$svg = preg_replace( '#\son[a-z]+\s*=\s*"[^"]*"#i', '', (string) $svg );
		$cache[ $path ] = trim( (string) $svg );
	}

	$svg = $cache[ $path ];
	if ( '' === $svg ) {
		return '';
	}

	$class = trim( 'hj-proc-icon ' . $class );
	$svg   = preg_replace( '#<svg\b#', '<svg class="' . esc_attr( $class ) . '" focusable="false"', $svg, 1 );

	return (string) $svg;
}

/**
 * شناسهٔ صفحهٔ راهنمای جراحی؛ در اولین اجرا ساخته می‌شود.
 *
 * @return int
 */
function hj_surgery_guide_page_id() {
	$id = (int) get_option( 'hj_surgery_guide_page_id', 0 );
	if ( $id > 0 && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	return 0;
}

/**
 * ساخت یک‌بارهٔ صفحهٔ راهنمای جراحی.
 *
 * @return void
 */
function hj_surgery_guide_ensure_page() {
	if ( hj_surgery_guide_page_id() ) {
		return;
	}
	if ( get_option( 'hj_surgery_guide_page_attempted' ) ) {
		return;
	}

	update_option( 'hj_surgery_guide_page_attempted', 1, false );

	$existing = get_page_by_path( 'راهنمای-جراحی' );
	if ( $existing instanceof WP_Post ) {
		update_option( 'hj_surgery_guide_page_id', (int) $existing->ID, false );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'راهنمای جراحی‌ها',
			'post_name'    => 'راهنمای-جراحی',
			'post_content' => '[hj_surgery_guide]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_option( 'hj_surgery_guide_page_id', (int) $page_id, false );
	}
}

/**
 * نشانی راهنمای جراحی؛ با لنگر همان عمل.
 *
 * @param string $slug اسلاگ جراحی (اختیاری).
 * @return string
 */
function hj_surgery_guide_url( $slug = '' ) {
	$page_id = hj_surgery_guide_page_id();
	$base    = $page_id ? get_permalink( $page_id ) : home_url( '/راهنمای-جراحی/' );
	$base    = (string) apply_filters( 'hj_surgery_guide_url', $base );

	if ( '' !== (string) $slug ) {
		$base .= '#hj-proc-' . sanitize_title( (string) $slug );
	}

	return $base;
}

/**
 * تشخیص دسته‌های مرتبط با یک پزشک از روی تخصص‌ها و متن جراحی‌ها.
 *
 * @param int $post_id شناسهٔ پزشک.
 * @return string[] شناسهٔ دسته‌ها.
 */
function hj_surgery_categories_for_doctor( $post_id ) {
	$keywords = array(
		'ortho'   => array( 'ارتوپد', 'استخوان', 'مفصل', 'زانو', 'لگن', 'ستون فقرات', 'Orthop' ),
		'plastic' => array( 'پلاستیک', 'ترمیمی', 'زیبایی', 'Plastic' ),
		'cardio'  => array( 'قلب', 'عروق', 'کرونر', 'Cardio' ),
		'neuro'   => array( 'مغز', 'اعصاب', 'نخاع', 'Neuro' ),
		'gyn'     => array( 'زنان', 'زایمان', 'نازایی', 'بارداری', 'Gynec' ),
		'uro'     => array( 'ارولوژی', 'کلیه', 'مثانه', 'پروستات', 'ادراری', 'Urolog' ),
		'general' => array( 'عمومی', 'گوارش', 'شکم', 'General' ),
		'eye'     => array( 'چشم', 'شبکیه', 'قرنیه', 'Ophthal' ),
		'ent'     => array( 'گوش', 'حلق', 'بینی', 'سینوس', 'حنجره', 'لوزه', 'ENT' ),
	);

	$haystack = '';

	$terms = get_the_terms( (int) $post_id, 'specialty' );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		foreach ( $terms as $t ) {
			$haystack .= ' ' . $t->name . ' ' . $t->slug;
		}
	}

	foreach ( array( 'specialty_fa', 'specialty_en' ) as $key ) {
		$value = function_exists( 'hj_doctors_field' ) ? hj_doctors_field( $key, $post_id ) : get_post_meta( $post_id, $key, true );
		if ( is_scalar( $value ) ) {
			$haystack .= ' ' . (string) $value;
		}
	}

	$found = array();
	foreach ( $keywords as $cat_id => $words ) {
		foreach ( $words as $word ) {
			if ( false !== stripos( $haystack, $word ) ) {
				$found[] = $cat_id;
				break;
			}
		}
	}

	return $found;
}

/**
 * جراحی‌های یک پزشک: تطبیق متن جراحی‌ها، وگرنه همهٔ جراحی‌های دستهٔ تخصص.
 *
 * @param int $post_id شناسهٔ پزشک.
 * @param int $limit   حداکثر تعداد.
 * @return array<int,array<string,string>>
 */
function hj_surgery_for_doctor( $post_id, $limit = 8 ) {
	$text = function_exists( 'hj_doctors_field' ) ? hj_doctors_field( 'surgeries_performed', $post_id ) : get_post_meta( $post_id, 'surgeries_performed', true );
	$text = is_scalar( $text ) ? (string) $text : '';

	$matched = array();

	foreach ( hj_surgery_catalog() as $cat_id => $cat ) {
		foreach ( $cat['procedures'] as $proc ) {
			if ( '' === $text ) {
				continue;
			}
			$needle_fa = preg_replace( '/\s+/u', ' ', $proc['fa'] );
			if ( '' !== $needle_fa && false !== stripos( $text, $needle_fa ) ) {
				$proc['category']    = $cat_id;
				$proc['category_fa'] = $cat['fa'];
				$matched[] = $proc;
			} elseif ( false !== stripos( $text, $proc['en'] ) ) {
				$proc['category']    = $cat_id;
				$proc['category_fa'] = $cat['fa'];
				$matched[] = $proc;
			}
		}
	}

	if ( $matched ) {
		return array_slice( $matched, 0, $limit );
	}

	$cats = hj_surgery_categories_for_doctor( $post_id );
	if ( ! $cats ) {
		return array();
	}

	$all = hj_surgery_catalog();
	$out = array();
	foreach ( $cats as $cat_id ) {
		if ( ! isset( $all[ $cat_id ]['procedures'] ) ) {
			continue;
		}
		$cat = $all[ $cat_id ];
		foreach ( $cat['procedures'] as $proc ) {
			$proc['category']    = $cat_id;
			$proc['category_fa'] = $cat['fa'];
			$out[] = $proc;
		}
	}

	return array_slice( $out, 0, $limit );
}

/**
 * شبکهٔ آیکون جراحی‌ها (برای پروفایل).
 *
 * @param array<int,array<string,string>> $procedures جراحی‌ها.
 * @param string                          $title      عنوان بخش.
 * @return string
 */
function hj_surgery_grid_html( $procedures, $title = 'عمل‌های انجام‌شده' ) {
	if ( empty( $procedures ) ) {
		return '';
	}

	$out = '<div class="hj-proc-grid-wrap">';
	if ( '' !== (string) $title ) {
		$out .= '<div class="hj-proc-grid-head"><h3>' . esc_html( $title ) . '</h3>';
		$out .= '<a class="hj-proc-grid-more" href="' . esc_url( hj_surgery_guide_url() ) . '">راهنمای کامل جراحی‌ها</a></div>';
	}
	$out .= '<div class="hj-proc-grid">';

	foreach ( $procedures as $proc ) {
		$url = hj_surgery_guide_url( $proc['slug'] );
		$out .= '<a class="hj-proc" href="' . esc_url( $url ) . '">';
		$out .= '<span class="hj-proc__icon">' . hj_surgery_icon( $proc['slug'] ) . '</span>';
		$out .= '<span class="hj-proc__fa">' . esc_html( $proc['fa'] ) . '</span>';
		$out .= '<span class="hj-proc__en" dir="ltr">' . esc_html( $proc['en'] ) . '</span>';
		$out .= '</a>';
	}

	$out .= '</div></div>';

	return $out;
}

/**
 * شورت‌کد راهنمای تعاملی جراحی‌ها.
 *
 * [hj_surgery_guide]                 → همهٔ دسته‌ها با ناوبری
 * [hj_surgery_guide category="ent"]  → فقط یک دسته (برای تقسیم راهنما)
 *
 * @param array<string,string> $atts ویژگی‌ها.
 * @return string
 */
function hj_surgery_guide_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'category' => '',
			'nav'      => 'yes',
		),
		$atts,
		'hj_surgery_guide'
	);

	$catalog = hj_surgery_catalog();
	$only    = sanitize_key( $atts['category'] );

	if ( '' !== $only && ! isset( $catalog[ $only ] ) ) {
		return '';
	}

	wp_enqueue_style(
		'hj-surgery-guide',
		HJ_DOCTORS_URL . 'assets/css/surgery-guide.css',
		array(),
		HJ_DOCTORS_VERSION
	);

	$cats = ( '' !== $only ) ? array( $only => $catalog[ $only ] ) : $catalog;

	ob_start();
	?>
	<div class="hj-guide" dir="rtl" lang="fa">
		<?php if ( 'yes' === $atts['nav'] ) : ?>
			<nav class="hj-guide__nav" aria-label="<?php echo esc_attr__( 'دسته‌های جراحی', 'hamyar-doctors' ); ?>">
				<?php foreach ( $catalog as $cat_id => $cat ) : ?>
					<?php if ( '' !== $only ) : ?>
						<a href="<?php echo esc_url( hj_surgery_guide_url() . '#hj-cat-' . $cat_id ); ?>">
					<?php else : ?>
						<a href="#hj-cat-<?php echo esc_attr( $cat_id ); ?>">
					<?php endif; ?>
						<span class="hj-guide__nav-fa"><?php echo esc_html( $cat['fa'] ); ?></span>
						<span class="hj-guide__nav-en" dir="ltr"><?php echo esc_html( $cat['en'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php foreach ( $cats as $cat_id => $cat ) : ?>
			<section class="hj-guide__cat" id="hj-cat-<?php echo esc_attr( $cat_id ); ?>">
				<header class="hj-guide__head">
					<h2><?php echo esc_html( $cat['fa'] ); ?></h2>
					<span class="hj-guide__head-en" dir="ltr"><?php echo esc_html( $cat['en'] ); ?></span>
					<span class="hj-guide__head-sub"><?php echo esc_html( $cat['sub'] ); ?></span>
				</header>

				<div class="hj-guide__grid">
					<?php foreach ( $cat['procedures'] as $proc ) : ?>
						<article class="hj-guide__card" id="hj-proc-<?php echo esc_attr( $proc['slug'] ); ?>">
							<div class="hj-guide__icon"><?php echo hj_surgery_icon( $proc['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<div class="hj-guide__body">
								<h3><?php echo esc_html( $proc['fa'] ); ?></h3>
								<p class="hj-guide__en" dir="ltr"><?php echo esc_html( $proc['en'] ); ?></p>
								<?php if ( ! empty( $proc['desc'] ) ) : ?>
									<p class="hj-guide__desc"><?php echo esc_html( $proc['desc'] ); ?></p>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>

				<?php if ( 'yes' === $atts['nav'] ) : ?>
					<p class="hj-guide__top"><a href="#hj-cat-<?php echo esc_attr( $cat_id ); ?>">بازگشت به بالای این دسته</a></p>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * بارگذاری استایل راهنما در صفحه‌ای که شورت‌کد دارد (پیش از چاپ head).
 *
 * @return void
 */
function hj_surgery_guide_maybe_enqueue() {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	if ( ! has_shortcode( (string) $post->post_content, 'hj_surgery_guide' ) ) {
		return;
	}
	wp_enqueue_style(
		'hj-surgery-guide',
		HJ_DOCTORS_URL . 'assets/css/surgery-guide.css',
		array(),
		HJ_DOCTORS_VERSION
	);
}

add_shortcode( 'hj_surgery_guide', 'hj_surgery_guide_shortcode' );
add_action( 'wp_enqueue_scripts', 'hj_surgery_guide_maybe_enqueue', 20 );
add_action( 'admin_init', 'hj_surgery_guide_ensure_page' );
