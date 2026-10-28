<?php
/**
 * Standalone homepage presentation tests; no WordPress installation required.
 * Run: php themes/lax-theme-child/tests/homepage-ui.test.php
 */
define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../inc/homepage-ui.php';

function check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
	echo "PASS: $message\n";
}

$labels = array(
	array( 'general-laparoscopy', 'general-laparoscopy', 'لاپاروسکوپی عمومی' ),
	array( 'General Laparoscopy', 'other-slug', 'لاپاروسکوپی عمومی' ),
	array( 'Laparoscopic Surgery', 'laparoscopic-surgery', 'لاپاروسکوپی' ),
	array( 'Laparoscopy', 'general-laparoscopy', 'لاپاروسکوپی عمومی' ),
	array( 'لاپاروسکوپی عمومی', 'general-laparoscopy', 'لاپاروسکوپی عمومی' ),
	array( 'Gynecological Laparoscopy', 'gynecology', 'Gynecological Laparoscopy' ),
	array( 'جراحی کودکان', 'pediatric-surgery', 'جراحی کودکان' ),
);
foreach ( $labels as $case ) {
	check( hj_home_specialty_label( $case[0], $case[1] ) === $case[2], 'specialty label: ' . $case[0] );
}

check( array() === hj_home_glossary_topics(), 'missing glossary engine is safe during partial updates' );
if ( true ) {
	function hj_glossary_get_groups_with_counts( $hide_empty = false ) {
		check( false === $hide_empty, 'selection sees existing empty groups too' );
		return $GLOBALS['qa_groups'];
	}
}
function group_fixture( $slug, $count, $name = '' ) {
	return array(
		'name'  => '' !== $name ? $name : $slug,
		'slug'  => $slug,
		'url'   => 'https://example.test/glossary-group/' . $slug . '/',
		'count' => $count,
	);
}
function slugs( $groups ) {
	return array_map( static function ( $group ) { return $group['slug']; }, $groups );
}

$GLOBALS['qa_groups'] = array(
	group_fixture( 'sterile-terms', 7 ),
	group_fixture( 'anesthesia-terms', 20 ),
	group_fixture( 'device-terms', 8 ),
	group_fixture( 'general-terms', 1 ),
	group_fixture( 'custom-extra', 100 ),
);
$original = $GLOBALS['qa_groups'];
$selected = hj_home_glossary_topics();
check( array( 'general-terms', 'anesthesia-terms', 'sterile-terms', 'device-terms' ) === slugs( $selected ), 'four populated standard groups appear in editorial order' );
check( $GLOBALS['qa_groups'] === $original, 'selection does not alter stored group data' );
check( $selected[0]['url'] === $original[3]['url'], 'original taxonomy link is retained' );

$GLOBALS['qa_groups'] = array(
	group_fixture( 'general-terms', 0 ),
	group_fixture( 'anesthesia-terms', 0 ),
	group_fixture( 'sterile-terms', 0 ),
	group_fixture( 'device-terms', 0 ),
	group_fixture( 'custom-b', 2 ),
	group_fixture( 'custom-a', 5 ),
	array( 'slug' => 'broken', 'name' => 'Bad link', 'url' => '', 'count' => 100 ),
);
check( array( 'custom-a', 'custom-b', 'general-terms', 'anesthesia-terms' ) === slugs( hj_home_glossary_topics() ), 'populated imported groups take priority over empty standard groups' );

$GLOBALS['qa_groups'] = array( group_fixture( 'only-real-group', 1 ) );
check( array( 'only-real-group' ) === slugs( hj_home_glossary_topics() ), 'fewer than four existing topics do not create fake links' );
$GLOBALS['qa_groups'] = array();
check( array() === hj_home_glossary_topics(), 'empty taxonomy remains empty' );
echo "All homepage presentation tests passed.\n";
