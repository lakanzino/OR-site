<?php
/**
 * Shared Interactive Glossary Directory Component (`/glossary/`, `/glossary-group/{slug}/`, `[hj_glossary]`).
 *
 * Server-rendered for full Google SEO + progressively enhanced with instant AJAX
 * search, topic chips, Persian/English alphabet filter, and pagination for 5,000+ terms.
 *
 * @package Lax_Theme_Child
 * @since   0.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hj_gl_stats    = hj_glossary_get_stats();
$hj_gl_groups   = hj_glossary_get_groups_with_counts( false );
$hj_gl_settings = hj_glossary_get_settings();
$hj_gl_per_page = (int) ( $hj_gl_settings['per_page'] ?? 60 );
$hj_gl_hub_url  = function_exists( 'hj_archive_url' ) ? hj_archive_url( 'glossary' ) : home_url( '/glossary/' );

$hj_init_cat = '';
if ( ! empty( $GLOBALS['hj_glossary_forced_group_slug'] ) ) {
	$hj_init_cat = (string) $GLOBALS['hj_glossary_forced_group_slug'];
} elseif ( is_tax( 'glossary_group' ) ) {
	$q_obj = get_queried_object();
	if ( $q_obj && ! empty( $q_obj->slug ) ) {
		$hj_init_cat = (string) $q_obj->slug;
	}
} elseif ( isset( $_GET['gcat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$hj_init_cat = sanitize_title( wp_unslash( $_GET['gcat'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

$hj_init_q      = isset( $_GET['gq'] ) ? sanitize_text_field( wp_unslash( $_GET['gq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$hj_init_letter = isset( $_GET['gletter'] ) ? sanitize_text_field( wp_unslash( $_GET['gletter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$hj_init_page   = isset( $_GET['gpage'] ) ? max( 1, (int) $_GET['gpage'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$hj_result = hj_glossary_query_terms(
	array(
		'status'     => 'publish',
		'search'     => $hj_init_q,
		'group_slug' => $hj_init_cat,
		'letter'     => $hj_init_letter,
		'page'       => $hj_init_page,
		'per_page'   => $hj_gl_per_page,
	)
);

$hj_active_group_obj = null;
if ( '' !== $hj_init_cat ) {
	foreach ( $hj_gl_groups as $g ) {
		if ( $g['slug'] === $hj_init_cat ) {
			$hj_active_group_obj = $g;
			break;
		}
	}
}

$hj_fa = static function ( $val ): string {
	return function_exists( 'hj_fa_digits' ) ? hj_fa_digits( (string) $val ) : (string) $val;
};

$hj_heading = $hj_active_group_obj
	? 'واژه‌نامه: ' . $hj_active_group_obj['name']
	: 'واژه‌نامه‌ی تخصصی اتاق عمل و جراحی';

$hj_subdesc = ( $hj_active_group_obj && '' !== trim( $hj_active_group_obj['description'] ) )
	? $hj_active_group_obj['description']
	: 'معنای ساده، دقیق و یک‌خطی اصطلاحات پزشکی، بیهوشی، ابزار جراحی و مراقبت‌های قبل و بعد از عمل. هر زمان این واژگان در متن مقالات سایت بیایند، با رنگ سبز مشخص می‌شوند تا با نگه‌داشتن موس یا لمس، تعریف آن‌ها را درجا ببینید.';
?>

<section
	id="hj-glossary-directory"
	class="hj-glossary-hub"
	data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-hub-url="<?php echo esc_url( $hj_gl_hub_url ); ?>"
	data-init-q="<?php echo esc_attr( $hj_init_q ); ?>"
	data-init-cat="<?php echo esc_attr( $hj_init_cat ); ?>"
	data-init-letter="<?php echo esc_attr( $hj_init_letter ); ?>"
	data-init-page="<?php echo esc_attr( (string) $hj_result['page'] ); ?>"
>
	<header class="hj-glossary-hub__hero">
		<div class="hj-glossary-hub__top">
			<h1 class="hj-glossary-hub__title"><?php echo esc_html( $hj_heading ); ?></h1>
			<?php if ( $hj_gl_stats['published'] > 0 ) : ?>
				<span class="hj-glossary-hub__count-pill">
					<?php echo esc_html( $hj_fa( number_format_i18n( $hj_gl_stats['published'] ) ) . ' اصطلاح تخصصی' ); ?>
				</span>
			<?php endif; ?>
		</div>
		<p class="hj-glossary-hub__desc"><?php echo esc_html( $hj_subdesc ); ?></p>

		<form class="hj-glossary-search" method="get" action="<?php echo esc_url( $hj_gl_hub_url ); ?>" role="search">
			<?php if ( '' !== $hj_init_cat ) : ?>
				<input type="hidden" name="gcat" value="<?php echo esc_attr( $hj_init_cat ); ?>" />
			<?php endif; ?>
			<label class="screen-reader-text" for="hj-glossary-search-input">جستجو در واژه‌نامه</label>
			<input
				type="search"
				id="hj-glossary-search-input"
				class="hj-glossary-search__input"
				name="gq"
				value="<?php echo esc_attr( $hj_init_q ); ?>"
				placeholder="جستجوی فوری واژه‌ی فارسی، انگلیسی یا کلیدواژه در تعریف..."
				autocomplete="off"
			/>
			<button
				type="button"
				id="hj-glossary-search-clear"
				class="hj-glossary-search__clear <?php echo '' !== $hj_init_q ? 'is-visible' : ''; ?>"
				aria-label="پاک کردن جستجو"
			>×</button>
		</form>
	</header>

	<?php if ( ! empty( $hj_gl_groups ) ) : ?>
		<nav class="hj-glossary-topics" aria-label="دسته‌بندی موضوعات واژگان">
			<a
				href="<?php echo esc_url( $hj_gl_hub_url ); ?>"
				data-gcat=""
				class="hj-glossary-topic-chip <?php echo '' === $hj_init_cat ? 'is-active' : ''; ?>"
			>
				<span>همه‌ی موضوعات</span>
				<span class="hj-glossary-topic-chip__num"><?php echo esc_html( $hj_fa( number_format_i18n( $hj_gl_stats['published'] ) ) ); ?></span>
			</a>
			<?php foreach ( $hj_gl_groups as $g ) : ?>
				<a
					href="<?php echo esc_url( $g['url'] ); ?>"
					data-gcat="<?php echo esc_attr( $g['slug'] ); ?>"
					class="hj-glossary-topic-chip <?php echo $g['slug'] === $hj_init_cat ? 'is-active' : ''; ?>"
				>
					<span><?php echo esc_html( $g['name'] ); ?></span>
					<span class="hj-glossary-topic-chip__num"><?php echo esc_html( $hj_fa( number_format_i18n( $g['count'] ) ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<nav class="hj-glossary-alpha" aria-label="فیلتر بر اساس حرف اول واژه">
		<a
			href="<?php echo esc_url( $hj_gl_hub_url ); ?>"
			data-gletter=""
			class="hj-glossary-alpha__btn <?php echo '' === $hj_init_letter ? 'is-active' : ''; ?>"
		>همه</a>
		<?php foreach ( hj_glossary_persian_alphabet() as $ltr ) : ?>
			<?php $has_ltr = ! empty( $hj_gl_stats['letters'][ $ltr ] ); ?>
			<a
				href="<?php echo esc_url( add_query_arg( 'gletter', $ltr, $hj_gl_hub_url ) ); ?>"
				data-gletter="<?php echo esc_attr( $ltr ); ?>"
				class="hj-glossary-alpha__btn <?php echo $ltr === $hj_init_letter ? 'is-active' : ''; ?> <?php echo ! $has_ltr ? 'is-empty' : ''; ?>"
			><?php echo esc_html( $ltr ); ?></a>
		<?php endforeach; ?>
		<?php $has_en = ! empty( $hj_gl_stats['letters']['EN'] ); ?>
		<a
			href="<?php echo esc_url( add_query_arg( 'gletter', 'EN', $hj_gl_hub_url ) ); ?>"
			data-gletter="EN"
			class="hj-glossary-alpha__btn <?php echo 'EN' === $hj_init_letter ? 'is-active' : ''; ?> <?php echo ! $has_en ? 'is-empty' : ''; ?>"
			dir="ltr"
		>A–Z</a>
	</nav>

	<div class="hj-glossary-status">
		<span id="hj-glossary-status-text">
			<?php if ( $hj_result['total'] > 0 ) : ?>
				<?php
				echo esc_html(
					sprintf(
						'نمایش %s واژه از مجموع %s نتیجه (صفحه‌ی %s از %s)',
						$hj_fa( count( $hj_result['items'] ) ),
						$hj_fa( number_format_i18n( $hj_result['total'] ) ),
						$hj_fa( $hj_result['page'] ),
						$hj_fa( $hj_result['pages'] )
					)
				);
				?>
			<?php elseif ( 0 === $hj_gl_stats['published'] ) : ?>
				بانک واژه‌نامه آماده است و به‌محض ورود واژگان از پیشخوان، در این صفحه و در متن مقالات فعال می‌شود.
			<?php else : ?>
				هیچ واژه‌ای مطابق فیلتر انتخابی یافت نشد.
			<?php endif; ?>
		</span>
	</div>

	<div id="hj-glossary-grid" class="hj-glossary-grid">
		<?php if ( empty( $hj_result['items'] ) ) : ?>
			<div class="hj-glossary-empty">
				<?php if ( 0 === $hj_gl_stats['published'] ) : ?>
					<strong>ساختار واژه‌نامه و هایلایت سبز آماده است.</strong>
					<p style="margin:8px 0 0;">واژگان تخصصی پس از بارگذاری از بخش «پیشخوان ← واژه‌نامه ← بانک واژگان (۵۰۰۰+)» در این صفحه و درون مقالات نمایش داده می‌شوند.</p>
				<?php else : ?>
					<strong>واژه‌ای با این مشخصات پیدا نشد.</strong>
					<p style="margin:8px 0 0;">املای کوتاه‌تر را امتحان کنید یا فیلتر موضوع و حرف اول را روی «همه» قرار دهید.</p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php foreach ( $hj_result['items'] as $item ) : ?>
				<article class="hj-glossary-item">
					<div class="hj-glossary-item__head">
						<div class="hj-glossary-item__title-wrap">
							<h2 class="hj-glossary-item__term"><?php echo esc_html( $item['term'] ); ?></h2>
							<?php if ( '' !== $item['en_term'] ) : ?>
								<span class="hj-glossary-item__en"><?php echo esc_html( $item['en_term'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $item['group_name'] ) : ?>
							<a class="hj-glossary-item__cat" data-gcat-jump="<?php echo esc_attr( $item['group_slug'] ); ?>" href="<?php echo esc_url( $item['group_url'] ); ?>">
								<?php echo esc_html( $item['group_name'] ); ?>
							</a>
						<?php endif; ?>
					</div>
					<p class="hj-glossary-item__def"><?php echo esc_html( $item['definition'] ); ?></p>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div id="hj-glossary-pagination" class="hj-glossary-pagination">
		<?php if ( $hj_result['pages'] > 1 ) : ?>
			<?php if ( $hj_result['page'] > 1 ) : ?>
				<a
					class="hj-glossary-page-btn"
					data-gpage="<?php echo (int) ( $hj_result['page'] - 1 ); ?>"
					href="<?php echo esc_url( add_query_arg( array( 'gq' => $hj_init_q, 'gcat' => $hj_init_cat, 'gletter' => $hj_init_letter, 'gpage' => $hj_result['page'] - 1 ), $hj_gl_hub_url ) ); ?>"
				>قبلی</a>
			<?php endif; ?>

			<?php
			for ( $p = 1; $p <= $hj_result['pages']; ++$p ) :
				if ( $p > 2 && $p < $hj_result['pages'] - 1 && abs( $p - $hj_result['page'] ) > 2 ) {
					if ( abs( $p - $hj_result['page'] ) === 3 ) {
						echo '<span style="padding:0 4px;color:#64748b;">…</span>';
					}
					continue;
				}
				?>
				<a
					class="hj-glossary-page-btn <?php echo $p === $hj_result['page'] ? 'is-active' : ''; ?>"
					data-gpage="<?php echo (int) $p; ?>"
					href="<?php echo esc_url( add_query_arg( array( 'gq' => $hj_init_q, 'gcat' => $hj_init_cat, 'gletter' => $hj_init_letter, 'gpage' => $p ), $hj_gl_hub_url ) ); ?>"
				><?php echo esc_html( $hj_fa( $p ) ); ?></a>
			<?php endfor; ?>

			<?php if ( $hj_result['page'] < $hj_result['pages'] ) : ?>
				<a
					class="hj-glossary-page-btn"
					data-gpage="<?php echo (int) ( $hj_result['page'] + 1 ); ?>"
					href="<?php echo esc_url( add_query_arg( array( 'gq' => $hj_init_q, 'gcat' => $hj_init_cat, 'gletter' => $hj_init_letter, 'gpage' => $hj_result['page'] + 1 ), $hj_gl_hub_url ) ); ?>"
				>بعدی</a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>
