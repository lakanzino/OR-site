<?php
/**
 * Content architecture: custom post types + hierarchical taxonomies.
 *
 * Load from the CHILD theme's functions.php:
 * require_once get_stylesheet_directory() . '/inc/content-types.php';
 *
 * Structure:  CPT (main category)  ->  taxonomy (parent terms -> child terms)
 * To add terms later: edit orsite_content_map() and bump ORSITE_STRUCTURE_VERSION.
 */

defined( 'ABSPATH' ) || exit;

const ORSITE_STRUCTURE_VERSION = '1.0.0';

/* -------------------------------------------------------------------------
 * 1. Content map: single source of truth for types, taxonomies and terms
 * ---------------------------------------------------------------------- */

function orsite_content_map(): array {
	return array(

		// Main category 1: patient journey guides (pillars + before/during/after).
		'patient_guide' => array(
			'singular' => 'راهنمای بیمار',
			'plural'   => 'راهنمای بیمار',
			'slug'     => 'patient-guide',
			'icon'     => 'dashicons-heart',
			'taxonomies' => array(
				'guide_stage' => array(
					'singular' => 'مرحله',
					'plural'   => 'مراحل مسیر بیمار',
					'slug'     => 'guide-stage',
					'terms'    => array(
						'pillars' => array( 'name' => 'ستون‌های اصلی' ),
						'before'  => array(
							'name'     => 'قبل از عمل',
							'children' => array(
								'general-preparation' => 'آمادگی عمومی',
								'questions-to-ask'    => 'سؤال‌هایی که باید پرسید',
								'anxiety-support'     => 'اضطراب و حمایت روانی',
								'day-of-surgery'      => 'روز عمل و پذیرش',
								'consent-anesthesia'  => 'رضایت‌نامه و مشاوره‌ی بیهوشی',
							),
						),
						'during'  => array(
							'name'     => 'حین عمل',
							'children' => array(
								'general-steps'       => 'مراحل کلی عمل',
								'anesthesia-types'    => 'انواع بیهوشی و بی‌حسی',
								'sterile-environment' => 'استریل و محیط اتاق عمل',
								'patient-safety'      => 'ایمنی بیمار',
							),
						),
						'after'   => array(
							'name'     => 'بعد از عمل و ریکاوری',
							'children' => array(
								'recovery'             => 'ریکاوری',
								'normal-symptoms'      => 'درد و علائم طبیعی',
								'infection-prevention' => 'پیشگیری از عفونت',
								'discharge'            => 'ترخیص',
								'warning-signs'        => 'علائم هشدار',
								'return-to-life'       => 'بازگشت به زندگی عادی',
							),
						),
						'special-groups' => array(
							'name'     => 'گروه‌های ویژه',
							'children' => array(
								'children'           => 'کودکان',
								'elderly'            => 'سالمندان',
								'emergency-elective' => 'جراحی اورژانس و انتخابی',
							),
						),
					),
				),
			),
		),

		// Main category 2: operating room environment, team and equipment.
		'or_environment' => array(
			'singular' => 'محیط و تجهیزات',
			'plural'   => 'اتاق عمل، تیم و تجهیزات',
			'slug'     => 'or-environment',
			'icon'     => 'dashicons-building',
			'taxonomies' => array(
				'env_section' => array(
					'singular' => 'بخش',
					'plural'   => 'بخش‌های اتاق عمل',
					'slug'     => 'env-section',
					'terms'    => array(
						'spaces'  => array(
							'name'     => 'فضاها و واحدها',
							'children' => array(
								'operating-room' => 'اتاق عمل',
								'pacu'           => 'ریکاوری (PACU)',
								'cssd'           => 'واحد استریل مرکزی (CSSD)',
								'ventilation'    => 'تهویه و هوای اتاق عمل',
							),
						),
						'team'    => array(
							'name'     => 'اعضای تیم',
							'children' => array(
								'surgeon'          => 'جراح و دستیار',
								'anesthesia-team'  => 'تیم بیهوشی',
								'scrub-technician' => 'تکنیسین اسکراب',
								'circulator'       => 'تکنیسین سیرکولر',
								'recovery-nurse'   => 'پرستار ریکاوری و بخش',
								'support-staff'    => 'پرسنل استریلیزاسیون و خدمات',
							),
						),
						'devices' => array(
							'name'     => 'دستگاه‌ها و تجهیزات',
							'children' => array(
								'vital-monitor'      => 'مانیتور علائم حیاتی',
								'anesthesia-machine' => 'دستگاه بیهوشی',
								'light-and-table'    => 'چراغ و تخت جراحی',
								'suction'            => 'ساکشن',
								'electrosurgery'     => 'الکتروسرجری (کوتر)',
								'instrument-set'     => 'ست ابزار جراحی',
								'counting'           => 'شمارش گاز و ابزار',
								'laparoscopy-tower'  => 'برج لاپاروسکوپی',
							),
						),
					),
				),
			),
		),

		// Main category 3: surgeries (specialty -> surgery) + article stage.
		'surgery' => array(
			'singular' => 'جراحی',
			'plural'   => 'جراحی‌ها',
			'slug'     => 'surgery',
			'icon'     => 'dashicons-clipboard',
			'taxonomies' => array(
				'surgery_specialty' => array(
					'singular' => 'تخصص و نوع عمل',
					'plural'   => 'تخصص‌ها و عمل‌ها',
					'slug'     => 'surgery-specialty',
					'terms'    => array(
						'general-surgery' => array(
							'name'     => 'جراحی عمومی و گوارش',
							'children' => array(
								'appendectomy'        => 'آپاندکتومی (آپاندیس)',
								'general-laparoscopy' => 'لاپاروسکوپی عمومی',
								'cholecystectomy'     => 'کوله‌سیستکتومی (کیسه صفرا)',
								'hernia-repair'       => 'جراحی فتق',
								'hemorrhoid-anal'     => 'هموروئید و جراحی‌های مقعد',
								'fistula-fissure'     => 'فیستول و شقاق',
								'thyroidectomy'       => 'تیروئیدکتومی',
								'bariatric'           => 'جراحی چاقی',
							),
						),
						'gynecology' => array(
							'name'     => 'زنان و زایمان',
							'children' => array(
								'cesarean'        => 'سزارین',
								'hysterectomy'    => 'هیسترکتومی',
								'myomectomy-cyst' => 'میومکتومی و کیست تخمدان',
								'tubal-ligation'  => 'بستن لوله‌ها',
							),
						),
						'urology' => array(
							'name'     => 'ارولوژی',
							'children' => array(
								'kidney-stone'  => 'سنگ کلیه و حالب',
								'prostate-turp' => 'جراحی پروستات',
								'vasectomy'     => 'وازکتومی',
								'varicocele'    => 'واریکوسل',
							),
						),
						'ent' => array(
							'name'     => 'گوش، حلق و بینی',
							'children' => array(
								'tonsillectomy'     => 'لوزه و آدنوئید',
								'septoplasty-sinus' => 'سپتوپلاستی و جراحی سینوس',
							),
						),
						'eye' => array(
							'name'     => 'چشم',
							'children' => array(
								'cataract' => 'کاتاراکت (آب مروارید)',
							),
						),
						'orthopedics' => array(
							'name'     => 'ارتوپدی',
							'children' => array(
								'knee-arthroscopy'  => 'آرتروسکوپی زانو',
								'carpal-tunnel'     => 'تونل کارپ',
								'joint-replacement' => 'تعویض مفصل',
								'fracture-fixation' => 'جراحی شکستگی',
							),
						),
						'vascular-cardiac' => array(
							'name'     => 'قلب و عروق',
							'children' => array(
								'cabg'             => 'بای‌پس عروق کرونر',
								'varicose-veins'   => 'جراحی واریس',
								'dialysis-fistula' => 'فیستول دیالیز',
							),
						),
						'neurosurgery' => array(
							'name'     => 'مغز و اعصاب',
							'children' => array(
								'brain-surgery' => 'جراحی مغز',
								'spine-disc'    => 'ستون فقرات و دیسک کمر',
							),
						),
						'breast' => array(
							'name'     => 'پستان',
							'children' => array(
								'breast-biopsy' => 'بیوپسی و جراحی خوش‌خیم پستان',
								'mastectomy'    => 'ماستکتومی',
							),
						),
						'cosmetic' => array(
							'name'     => 'زیبایی',
							'children' => array(
								'rhinoplasty' => 'رینوپلاستی',
							),
						),
						'pediatric-surgery' => array(
							'name'     => 'جراحی کودکان',
							'children' => array(
								'circumcision' => 'ختنه',
							),
						),
					),
				),

				// Maps to the 3-article pattern: overview / before / after.
				'surgery_stage' => array(
					'singular' => 'نوع مقاله',
					'plural'   => 'نوع مقاله‌ی عمل',
					'slug'     => 'surgery-stage',
					'terms'    => array(
						'surgery-overview' => array( 'name' => 'معرفی و مراحل عمل' ),
						'before-surgery'   => array( 'name' => 'قبل از عمل' ),
						'after-surgery'    => array( 'name' => 'بعد از عمل' ),
						'surgery-extra'    => array( 'name' => 'مقالات تکمیلی' ),
					),
				),
			),
		),

		// Main category 4: student content.
		'student_content' => array(
			'singular' => 'محتوای دانشجویی',
			'plural'   => 'ویژه‌ی دانشجویان',
			'slug'     => 'student',
			'icon'     => 'dashicons-welcome-learn-more',
			'taxonomies' => array(
				'student_topic' => array(
					'singular' => 'موضوع',
					'plural'   => 'موضوعات دانشجویی',
					'slug'     => 'student-topic',
					'terms'    => array(
						'asepsis' => array(
							'name'     => 'آسپسیس و استریلیزاسیون',
							'children' => array(
								'surgical-scrub'  => 'اسکراب جراحی دست‌ها',
								'sterile-field'   => 'میدان استریل',
								'gowning-gloving' => 'گان و دستکش',
								'sterilization'   => 'استریلیزاسیون و ضدعفونی',
							),
						),
						'instruments' => array(
							'name'     => 'ابزار و میز عمل',
							'children' => array(
								'instrument-classes' => 'دسته‌بندی ابزار',
								'table-setup'        => 'چیدمان میز ابزار',
								'student-counting'   => 'شمارش گاز و ابزار',
							),
						),
						'safety' => array(
							'name'     => 'ایمنی',
							'children' => array(
								'fire-electric-radiation' => 'آتش، برق و تشعشع',
								'patient-positioning'     => 'وضعیت‌دهی بیمار',
								'who-checklist'           => 'چک‌لیست ایمنی جراحی',
							),
						),
						'professional-skills' => array(
							'name'     => 'مهارت‌های حرفه‌ای',
							'children' => array(
								'scrub-vs-circulator' => 'نقش اسکراب و سیرکولر',
								'team-communication'  => 'ارتباط مؤثر در تیم',
								'internship-prep'     => 'آمادگی کارآموزی',
							),
						),
					),
				),
			),
		),

		// Main category 5: glossary.
		'glossary' => array(
			'singular' => 'اصطلاح',
			'plural'   => 'واژه‌نامه',
			'slug'     => 'glossary',
			'icon'     => 'dashicons-book-alt',
			'taxonomies' => array(
				'glossary_group' => array(
					'singular' => 'گروه اصطلاح',
					'plural'   => 'گروه‌های واژه‌نامه',
					'slug'     => 'glossary-group',
					'terms'    => array(
						'general-terms'    => array( 'name' => 'اصطلاحات عمومی جراحی' ),
						'anesthesia-terms' => array( 'name' => 'اصطلاحات بیهوشی' ),
						'sterile-terms'    => array( 'name' => 'اصطلاحات استریل' ),
						'device-terms'     => array( 'name' => 'ابزار و دستگاه' ),
					),
				),
			),
		),

	);
}

