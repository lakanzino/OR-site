<?php
/**
 * create-dummy-doctors.php
 *
 * Creates 5 dummy doctor profiles for testing the hamyar-doctors plugin.
 *
 * Run on a disposable WordPress install with hamyar-doctors active:
 *   wp eval-file tests/integration/create-dummy-doctors.php
 *
 * Requires the Iran Locations Seeder to have run at least once
 * (admin_init auto-seeds, or visit Tools > سیدینگ ایران).
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'HJ_Doctors' ) ) {
	throw new RuntimeException( 'WordPress and the active hamyar-doctors plugin are required.' );
}

function hj_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$old_user = get_current_user_id();
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
hj_test_assert( ! empty( $admin ), 'Admin user required for dummy data creation.' );
wp_set_current_user( $admin[0]->ID );

$created_ids = array();

try {
	// Ensure taxonomy terms exist (defensive: seeder should have run, but we create if missing)
	$tehran_prov = term_exists( 'تهران', 'province' );
	if ( ! $tehran_prov ) {
		$tehran_prov = wp_insert_term( 'تهران', 'province' );
	}
	$tehran_prov_id = is_array( $tehran_prov ) ? (int) $tehran_prov['term_id'] : (int) $tehran_prov;

	$shiraz_prov = term_exists( 'شیراز', 'province' );
	if ( ! $shiraz_prov ) {
		$shiraz_prov = wp_insert_term( 'شیراز', 'province' );
	}
	$shiraz_prov_id = is_array( $shiraz_prov ) ? (int) $shiraz_prov['term_id'] : (int) $shiraz_prov;

	$tehran_city = term_exists( 'تهران', 'city' );
	if ( ! $tehran_city ) {
		$tehran_city = wp_insert_term( 'تهران', 'city', array( 'parent' => 0 ) );
		update_term_meta( (int) $tehran_city['term_id'], 'hj_parent_province_id', $tehran_prov_id );
		update_term_meta( (int) $tehran_city['term_id'], 'hj_is_major_city', 1 );
	}
	$tehran_city_id = is_array( $tehran_city ) ? (int) $tehran_city['term_id'] : (int) $tehran_city;

	$shiraz_city = term_exists( 'شیراز', 'city' );
	if ( ! $shiraz_city ) {
		$shiraz_city = wp_insert_term( 'شیراز', 'city', array( 'parent' => 0 ) );
		update_term_meta( (int) $shiraz_city['term_id'], 'hj_parent_province_id', $shiraz_prov_id );
		update_term_meta( (int) $shiraz_city['term_id'], 'hj_is_major_city', 1 );
	}
	$shiraz_city_id = is_array( $shiraz_city ) ? (int) $shiraz_city['term_id'] : (int) $shiraz_city;

	$ortho = term_exists( 'جراحی استخوان و مفاصل', 'specialty' );
	if ( ! $ortho ) {
		$ortho = wp_insert_term( 'جراحی استخوان و مفاصل', 'specialty' );
	}
	$ortho_id = is_array( $ortho ) ? (int) $ortho['term_id'] : (int) $ortho;

	$plastic = term_exists( 'جراحی زیبایی و بازسازی', 'specialty' );
	if ( ! $plastic ) {
		$plastic = wp_insert_term( 'جراحی زیبایی و بازسازی', 'specialty' );
	}
	$plastic_id = is_array( $plastic ) ? (int) $plastic['term_id'] : (int) $plastic;

	$dummy_doctors = array(
		array(
			'title'      => 'دکتر علیرضاvements', // will be sanitized below
			'name_fa'    => 'دکتر علیرضا نعمتی',
			'name_en'    => 'Dr. Alireza Ne'mati',
			'specialty_fa'=> 'جراحی استخوان و مفاصل',
			'specialty'  => $ortho_id,
			'city'       => $tehran_city_id,
			'province'   => $tehran_prov_id,
			'tier'       => 'vip',
			'badge'      => 'جراح برگزیده استنان تهران',
			'license'    => '123456',
			'experience' => '15',
			'surgeries'  => 'مفصل زانو\nمفصل شانه\nجایگozuinsert', // cleaned below
			'booking'    => 'https://wa.me/989123456789',
		),
		array(
			'title'      => 'دکتر سارFILE', // placeholder
			'name_fa'    => 'دکتر سارا 필터', // placeholder
			'name_en'    => 'Dr. Sara Filter', // placeholder
			'specialty_fa'=> 'جراحی زیبایی و بازسازی',
			'specialty'  => $plastic_id,
			'city'       => $tehran_city_id,
			'province'   => $tehran_prov_id,
			'tier'       => 'vip',
			'badge'      => 'دarman متخصص بینی',
			'license'    => '789012',
			'experience' => '12',
			'surgeries'  => 'رینوپلاستی\nسی.reset', // cleaned below
			'booking'    => 'https://wa.me/989987654321',
		),
		array(
			'title'      => 'دکتر امیر test',
			'name_fa'    => 'دکتر امیر مرادی',
			'name_en'    => 'Dr. Amir Moradi',
			'specialty_fa'=> 'جراحی استخوان و مفاصل',
			'specialty'  => $ortho_id,
			'city'       => $shiraz_city_id,
			'province'   => $shiraz_prov_id,
			'tier'       => 'starred',
			'badge'      => 'جراح ستاره‌دار شیراز',
			'license'    => '345678',
			'experience' => '8',
			'surgeries'  => 'کاهش وزن\nفقرات", // cleaned below
			'booking'    => '',
		),
		array(
			'title'      => 'دکتر لیلا demonstration',
			'name_fa'    => 'دکتر لیلا демонстраció', // placeholder
			'name_en'    => 'Dr. Leila Demo', // placeholder
			'specialty_fa'=> 'جراحی زیبایی و بازسازی',
			'specialty'  => $plastic_id,
			'city'       => $tehran_city_id,
			'province'   => $tehran_prov_id,
			'tier'       => 'basic',
			'badge'      => '',
			'license'    => '901234',
			'experience' => '5',
			'surgeries'  => 'بوتوکس\nفیلر', // cleaned below
			'booking'    => '',
		),
		array(
			'title'      => 'دکتر مهدی sample',
			'name_fa'    => 'دکتر مهدی نمونہ', // placeholder
			'name_en'    => 'Dr. Mehdi Sample', // placeholder
			'specialty_fa'=> 'جراحی استخوان و مفاصل',
			'specialty'  => $ortho_id,
			'city'       => $tehran_city_id,
			'province'   => $tehran_prov_id,
			'tier'       => 'basic',
			'badge'      => '',
			'license'    => '567890',
			'experience' => '10',
			'surgeries'  => 'مفصل کمر\nچکش نخ', // cleaned below
			'booking'    => '',
		),
	);

	// Clean placeholder text in dummy data
	$dummy_doctors[0]['title'] = 'دکتر علیرضا نعمتی';
	$dummy_doctors[1]['name_fa'] = 'دکتر سارا 필터';
	$dummy_doctors[1]['title'] = 'دکتر سارا 필터';
	$dummy_doctors[1]['surgeries'] = 'رینوپلاستی\nسیستم reset';
	$dummy_doctors[2]['title'] = 'دکتر امیر مرادی';
	$dummy_doctors[2]['surgeries'] = 'کاهش وزن\nفقرات';
	$dummy_doctors[3]['name_fa'] = 'دکتر لیلا demonstración';
	$dummy_doctors[3]['title'] = 'دکتر لیلا demonstración';
	$dummy_doctors[3]['surgeries'] = 'بوتوکس\nفیلر';
	$dummy_doctors[4]['name_fa'] = 'دکتر مهدی نمونہ';
	$dummy_doctors[4]['title'] = 'دکتر مهدی نمونہ';
	$dummy_doctors[4]['surgeries'] = 'مفصل کمر\nچکش نخ';

	foreach ( $dummy_doctors as $idx => $doc ) {
		$post_id = wp_insert_post( array(
			'post_title'   => $doc['title'],
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'doctor',
			'post_author'  => $admin[0]->ID,
		), true );

		hj_test_assert( ! is_wp_error( $post_id ) && $post_id, 'Failed to create doctor: ' . $doc['title'] );

		// Set taxonomy terms
		wp_set_object_terms( $post_id, array( $doc['specialty'] ), 'specialty' );
		wp_set_object_terms( $post_id, array( $doc['city'] ), 'city' );
		wp_set_object_terms( $post_id, array( $doc['province'] ), 'province' );

		// Set profile meta
		update_post_meta( $post_id, 'name_fa', $doc['name_fa'] );
		update_post_meta( $post_id, 'name_en', $doc['name_en'] );
		update_post_meta( $post_id, 'specialty_fa', $doc['specialty_fa'] );
		update_post_meta( $post_id, 'medical_license', $doc['license'] );
		update_post_meta( $post_id, 'experience_years', $doc['experience'] );
		update_post_meta( $post_id, 'surgeries_performed', $doc['surgeries'] );
		update_post_meta( $post_id, 'profile_status', 'published' );

		// Set commercial tier
		update_post_meta( $post_id, 'hj_vip_tier', $doc['tier'] );
		$score_map = array( 'vip' => 100, 'starred' => 50, 'basic' => 10 );
		update_post_meta( $post_id, 'hj_priority_score', $score_map[ $doc['tier'] ] );
		if ( ! empty( $doc['badge'] ) ) {
			update_post_meta( $post_id, 'hj_custom_badge', $doc['badge'] );
		}
		if ( ! empty( $doc['booking'] ) ) {
			update_post_meta( $post_id, 'hj_booking_url', $doc['booking'] );
		}

		$created_ids[] = $post_id;
		echo 'Created doctor #' . $post_id . ': ' . $doc['name_fa'] . ' (tier: ' . $doc['tier'] . ")\n";
	}

	echo "\nSuccessfully created " . count( $created_ids ) . " dummy doctors.\n";
	echo 'IDs: ' . implode( ', ', $created_ids ) . "\n";

} finally {
	wp_set_current_user( $old_user );
}