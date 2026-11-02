<?php

/**
 * ماژول ۲: lượt تجاری، ستاره‌دار/VIP، محدودیت سلأت جراحان.
 *
 * - ۳ سطح تجاری: basic، starred، vip
 * - متاباکس مدیریت در صفحه ویرایش پزشک
 * - depletion-toggle سریع در جدول edit.php
 * - توابع کمکی hj_get_surgeon_commercial_data() و hj_get_city_slot_limit()
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/************************************************************************
 * کلاس موتورoure تجاری
 ************************************************************************/

if ( ! class_exists( 'HJ_Monetization_VIP_Engine' ) ) {

	final class HJ_Monetization_VIP_Engine {

		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'register_monetization_metabox' ) );
			add_action( 'save_post_doctor', array( $this, 'save_monetization_meta' ), 10, 2 );
			add_filter( 'manage_doctor_posts_columns', array( $this, 'add_admin_columns' ), 20 );
			add_action( 'manage_doctor_posts_custom_column', array( $this, 'render_admin_columns' ), 20, 2 );
			add_action( 'admin_post_hj_quick_set_tier', array( $this, 'handle_quick_tier_change' ) );
		}

		public function register_monetization_metabox() {
			add_meta_box(
				'hj_monetization_vip_box',
				'💎 پنل درآمدزایی، ستاره‌دار کردن و جایگاه ویژه جراح',
				array( $this, 'render_metabox' ),
				'doctor',
				'side',
				'high'
			);
		}

		public function render_metabox( $post ) {
			wp_nonce_field( 'hj_save_monetization_nonce', 'hj_monetization_nonce' );

			$data = hj_get_surgeon_commercial_data( $post->ID );
			$tier        = $data['tier'];
			$badge       = $data['badge'];
			$booking_url = $data['booking_url'];
			$expires_at  = get_post_meta( $post->ID, 'hj_vip_expires_at', true );
			$priority    = $data['priority'];
			?>
			<div dir="rtl" style="line-height:1.7;">
				<p>
					<label for="hj_vip_tier"><strong>سطح تجاری و نمایش جراح:</strong></label><br>
					<select name="hj_vip_tier" id="hj_vip_tier" style="width:100%;margin-top:4px;">
						<option value="basic" <?php selected( 'basic', $tier ); ?>>عادی (رایگان)</option>
						<option value="starred" <?php selected( 'starred', $tier ); ?>>⭐ جراح ستاره‌دار (نشان طلایی + اولویت شهرستان)</option>
						<option value="vip" <?php selected( 'vip', $tier ); ?>>💎 جایگاه VIP (۳ نفر اول شفاف تخصص + صدر شهرستان)</option>
					</select>
				</p>

				<p>
					<label for="hj_custom_badge"><strong>برچسب تبلیغاتی/تخصصی ویژه:</strong></label><br>
					<input type="text" name="hj_custom_badge" id="hj_custom_badge" value="<?php echo esc_attr( $badge ); ?>" placeholder="مثلاً: جراح ممتاز زانو / دارای بورد تخصصی" style="width:100%;" />
				</p>

				<p>
					<label for="hj_booking_url"><strong>لینک نوبت‌دهی / واتساپ مستقیم (مخصوص ستاره‌دار/VIP):</strong></label><br>
					<input type="url" dir="ltr" name="hj_booking_url" id="hj_booking_url" value="<?php echo esc_attr( $booking_url ); ?>" placeholder="https://wa.me/98912..." style="width:100%;" />
				</p>

				<p>
					<label for="hj_vip_expires_at"><strong>تاریخ انقضای اشتراک ویژه (اختیاری):</strong></label><br>
					<input type="date" dir="ltr" name="hj_vip_expires_at" id="hj_vip_expires_at" value="<?php echo esc_attr( $expires_at ); ?>" style="width:100%;" />
				</p>

				<p style="font-size:11px;color:#64748b;background:#f8fafc;padding:8px;border-radius:6px;">
					امتیاز اولویت فعلی در موتور جستجو: <strong><?php echo esc_html( $priority ); ?></strong><br>
					(در شهرستان‌های عادی فقط ۲ جراح با بالاترین امتیاز اولویت در هر تخصص نمایش داده می‌شوند).
				</p>
			</div>
			<?php
		}

		public function save_monetization_meta( $post_id, $post ) {
			if ( ! isset( $_POST['hj_monetization_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_monetization_nonce'] ) ), 'hj_save_monetization_nonce' ) ) {
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( wp_is_post_revision( $post_id ) ) {
				return;
			}

			$tier = isset( $_POST['hj_vip_tier'] ) ? sanitize_key( wp_unslash( $_POST['hj_vip_tier'] ) ) : 'basic';
			if ( ! in_array( $tier, array( 'basic', 'starred', 'vip' ), true ) ) {
				$tier = 'basic';
			}
			update_post_meta( $post_id, 'hj_vip_tier', $tier );

			$score_map = array(
				'vip'     => 100,
				'starred' => 50,
				'basic'   => 10,
			);
			update_post_meta( $post_id, 'hj_priority_score', $score_map[ $tier ] );

			if ( isset( $_POST['hj_custom_badge'] ) ) {
				update_post_meta( $post_id, 'hj_custom_badge', sanitize_text_field( wp_unslash( $_POST['hj_custom_badge'] ) ) );
			}

			if ( isset( $_POST['hj_booking_url'] ) ) {
				update_post_meta( $post_id, 'hj_booking_url', esc_url_raw( wp_unslash( $_POST['hj_booking_url'] ) ) );
			}

			if ( isset( $_POST['hj_vip_expires_at'] ) ) {
				update_post_meta( $post_id, 'hj_vip_expires_at', sanitize_text_field( wp_unslash( $_POST['hj_vip_expires_at'] ) ) );
			}
		}

		public function add_admin_columns( $columns ) {
			$columns['hj_commercial_tier'] = '⭐ جایگاه تجاری / ستاره';
			return $columns;
		}

		public function render_admin_columns( $column, $post_id ) {
			if ( 'hj_commercial_tier' !== $column ) {
				return;
			}

			$data = hj_get_surgeon_commercial_data( $post_id );
			$tier = $data['tier'];

			if ( 'vip' === $tier ) {
				echo '<span style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:3px 10px;border-radius:99px;font-weight:bold;font-size:12px;display:inline-block;">💎 VIP (ویترین اول)</span>';
			} elseif ( 'starred' === $tier ) {
				echo '<span style="background:#fef3c7;color:#b45309;border:1px solid #f59e0b;padding:3px 10px;border-radius:99px;font-weight:bold;font-size:12px;display:inline-block;">⭐ ستاره‌دار</span>';
			} else {
				echo '<span style="color:#64748b;font-size:12px;">عادی</span>';
			}

			if ( current_user_can( 'manage_options' ) ) {
				echo '<div style="margin-top:5px;font-size:11px;">تغییر سریع: ';
				foreach ( array( 'basic' => 'عادی', 'starred' => '⭐ستاره', 'vip' => '💎VIP' ) as $k => $label ) {
					if ( $k === $tier ) {
						continue;
					}
					$url = wp_nonce_url(
						admin_url( 'admin-post.php?action=hj_quick_set_tier&amp;post_id=' . $post_id . '&amp;tier=' . $k ),
						'hj_set_tier_' . $post_id
					);
					echo '<a href="' . esc_url( $url ) . '" style="margin-left:6px;text-decoration:none;">[' . esc_html( $label ) . ']</a>';
				}
				echo '</div>';
			}
		}

		public function handle_quick_tier_change() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'دسترسی غیرمجاز' );
			}

			$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
			$tier    = isset( $_GET['tier'] ) ? sanitize_key( wp_unslash( $_GET['tier'] ) ) : 'basic';
			check_admin_referer( 'hj_set_tier_' . $post_id );

			if ( $post_id && in_array( $tier, array( 'basic', 'starred', 'vip' ), true ) ) {
				$score_map = array( 'vip' => 100, 'starred' => 50, 'basic' => 10 );
				update_post_meta( $post_id, 'hj_vip_tier', $tier );
				update_post_meta( $post_id, 'hj_priority_score', $score_map[ $tier ] );
			}

			wp_safe_redirect( admin_url( 'edit.php?post_type=doctor' ) );
			exit;
		}
	}
}

