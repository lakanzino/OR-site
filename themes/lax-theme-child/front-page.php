<?php
/**
 * Front page: Hamyar Jarahi homepage (child theme 0.9.0).
 *
 * One clear job: take a patient or companion from "I have a surgery
 * ahead" to the right guide in seconds — stage first, then sections,
 * then the newest articles. Empty states stay honest while the first
 * article pillars are still being prepared.
 *
 * 0.9.0: simplified hero (title + search + actions only), image/title
 * article cards, slim section bars, trust and safety bands removed,
 * shorter FAQ list.
 *
 * Structure + standards: docs/HOMEPAGE-AND-SEO-PLAYBOOK.md
 *
 * @package Lax_Theme_Child
 * @since   0.6.0
 */

get_header();

$hj_latest = new WP_Query(
	array(
		'post_type'           => array( 'patient_guide', 'or_environment', 'surgery', 'student_content' ),
		'post_status'         => 'publish',
		'posts_per_page'      => 6,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);

$hj_glossary_topics = function_exists( 'hj_home_glossary_topics' ) ? hj_home_glossary_topics() : array();

$hj_surgery_groups = get_terms(
	array(
		'taxonomy'   => 'surgery_specialty',
		'parent'     => 0,
		'hide_empty' => true,
		'orderby'    => 'name',
	)
);
if ( is_wp_error( $hj_surgery_groups ) ) {
	$hj_surgery_groups = array();
}

$hj_journey = array(
	array(
		'stage' => 'before',
		'title' => 'قبل از عمل',
		'text'  => 'تصمیم به جراحی، پرسش‌هایی که باید از تیم درمان بپرسید، رضایت آگاهانه و آمادگی روز عمل.',
	),
	array(
		'stage' => 'during',
		'title' => 'حین عمل',
		'text'  => 'داخل اتاق عمل چه می‌گذرد؛ بیهوشی و بی‌حسی، محیط استریل و ایمنی بیمار به زبان ساده.',
	),
	array(
		'stage' => 'after',
		'title' => 'بعد از عمل و ریکاوری',
		'text'  => 'از ریکاوری تا ترخیص و بازگشت به زندگی؛ چه چیزهایی را باید بشناسید و از تیم درمان بپرسید.',
	),
	array(
		'stage' => 'special-groups',
		'title' => 'گروه‌های ویژه',
		'text'  => 'مسیر جراحی برای کودکان، سالمندان و تفاوت جراحی انتخابی و اورژانسی برای بیمار و همراه.',
	),
);

// The care assistant reads from real, published surgery articles.
$hj_care_surgeries = get_posts(
	array(
		'post_type'      => 'surgery',
		'post_status'    => 'publish',
		'posts_per_page' => 40,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);

$hj_care_stages = array(
	'day0' => array(
		'title'  => 'روزهای ۰ تا ۲ — ترخیص و روزهای اول',
		'points' => array(
			'استراحت و حرکت سبک داخل تخت، طبق دستوری که تیم درمان می‌دهد.',
			'مسکن را فقط همان‌طور که نسخه شده مصرف کنید؛ مصرف خودسرانه یا قطع خودسرانه هر دو مشکل‌ساز است.',
			'زخم را خشک و پوشیده نگه دارید؛ تا اجازهٔ صریح تیم درمان دوش نگیرید.',
			'مایعات کافی و غذای سبک؛ اگر تهوع یا استفراغ پایدار دارید با مرکز جراحی تماس بگیرید.',
		),
	),
	'day3' => array(
		'title'  => 'روزهای ۳ تا ۷ — راه‌افتادن و پیگیری',
		'points' => array(
			'پیاده‌روی کوتاه و مکرر بهتر از یک فعالیت سنگین است.',
			'دوش گرفتن فقط با اجازهٔ پزشک و طبق توضیح مرکز جراحی؛ زخم را بعد از آن خشک کنید.',
			'ویزیت یا تماس پیگیری را جا نیندازید و فهرست پرسش‌هایتان را از قبل بنویسید.',
			'درد باید روند کاهشی داشته باشد؛ اگر بیشتر شد، گزارش کنید.',
		),
	),
	'day8' => array(
		'title'  => 'روزهای ۸ تا ۱۴ — بازگشت تدریجی',
		'points' => array(
			'فعالیت روزمره را پله‌پله اضافه کنید و بین کارها استراحت بگذارید.',
			'زمان بازگشت به کار به نوع عمل و نظر جراح بستگی دارد؛ خودتان برای خودتان تصمیم نگیرید.',
			'مراقبت زخم را ادامه دهید و تغییر رنگ، تورم یا ترشح تازه را گزارش کنید.',
			'رانندگی و بلند کردن اجسام سنگین را با تیم درمان هماهنگ کنید.',
		),
	),
	'day15' => array(
		'title'  => 'بیش از دو هفته — تثبیت و بازگشت به روال',
		'points' => array(
			'اگر هنوز درد، تورم یا محدودیت حرکت دارید، طبیعی فرض نکنید و با تیم درمان تماس بگیرید.',
			'ورزش و باشگاه را با اجازهٔ پزشک شروع کنید، نه بر اساس احساس بهبودی.',
			'چکاپ و آزمایش‌های پیگیری را طبق برنامه انجام دهید.',
			'هر علامت تازه‌ای که شما را نگران می‌کند ارزش یک تماس دارد.',
		),
	),
);

$hj_sections = array(
	array(
		'post_type' => 'patient_guide',
		'title'     => 'راهنمای بیمار',
		'text'      => 'نقشه‌ی کامل مسیر؛ از تصمیم به جراحی تا بازگشت به زندگی روزمره.',
	),
	array(
		'post_type' => 'or_environment',
		'title'     => 'اتاق عمل، تیم و تجهیزات',
		'text'      => 'فضاها، آدم‌ها و دستگاه‌هایی که روز عمل کنارتان هستند را بشناسید.',
	),
	array(
		'post_type' => 'surgery',
		'title'     => 'جراحی‌ها',
		'text'      => 'برای هر عمل با الگوی ثابت: معرفی عمل، پیش از عمل و پس از عمل.',
	),
	array(
		'post_type' => 'disease',
		'title'     => 'دانشنامه بیماری‌ها',
		'text'      => 'آشنایی با بیماری‌ها، علائم، علت و منابع علمی.',
	),
	array(
		'post_type' => 'glossary',
		'title'     => 'واژه‌نامه',
		'text'      => 'معنی روشن اصطلاح‌هایی که در بیمارستان و مقاله‌ها می‌شنوید.',
	),
	array(
		'post_type' => 'student_content',
		'title'     => 'ویژه‌ی دانشجویان اتاق عمل',
		'text'      => 'آموزش حرفه‌ای با برچسب جدا، برای دانشجویان و تازه‌کاران اتاق عمل.',
	),
);

$hj_faqs = array(
	array(
		'q' => 'اگر جراحی در پیش دارم، از کجا شروع کنم؟',
		'a' => 'از بخش «در کدام مرحله‌اید؟» در همین صفحه یا راهنمای بیمار شروع کنید؛ مسیر از تصمیم به جراحی تا بازگشت به زندگی مرحله‌به‌مرحله چیده شده است.',
	),
	array(
		'q' => 'آیا همیار جراحی مشاوره‌ی پزشکی می‌دهد؟',
		'a' => 'خیر. این مجله آموزش عمومی است و جایگزین پزشک نیست. تصمیم درمانی را با جراح و تیم درمانی بگیرید که پرونده‌ی شما را دیده‌اند.',
	),
	array(
		'q' => 'اگر بعد از عمل نگران یک علامت هستم چه کنم؟',
		'a' => 'مقاله‌خواندن را جای تماس درمانی نگذارید؛ با تیم درمان یا مرکز جراحی خود تماس بگیرید و در وضعیت فوری در ایران با ۱۱۵ تماس بگیرید.',
	),
);
?>

<div class="container hj-home-wrap">
	<main id="primary" class="hj-home">

		<section class="hj-home-hero" aria-labelledby="hj-home-title">
			<h1 id="hj-home-title" class="hj-home-hero__title">آنچه برای مراقبت‌های قبل و بعد از جراحی باید بدانید</h1>

			<div class="hj-home-hero__search"><?php get_search_form(); ?></div>

			<!-- ابزار ارزیابی علائم: برگهٔ افزونهٔ «دستیار نشانه‌ها» (نامک symptom-checker).
			     صفحهٔ آزمایشی قدیمی قالب (inc/symptom-checker-test.php) در فاز ۰ بازنشسته شد،
			     چون توابع hj_sc_* آن با افزونه هم‌نام بود و فعال‌سازی را می‌شکست. دکمه فقط
			     وقتی نمایش داده می‌شود که آن برگه منتشر شده باشد، تا هیچ‌وقت لینک ۴۰۴ ندهیم. -->
			<div class="hj-home-hero__actions">
				<?php
				// نامک برگهٔ ابزار، همان ثابت افزونه است؛ اگر افزونه خاموش باشد، همان مقدار پیش‌فرض.
				$hj_symptom_slug  = defined( 'HJ_SC_PAGE_SLUG' ) ? HJ_SC_PAGE_SLUG : 'symptom-checker';
				$hj_symptom_pages = get_posts(
					array(
						'post_type'     => 'page',
						'post_status'   => 'publish',
						'name'          => $hj_symptom_slug,
						'numberposts'   => 1,
						'no_found_rows' => true,
					)
				);
				$hj_symptom_url = $hj_symptom_pages ? get_permalink( $hj_symptom_pages[0] ) : '';
				?>
				<?php if ( $hj_symptom_url ) : ?>
					<a class="hj-button hj-button--light" href="<?php echo esc_url( $hj_symptom_url ); ?>">ابزار هوشمند ارزیابی علائم بیماری</a>
				<?php endif; ?>
				<a class="hj-button hj-button--light" href="<?php echo esc_url( hj_archive_url( 'patient_guide' ) ); ?>">شروع از راهنمای بیمار</a>
				<a class="hj-button hj-button--outline-light" href="<?php echo esc_url( hj_archive_url( 'or_environment' ) ); ?>">شناخت اتاق عمل و تیم</a>
			</div>
		</section>

		<section id="hj-care-tool" class="hj-home-section" aria-labelledby="hj-care-tool-title">
			<div class="hj-care-tool">
				<div class="hj-care-tool__head">
					<h2 id="hj-care-tool-title">دستیار مراقبت: امروز روز چندم بعد از عمل است؟</h2>
					<p>نام عمل و روزی که در آن هستید را انتخاب کنید؛ چک‌لیست کوتاه همان مرحله و علائم هشدار را می‌بینید. این ابزار آموزش عمومی است، نه جایگزین دستور تیم درمان.</p>
				</div>

				<?php if ( $hj_care_surgeries ) : ?>
					<div class="hj-care-tool__form">
						<div class="hj-care-tool__field">
							<label for="hj-care-surgery">نوع جراحی</label>
							<select id="hj-care-surgery">
								<?php foreach ( $hj_care_surgeries as $hj_care_post ) : ?>
									<option value="<?php echo esc_attr( (string) $hj_care_post->ID ); ?>" data-url="<?php echo esc_url( get_permalink( $hj_care_post ) ); ?>" data-title="<?php echo esc_attr( get_the_title( $hj_care_post ) ); ?>"><?php echo esc_html( function_exists( 'hj_care_option_label' ) ? hj_care_option_label( get_the_title( $hj_care_post ) ) : get_the_title( $hj_care_post ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="hj-care-tool__field">
							<label for="hj-care-stage">روز بعد از عمل</label>
							<select id="hj-care-stage">
								<?php foreach ( $hj_care_stages as $hj_stage_key => $hj_stage ) : ?>
									<option value="<?php echo esc_attr( $hj_stage_key ); ?>"><?php echo esc_html( function_exists( 'hj_care_option_label' ) ? hj_care_option_label( $hj_stage['title'] ) : $hj_stage['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<button type="button" class="hj-button" id="hj-care-run">نمایش راهنمای این مرحله</button>
					</div>

					<div class="hj-care-tool__result" id="hj-care-result" aria-live="polite" hidden></div>

					<div class="hj-care-tool__fallback">
					</div>

					<script type="application/json" id="hj-care-data"><?php echo wp_json_encode( $hj_care_stages, JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON data. ?></script>
					<script>
						(function () {
							var data = document.getElementById('hj-care-data');
							var run = document.getElementById('hj-care-run');
							var box = document.getElementById('hj-care-result');
							var surgery = document.getElementById('hj-care-surgery');
							var stage = document.getElementById('hj-care-stage');
							if (!data || !run || !box || !surgery || !stage) { return; }
							var stages = {};
							try { stages = JSON.parse(data.textContent); } catch (e) { return; }
							run.addEventListener('click', function () {
								var chosen = stages[stage.value];
								if (!chosen) { return; }
								box.textContent = '';
								var h = document.createElement('h3');
								h.textContent = chosen.title;
								box.appendChild(h);
								var ul = document.createElement('ul');
								chosen.points.forEach(function (point) {
									var li = document.createElement('li');
									li.textContent = point;
									ul.appendChild(li);
								});
								box.appendChild(ul);
								var flag = document.createElement('p');
								flag.className = 'hj-care-tool__flag';
								flag.textContent = 'علائم هشدار: تب، تنگی نفس، درد شدید و فزاینده، خون‌ریزی یا ترشح چرکی — با مرکز جراحی تماس بگیرید و در وضعیت اورژانسی با ۱۱۵.';
								box.appendChild(flag);
								var link = document.createElement('p');
								link.className = 'hj-care-tool__links';
								var a = document.createElement('a');
								a.href = surgery.options[surgery.selectedIndex].getAttribute('data-url') || '';
								a.textContent = 'راهنمای کامل ' + (surgery.options[surgery.selectedIndex].getAttribute('data-title') || surgery.options[surgery.selectedIndex].textContent);
								link.appendChild(a);
								box.appendChild(link);
								box.hidden = false;
							});
						})();
					</script>
				<?php else : ?>
					<div class="hj-care-tool__fallback">
						<p>به‌محض انتشار نخستین راهنماهای جراحی، انتخاب عمل در همین ابزار فعال می‌شود. فعلاً از <a href="<?php echo esc_url( hj_archive_url( 'patient_guide' ) ); ?>">راهنمای بیمار</a> شروع کنید.</p>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section id="hj-journey" class="hj-home-section" aria-labelledby="hj-journey-title">
			<div class="hj-home-section__head">
				<h2 id="hj-journey-title">در کدام مرحله‌اید؟</h2>
				<p>همین حالا همان مرحله‌ای را انتخاب کنید که در آن هستید؛ لازم نیست همه‌ی مسیر را یک‌جا بخوانید.</p>
			</div>
			<div class="hj-journey-grid">
				<?php foreach ( $hj_journey as $hj_step ) : ?>
					<article class="hj-journey-card">
						<h3><a href="<?php echo esc_url( hj_term_url( 'guide_stage', $hj_step['stage'], 'patient_guide' ) ); ?>"><?php echo esc_html( $hj_step['title'] ); ?></a></h3>
						<p><?php echo esc_html( $hj_step['text'] ); ?></p>
						<a class="hj-text-link" href="<?php echo esc_url( hj_term_url( 'guide_stage', $hj_step['stage'], 'patient_guide' ) ); ?>">مشاهده‌ی راهنماهای این مرحله</a>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<section id="hj-latest" class="hj-home-section" aria-labelledby="hj-latest-title">
			<div class="hj-home-section__head">
				<h2 id="hj-latest-title">تازه‌ترین مقاله‌های منتشرشده</h2>
				<p>
					<?php esc_html_e( 'هر مقاله پیش از انتشار از گیت تحریریه می‌گذرد: تحقیق از منابع معتبر، ویرایش فارسی، کنترل ساختار و پیوندها.', 'lax-theme-child' ); ?>
				</p>
			</div>

			<?php if ( $hj_latest->have_posts() ) : ?>
				<div class="orsite-card-grid hj-home-card-grid">
					<?php
					while ( $hj_latest->have_posts() ) :
						$hj_latest->the_post();
						get_template_part(
							'template-parts/article',
							'card',
							array(
								'heading_level' => 'h3',
								'show_review'   => true,
							)
						);
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			<?php else : ?>
				<div class="hj-home-empty">
					<h3>نخستین مقاله‌های ستونی در مسیر انتشارند</h3>
					<p>ستون‌های «مسیر بیمار»، «اتاق عمل چیست»، «اصطلاحات اتاق عمل» و «تیم جراحی و مراقبت» یکی‌یکی منتشر می‌شوند. ما برای سرعت، متن نیمه‌تمام منتشر نمی‌کنیم.</p>
					<p class="hj-home-empty__actions">
						<a class="hj-button" href="<?php echo esc_url( hj_page_url( 'about' ) ); ?>">همیار جراحی را بشناسید</a>
					</p>
				</div>
			<?php endif; ?>
		</section>

		<section id="hj-sections" class="hj-home-section" aria-labelledby="hj-sections-title">
			<div class="hj-home-section__head">
				<h2 id="hj-sections-title">بخش‌های اصلی مجله</h2>
				<p>ساختار ثابت سایت کمک می‌کند هر بار از همان نقطه‌ای شروع کنید که پرسشتان همان‌جاست.</p>
			</div>
			<div class="hj-section-strip">
				<?php foreach ( $hj_sections as $hj_section ) : ?>
					<?php
					$hj_counts    = wp_count_posts( $hj_section['post_type'] );
					$hj_published = ( $hj_counts && isset( $hj_counts->publish ) ) ? (int) $hj_counts->publish : 0;
					?>
					<a class="hj-section-bar" href="<?php echo esc_url( hj_archive_url( $hj_section['post_type'] ) ); ?>">
						<span class="hj-section-bar__title"><?php echo esc_html( $hj_section['title'] ); ?></span>
						<span class="hj-section-bar__meta">
							<?php if ( $hj_published ) : ?>
								<span><?php echo esc_html( number_format_i18n( $hj_published ) ); ?> مطلب منتشرشده</span>
							<?php endif; ?>
							<span class="hj-section-bar__arrow" aria-hidden="true">&larr;</span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<section id="hj-surgeries" class="hj-home-section" aria-labelledby="hj-surgeries-title">
			<div class="hj-home-section__head">
				<h2 id="hj-surgeries-title">جراحی‌ها بر اساس تخصص</h2>
				<p>هر جراحی با همان الگوی قابل پیش‌بینی منتشر می‌شود: معرفی و مراحل عمل، آمادگی پیش از عمل و مراقبت پس از عمل.</p>
			</div>

			<?php if ( $hj_surgery_groups ) : ?>
				<div class="hj-surgery-grid">
					<?php foreach ( $hj_surgery_groups as $hj_group ) : ?>
						<?php
						$hj_group_link  = get_term_link( $hj_group );
						$hj_group_label = function_exists( 'hj_home_specialty_label' ) ? hj_home_specialty_label( $hj_group->name, $hj_group->slug ) : $hj_group->name;
						?>
						<article class="hj-surgery-card">
							<h3>
								<?php if ( ! is_wp_error( $hj_group_link ) ) : ?>
									<a href="<?php echo esc_url( $hj_group_link ); ?>"><?php echo esc_html( $hj_group_label ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $hj_group_label ); ?>
								<?php endif; ?>
							</h3>
							<p><?php echo esc_html( $hj_group->description ? wp_trim_words( wp_strip_all_tags( $hj_group->description ), 22, '…' ) : 'راهنماهای منتشرشده‌ی این تخصص را با الگوی ثابت پیش، حین و پس از عمل بخوانید.' ); ?></p>
							<?php if ( ! is_wp_error( $hj_group_link ) ) : ?>
								<a class="hj-text-link" href="<?php echo esc_url( $hj_group_link ); ?>">مشاهده‌ی جراحی‌های این تخصص</a>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="hj-home-empty hj-home-empty--soft">
					<h3>صفحه‌های جراحی‌ها به‌تدریج کامل می‌شوند</h3>
					<p>صفحه‌های خالی را به‌عنوان مقاله جا نمی‌زنیم و تا وقتی یک بخش به حد کافی مقاله نداشته باشد، آن را از گوگل بیرون نگه می‌داریم. از راهنمای کلی مسیر بیمار شروع کنید و برای عمل مشخص خودتان از تیم درمان راهنمایی بگیرید.</p>
					<a class="hj-text-link" href="<?php echo esc_url( hj_archive_url( 'surgery' ) ); ?>">مشاهده‌ی بخش جراحی‌ها</a>
				</div>
			<?php endif; ?>

		</section>

		<section id="hj-glossary" class="hj-home-section" aria-labelledby="hj-glossary-topics-title">
			<div class="hj-glossary-strip">
				<h3 id="hj-glossary-topics-title">از واژه‌نامه شروع کنید</h3>
				<nav aria-labelledby="hj-glossary-topics-title">
					<ul class="hj-glossary-topic-list">
						<?php foreach ( $hj_glossary_topics as $hj_topic ) : ?>
							<li>
								<a class="hj-glossary-topic-list__link" href="<?php echo esc_url( $hj_topic['url'] ); ?>" title="<?php echo esc_attr( $hj_topic['name'] ); ?>">
									<span class="hj-glossary-topic-list__label"><?php echo esc_html( $hj_topic['name'] ); ?></span>
									<span class="hj-glossary-topic-list__arrow" aria-hidden="true">‹</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
				<a class="hj-text-link" href="<?php echo esc_url( hj_archive_url( 'glossary' ) ); ?>">همه‌ی اصطلاحات اتاق عمل</a>
			</div>
		</section>

		<section id="hj-faq" class="hj-home-section" aria-labelledby="hj-faq-title">
			<div class="hj-home-section__head">
				<h2 id="hj-faq-title">پاسخ کوتاه به پرسش‌های پرتکرار</h2>
				<p>این پاسخ‌ها درباره‌ی نحوه‌ی استفاده از مجله‌اند، نه پاسخ درمانی برای یک بیمار مشخص.</p>
			</div>
			<div class="hj-faq-list">
				<?php foreach ( $hj_faqs as $hj_index => $hj_faq ) : ?>
					<details<?php echo 0 === $hj_index ? ' open' : ''; ?>>
						<summary><?php echo esc_html( $hj_faq['q'] ); ?></summary>
						<p><?php echo esc_html( $hj_faq['a'] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
			<p class="hj-faq-more"><a class="hj-text-link" href="<?php echo esc_url( hj_page_url( 'faq' ) ); ?>">همه‌ی پرسش‌های متداول درباره‌ی همیار جراحی</a></p>
		</section>

		<section class="hj-contact-band" aria-labelledby="hj-contact-title">
			<div>
				<h2 id="hj-contact-title">موضوعی هست که باید درباره‌اش بنویسیم؟</h2>
				<p>پیشنهاد موضوع، گزارش خطا یا درخواست افزودن یک اصطلاح را برای تحریریه بفرستید. پرسش درمانی فردی را لطفاً با تیم درمان مطرح کنید.</p>
			</div>
			<div class="hj-contact-band__actions">
				<a class="hj-button" href="mailto:mail@hamyarjarahi.ir">mail@hamyarjarahi.ir</a>
				<a class="hj-button hj-button--ghost" href="<?php echo esc_url( hj_page_url( 'contact' ) ); ?>">صفحه‌ی تماس</a>
			</div>
		</section>

	</main>
</div>

<?php
get_footer();
