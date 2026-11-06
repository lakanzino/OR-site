<?php
/** CI-only bootstrap. Never load on a real server. */
if ( getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
define( 'HJ_BOOKING_ENABLED', true );
define( 'HJ_BOOKING_TESTING', true );