/************************************************************************
 * helperهای عمومی (دسترس‌پذیر برای سایر ماژول‌ها)
 ************************************************************************/

if ( ! function_exists( 'hj_get_surgeon_commercial_data' ) ) {
	/**
	 * برگرداندن داده‌های تجاری یک جراح با لحاظ تاریخ انقضا.
	 *
	 * @param int $post_id شناسهٔ نوشتهٔเภت.
	 * @return array{tier,is_starred,is_vip,badge,booking_url,priority}
	 */
	function hj_get_surgeon_commercial_data( $post_id ) {
		$post_id = (int) $post_id;

		$tier       = get_post_meta( $post_id, 'hj_vip_tier', true ) ?: 'basic';
		$expires_at = get_post_meta( $post_id, 'hj_vip_expires_at', true );

		if ( ! empty( $expires_at ) && strtotime( $expires_at ) < current_time( 'timestamp' ) ) {
			$tier = 'basic';
		}

		return array(
			'tier'        => $tier,
			'is_starred'  => in_array( $tier, array( 'starred', 'vip' ), true ),
			'is_vip'      => ( 'vip' === $tier ),
			'badge'       => get_post_meta( $post_id, 'hj_custom_badge', true ),
			'booking_url' => get_post_meta( $post_id, 'hj_booking_url', true ),
			'priority'    => (int) get_post_meta( $post_id, 'hj_priority_score', true ),
		);
	}
}

if ( ! function_exists( 'hj_get_city_slot_limit' ) ) {
	/**
	 * سقف تعداد جراحان قابل نمایش در هر تخصص برای یک شهرستان.
	 *
	 * month-cities که hj_is_major_city = ۱ دارند، سقف ۱۲؛ بقیه سقف ۲.
	 *
	 * @param int $city_term_id شناسهٔ ترم شهر.
	 * @return int
	 */
	function hj_get_city_slot_limit( $city_term_id ) {
		$is_major = (int) get_term_meta( (int) $city_term_id, 'hj_is_major_city', true );
		return ( 1 === $is_major ) ? 12 : 2;
	}
}

new HJ_Monetization_VIP_Engine();