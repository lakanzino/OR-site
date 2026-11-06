<?php
/** Explicit CI-only experiment: no production migration or activation. */
require __DIR__ . '/prepend.php';
define( 'HJ_BOOKING_CAPACITY_V2_TEST_ONLY', true );