/* -------------------------------------------------------------------------
 * 2. Label helpers
 * ---------------------------------------------------------------------- */

function orsite_post_type_labels( string $singular, string $plural ): array {
	return array(
		'name'               => $plural,
		'singular_name'      => $singular,
		'menu_name'          => $plural,
		'all_items'          => 'همه‌ی ' . $plural,
		'add_new'            => 'افزودن جدید',
		'add_new_item'       => 'افزودن ' . $singular . ' جدید',
		'edit_item'          => 'ویرایش ' . $singular,
		'new_item'           => $singular . ' جدید',
		'view_item'          => 'مشاهده‌ی ' . $singular,
		'search_items'       => 'جستجوی ' . $plural,
		'not_found'          => 'موردی یافت نشد',
		'not_found_in_trash' => 'موردی در زباله‌دان نیست',
	);
}

function orsite_taxonomy_labels( string $singular, string $plural ): array {
	return array(
		'name'              => $plural,
		'singular_name'     => $singular,
		'menu_name'         => $plural,
		'all_items'         => 'همه‌ی ' . $plural,
		'edit_item'         => 'ویرایش ' . $singular,
		'view_item'         => 'مشاهده‌ی ' . $singular,
		'update_item'       => 'به‌روزرسانی ' . $singular,
		'add_new_item'      => 'افزودن ' . $singular . ' جدید',
		'new_item_name'     => 'نام ' . $singular . ' جدید',
		'parent_item'       => $singular . ' والد',
		'parent_item_colon' => $singular . ' والد:',
		'search_items'      => 'جستجوی ' . $plural,
		'not_found'         => 'موردی یافت نشد',
	);
}

