<?php
/**
 * Template Name: داشبورد پروفایل پزشک
 *
 * Front-end dashboard where a specialist/surgeon writes and maintains their
 * own profile (owner decision 2026-11-01):
 *
 *  - the doctor creates/edits their own profile (create → `pending`, the site
 *    admin approves and publishes);
 *  - the site admin can edit every profile here (full rights; account
 *    deletion stays in wp-admin → Users);
 *  - each profile shows its ready-made advertising embed codes (strip +
 *    full widgets) to paste into other websites;
 *  - stars/dates of individual votes are managed by the admin in wp-admin →
 *    Site Reviews → Reviews (linked below for admins).
 *
 * Page setup: create a page (e.g. /my-profile/), assign this template.
 *
 * @package Lax_Theme_Child
 * @since   0.27.0
 */

defined( 'ABSPATH' ) || exit;
get_header();

$hj_is_admin = is_user_logged_in() && current_user_can( 'manage_options' );
$hj_status   = isset( $_GET['hj-status'] ) ? sanitize_key( wp_unslash( $_GET['hj-status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$hj_messages = array(
	'saved'        => 'پروفایل با موفقیت ذخیره شد.',
	'created'      => 'پروفایل ساخته و منتشر شد.',
	'pending'      => 'پروفایل ساخته شد و پس از تأیید مدیر سایت منتشر می‌شود.',
	'denied'       => 'اجازهٔ ویرایش این پروفایل را ندارید.',
	'missing-name' => 'نام پزشک نمی‌تواند خالی باشد.',
	'error'        => 'خطا در ذخیره‌سازی؛ دوباره تلاش کنید.',
);
?>
<style>
.hj-dash{--ink:#0f172a;--line:#e2e8f0;--muted:#64748b;--navy:#0a2f6b;color:var(--ink);line-height:1.9;max-width:860px;margin:0 auto;padding:1.75rem 1rem 3rem}
.hj-dash h1{font-size:1.5rem;color:var(--navy);margin:0 0 1rem}
.hj-dash .hj-box{border:1px solid var(--line);border-radius:12px;background:#fff;padding:1rem 1.2rem;margin:0 0 1rem}
.hj-dash .hj-msg{border-inline-start:4px solid #16a34a;background:#f0fdf4;padding:.6rem 1rem;border-radius:8px;margin:0 0 1rem}
.hj-dash .hj-msg.err{border-color:#dc2626;background:#fef2f2}
.hj-dash label{display:block;font-weight:700;font-size:.9rem;margin:.7rem 0 .25rem}
.hj-dash input[type=text],.hj-dash select,.hj-dash textarea{width:100%;border:1px solid var(--line);border-radius:8px;padding:.55rem .7rem;font:inherit;font-size:.92rem;background:#fff}
.hj-dash textarea{min-height:96px;resize:vertical}
.hj-dash .hj-btn{display:inline-block;border:0;border-radius:10px;background:var(--navy);color:#fff;font:inherit;font-weight:700;padding:.6rem 1.4rem;cursor:pointer;margin-top:1rem}
.hj-dash .hj-btn:hover{filter:brightness(1.15)}
.hj-dash .hj-btn.small{padding:.3rem .8rem;font-size:.8rem;margin:0;border-radius:8px;background:#334155}
.hj-dash .hj-profiles{list-style:none;padding:0;margin:0 0 1rem}
.hj-dash .hj-profiles li{display:inline-block;margin:0 .4rem .4rem 0}
.hj-dash .hj-profiles a{display:inline-block;border:1px solid var(--line);border-radius:999px;padding:.25rem .9rem;font-size:.85rem;color:var(--ink);text-decoration:none;background:#f8fafc}
.hj-dash .hj-profiles a.active,.hj-dash .hj-profiles a:hover{border-color:var(--navy);color:var(--navy);background:#eff6ff}
.hj-dash .hj-embed-row{display:flex;gap:.6rem;align-items:flex-start;margin:.5rem 0}
.hj-dash .hj-embed-row textarea{flex:1;min-height:64px;font-family:ui-monospace,Consolas,monospace;font-size:.78rem;direction:ltr;text-align:left;color:#334155;background:#f8fafc}
.hj-dash .hj-note{font-size:.85rem;color:var(--muted)}
.hj-dash .hj-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 1rem}
@media (max-width:600px){.hj-dash .hj-grid{grid-template-columns:1fr}}
</style>
<div class="container orsite-article-wrap hj-dash"><main id="primary">
<h1>داشبورد پروفایل پزشک</h1>

<?php if ( ! is_user_logged_in() ) : ?>
	<div class="hj-box">
		<p>برای ساخت و ویرایش پروفایل باید وارد حساب شوید.</p>
		<p><a class="hj-btn" href="<?php echo esc_url( wp_login_url( (string) get_permalink() ) ); ?>">ورود / ثبت نام</a></p>
		<p class="hj-note">حساب متخصصان توسط مدیر سایت ساخته می‌شود یا از راه «پیوستن به همیار جراحی» درخواست دهید؛ پروفایلی که خودتان می‌سازید پس از تأیید مدیر منتشر می‌شود.</p>
	</div>
<?php else :
	$hj_user_id = get_current_user_id();
	$hj_args    = array(
		'post_type'      => 'doctor',
		'post_status'    => array( 'publish', 'pending', 'draft' ),
		'numberposts'    => $hj_is_admin ? 200 : 50,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( ! $hj_is_admin ) {
		$hj_args['author'] = $hj_user_id;
	}
	$hj_profiles = get_posts( $hj_args );
	$hj_selected = isset( $_GET['doctor'] ) ? absint( $_GET['doctor'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $hj_selected || ! wp_filter_object_list( $hj_profiles, array( 'ID' => $hj_selected ) ) ) {
		$hj_selected = $hj_profiles ? (int) $hj_profiles[0]->ID : 0;
	}
	?>

	<?php if ( $hj_status && isset( $hj_messages[ $hj_status ] ) ) : ?>
		<div class="hj-msg<?php echo in_array( $hj_status, array( 'denied', 'missing-name', 'error' ), true ) ? ' err' : ''; ?>">
			<?php echo esc_html( $hj_messages[ $hj_status ] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $hj_profiles ) : ?>
		<div class="hj-box">
			<strong>پروفایل‌های <?php echo $hj_is_admin ? 'سایت (دسترسی مدیر)' : 'شما'; ?>:</strong>
			<ul class="hj-profiles">
				<?php foreach ( $hj_profiles as $hj_p ) : ?>
					<li><a href="<?php echo esc_url( add_query_arg( 'doctor', (int) $hj_p->ID, (string) get_permalink() ) ); ?>"<?php echo (int) $hj_p->ID === $hj_selected ? ' class="active"' : ''; ?>>
						<?php echo esc_html( $hj_p->post_title ?: '(بدون نام)' ); ?>
						<?php if ( 'publish' !== $hj_p->post_status ) : ?>
							— <?php echo esc_html( 'pending' === $hj_p->post_status ? 'در انتظار تأیید' : 'پیش‌نویس' ); ?>
						<?php endif; ?>
					</a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( ! function_exists( 'hj_doctor_meta_keys' ) ) : ?>
		<div class="hj-box"><p class="hj-note">فایل «inc/doctor-profile.php» هنوز روی سایت آپلود نشده است؛ این داشبورد بدون آن کار نمی‌کند (به مدیر سایت اطلاع دهید).</p></div>
	<?php endif; ?>
	<?php
	$hj_keys   = function_exists( 'hj_doctor_meta_keys' ) ? hj_doctor_meta_keys() : array( 'degree' => '_hj_doctor_degree', 'code' => '_hj_doctor_code', 'location' => '_hj_doctor_location', 'bio' => '_hj_doctor_bio' );
	$hj_labels = function_exists( 'hj_doctor_field_labels' ) ? hj_doctor_field_labels() : array( 'degree' => 'مدرک تحصیلی', 'code' => 'کد نظام پزشکی', 'location' => 'محل طبابت', 'bio' => 'خلاصهٔ سوابق' );
	$hj_edit   = $hj_selected ? get_post( $hj_selected ) : null;
	$hj_can    = $hj_edit && function_exists( 'hj_doctor_user_can_edit' ) && hj_doctor_user_can_edit( (int) $hj_edit->ID, $hj_user_id );
	if ( $hj_edit && ! $hj_can ) {
		$hj_edit = null; // never render a form for a profile the user may not edit.
	}
	$hj_terms = get_terms( array( 'taxonomy' => 'specialty', 'hide_empty' => false ) );
	?>

	<div class="hj-box">
		<h2 style="font-size:1.1rem;margin:0 0 .4rem"><?php echo $hj_edit ? 'ویرایش پروفایل' : 'ساخت پروفایل جدید'; ?></h2>
		<p class="hj-note">
			<?php echo $hj_edit
				? 'تغییرات پس از ذخیره فوراً در صفحهٔ پروفایل و ویجت‌ها اعمال می‌شود.'
				: ( $hj_is_admin
					? 'پروفایل جدید بلافاصله منتشر می‌شود (دسترسی مدیر).'
					: 'پروفایل جدیدی که خودتان می‌سازید پس از تأیید مدیر سایت منتشر می‌شود.' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hj_save_doctor_profile">
			<input type="hidden" name="hj_doctor_nonce" value="<?php echo esc_attr( wp_create_nonce( 'hj_doctor_save' ) ); ?>">
			<?php if ( $hj_edit ) : ?>
				<input type="hidden" name="doctor_id" value="<?php echo (int) $hj_edit->ID; ?>">
			<?php endif; ?>

			<label for="hj-name">نام و نام خانوادگی *</label>
			<input type="text" id="hj-name" name="hj_name" required value="<?php echo $hj_edit ? esc_attr( $hj_edit->post_title ) : ''; ?>">

			<div class="hj-grid">
				<div>
					<label for="hj-degree"><?php echo esc_html( $hj_labels['degree'] ?? 'مدرک تحصیلی' ); ?></label>
					<input type="text" id="hj-degree" name="hj_degree" value="<?php echo $hj_edit ? esc_attr( (string) get_post_meta( $hj_edit->ID, $hj_keys['degree'], true ) ) : ''; ?>">
				</div>
				<div>
					<label for="hj-code"><?php echo esc_html( $hj_labels['code'] ?? 'کد نظام پزشکی' ); ?></label>
					<input type="text" id="hj-code" name="hj_code" value="<?php echo $hj_edit ? esc_attr( (string) get_post_meta( $hj_edit->ID, $hj_keys['code'], true ) ) : ''; ?>">
				</div>
				<div>
					<label for="hj-location"><?php echo esc_html( $hj_labels['location'] ?? 'محل طبابت' ); ?></label>
					<input type="text" id="hj-location" name="hj_location" value="<?php echo $hj_edit ? esc_attr( (string) get_post_meta( $hj_edit->ID, $hj_keys['location'], true ) ) : ''; ?>">
				</div>
				<div>
					<label for="hj-specialty">رشتهٔ تخصصی</label>
					<select id="hj-specialty" name="hj_specialty">
						<option value="0">— انتخاب کنید —</option>
						<?php
						if ( is_array( $hj_terms ) ) {
							$hj_current = 0;
							if ( $hj_edit ) {
								$hj_sp_terms = get_the_terms( $hj_edit->ID, 'specialty' );
								if ( is_array( $hj_sp_terms ) && $hj_sp_terms ) {
									$hj_current = (int) $hj_sp_terms[0]->term_id;
								}
							}
							foreach ( $hj_terms as $hj_t ) {
								echo '<option value="' . (int) $hj_t->term_id . '"' . selected( $hj_current, (int) $hj_t->term_id, false ) . '>' . esc_html( $hj_t->name ) . '</option>';
							}
						}
						?>
					</select>
				</div>
			</div>

			<label for="hj-bio"><?php echo esc_html( $hj_labels['bio'] ?? 'خلاصهٔ سوابق' ); ?> (۲ تا ۳ خط)</label>
			<textarea id="hj-bio" name="hj_bio"><?php echo $hj_edit ? esc_textarea( (string) get_post_meta( $hj_edit->ID, $hj_keys['bio'], true ) ) : ''; ?></textarea>

			<button type="submit" class="hj-btn"><?php echo $hj_edit ? 'ذخیرهٔ تغییرات' : 'ساخت پروفایل'; ?></button>
		</form>
	</div>

	<?php if ( $hj_edit && 'publish' === $hj_edit->post_status && function_exists( 'hj_doctor_embed_code' ) ) : ?>
		<div class="hj-box">
			<h2 style="font-size:1.1rem;margin:0 0 .4rem">لینک و کد تبلیغاتی (برای سایت‌های دیگر)</h2>
			<p class="hj-note">این کدها را در هر سایتی (سایت شهری، مطب، وبلاگ) بگذارید تا ویجت پروفایل با ستاره‌های آرای مردمی همان‌جا نمایش داده شود.</p>

			<label>لینک مستقیم پروفایل</label>
			<div class="hj-embed-row">
				<textarea readonly onclick="this.select()"><?php echo esc_textarea( (string) get_permalink( $hj_edit->ID ) ); ?></textarea>
				<button type="button" class="hj-btn small hj-copy">کپی</button>
			</div>

			<label>ویجت نواری (کوچک — نام + کد نظام پزشکی + ستاره‌ها)</label>
			<div class="hj-embed-row">
				<textarea readonly onclick="this.select()"><?php echo esc_textarea( hj_doctor_embed_code( (int) $hj_edit->ID, 'strip' ) ); ?></textarea>
				<button type="button" class="hj-btn small hj-copy">کپی</button>
			</div>

			<label>ویجت بزرگ (کارت کامل با توضیحات)</label>
			<div class="hj-embed-row">
				<textarea readonly onclick="this.select()"><?php echo esc_textarea( hj_doctor_embed_code( (int) $hj_edit->ID, 'full' ) ); ?></textarea>
				<button type="button" class="hj-btn small hj-copy">کپی</button>
			</div>

			<p class="hj-note">پیش‌نمایش زنده: <a href="<?php echo esc_url( add_query_arg( 'embed', 'strip', (string) get_permalink( $hj_edit->ID ) ) ); ?>" target="_blank" rel="noopener">ویجت نواری</a> · <a href="<?php echo esc_url( add_query_arg( 'embed', 'full', (string) get_permalink( $hj_edit->ID ) ) ); ?>" target="_blank" rel="noopener">ویجت بزرگ</a></p>
		</div>
	<?php endif; ?>

	<?php if ( $hj_is_admin ) : ?>
		<div class="hj-box">
			<h2 style="font-size:1.1rem;margin:0 0 .4rem">دسترسی‌های مدیر سایت</h2>
			<ul class="hj-note">
				<li><strong>ستاره و تاریخ رأی‌ها:</strong> پیشخوان وردپرس → <a href="<?php echo esc_url( admin_url( 'admin.php?page=site-reviews' ) ); ?>">Site Reviews → Reviews</a> — ویرایش امتیاز/تاریخ هر رأی، افزودن یا حذف رأی (طبق سند آرای اولیه).</li>
				<li><strong>ویرایش کامل هر پروفایل:</strong> همان فهرست بالا (همهٔ پروفایل‌ها برای شماست) یا <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=doctor' ) ); ?>">پیشخوان → پزشکان</a>.</li>
				<li><strong>حساب متخصصان:</strong> ساخت/حذف اکانت در <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">کاربران</a> — حق کامل با مدیر وبسایت است؛ با حذف اکانت، پروفایل‌ها باقی می‌مانند (نویسندهٔ آن‌ها قابل تغییر است).</li>
				<li><strong>تأیید پروفایل‌های در انتظار:</strong> وضعیت «در انتظار تأیید» را از پیشخوان → پزشکان منتشر کنید.</li>
			</ul>
		</div>
	<?php endif; ?>

	<script>
	document.querySelectorAll('.hj-copy').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var ta = btn.parentElement.querySelector('textarea');
			ta.select();
			if (navigator.clipboard) { navigator.clipboard.writeText(ta.value); }
			try { document.execCommand('copy'); } catch (e) {}
			btn.textContent = 'کپی شد ✓';
			setTimeout(function () { btn.textContent = 'کپی'; }, 1600);
		});
	});
	</script>
<?php endif; ?>
</main></div>
<?php get_footer();
