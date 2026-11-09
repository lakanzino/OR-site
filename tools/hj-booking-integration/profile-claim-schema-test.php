<?php
/** Disposable CI-only checks for schema fail-closed behaviour. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php'; hj_doctors();
global $wpdb;
$original = $wpdb->options;
$cases = array(
 'hjt_ci_claim_valid' => 'ENGINE=InnoDB, UNIQUE KEY option_name (option_name)',
 'hjt_ci_claim_nonunique' => 'ENGINE=InnoDB, KEY option_name (option_name)',
 'hjt_ci_claim_composite' => 'ENGINE=InnoDB, UNIQUE KEY composite_key (option_name, autoload)',
 'hjt_ci_claim_prefix' => 'ENGINE=InnoDB, UNIQUE KEY prefix_key (option_name(16))',
 'hjt_ci_claim_myisam' => 'ENGINE=MyISAM, UNIQUE KEY option_name (option_name)',
);
try {
 foreach ( $cases as $table => $definition ) {
  list( $engine, $key ) = explode( ', ', $definition, 2 );
  if ( false === $wpdb->query( "CREATE TABLE `$table` (option_name varchar(191) NOT NULL, option_value longtext NOT NULL, autoload varchar(20) NOT NULL, $key) $engine" ) ) { throw new RuntimeException( 'Fixture schema creation failed' ); }
  $wpdb->options = $table;
  $first = hj_doctors_claim_first_profile( 987654 );
  $second = hj_doctors_claim_first_profile( 987654 );
  $rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
  if ( $table === 'hjt_ci_claim_valid' ) {
   if ( ! $first || $second || $rows !== 1 ) { throw new RuntimeException( 'Valid unique claim failed' ); }
  } elseif ( $first || $second || $rows !== 0 ) { throw new RuntimeException( 'Invalid claim schema did not fail closed: ' . $table ); }
  echo "PASS: $table claim schema check\n";
 }
 $wpdb->options = 'hjt_ci_claim_missing';
 if ( hj_doctors_claim_first_profile( 987654 ) ) { throw new RuntimeException( 'Missing schema did not fail closed' ); }
 echo "PASS: missing claim schema fails closed\n";
} finally {
 $wpdb->options = $original;
 foreach ( array_keys( $cases ) as $table ) { $wpdb->query( "DROP TABLE IF EXISTS `$table`" ); }
}