/* -------------------------------------------------------------------------
 * 3. Register post types and taxonomies
 * ---------------------------------------------------------------------- */

function orsite_register_content_types(): void {
	$position = 21;

	foreach ( orsite_content_map() as $type => $def ) {
		register_post_type(
			$type,
			array(
				'labels'        => orsite_post_type_labels( $def['singular'], $def['plural'] ),
				'public'        => true,
				'has_archive'   => $def['slug'],
				'rewrite'       => array( 'slug' => $def['slug'], 'with_front' => false ),
				'show_in_rest'  => true, // Block editor + REST API.
				'menu_icon'     => $def['icon'],
				'menu_position' => $position++,
				'taxonomies'    => array_keys( $def['taxonomies'] ),
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ),
			)
		);

		foreach ( $def['taxonomies'] as $taxonomy => $tax_def ) {
			register_taxonomy(
				$taxonomy,
				$type,
				array(
					'labels'            => orsite_taxonomy_labels( $tax_def['singular'], $tax_def['plural'] ),
					'hierarchical'      => true, // Parent / child terms with checkboxes.
					'public'            => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array( 'slug' => $tax_def['slug'], 'hierarchical' => true, 'with_front' => false ),
				)
			);
		}
	}
}
add_action( 'init', 'orsite_register_content_types' );

/* -------------------------------------------------------------------------
 * 4. Seed default terms once per structure version
 * ---------------------------------------------------------------------- */

function orsite_ensure_term( string $name, string $slug, string $taxonomy, int $parent = 0 ): int {
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing ) {
		return (int) $existing->term_id;
	}

	$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'parent' => $parent ) );
	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

function orsite_seed_terms(): void {
	if ( get_option( 'orsite_structure_version' ) === ORSITE_STRUCTURE_VERSION ) {
		return;
	}

	foreach ( orsite_content_map() as $def ) {
		foreach ( $def['taxonomies'] as $taxonomy => $tax_def ) {
			foreach ( $tax_def['terms'] as $slug => $node ) {
				$parent_id = orsite_ensure_term( $node['name'], $slug, $taxonomy );
				if ( ! $parent_id || empty( $node['children'] ) ) {
					continue;
				}
				foreach ( $node['children'] as $child_slug => $child_name ) {
					orsite_ensure_term( $child_name, $child_slug, $taxonomy, $parent_id );
				}
			}
		}
	}

	flush_rewrite_rules( false );
	update_option( 'orsite_structure_version', ORSITE_STRUCTURE_VERSION );
}
add_action( 'init', 'orsite_seed_terms', 99 );
