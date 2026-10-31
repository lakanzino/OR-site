<?php
/**
 * Minimal WordPress stubs, only enough to execute the plugin's own logic in a
 * plain PHP process. This is NOT WordPress and proves nothing about how
 * WordPress itself behaves; it exists so the mapping, activation and enqueue
 * rules can be unit-tested outside a full install.
 *
 * @package HamyarSymptomChecker
 */

// phpcs:disable

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['hj_stub'] = array(
	'options'  => array(),
	'posts'    => array(),
	'next_id'  => 100,
	'filters'  => array(),
	'actions'  => array(),
	'styles'   => array(),
	'scripts'  => array(),
	'inline'   => array(),
	'hooks'    => array(),
	'is_admin' => false,
	'is_page'  => false,
	'queried'  => 0,
);

function hj_stub_reset() {
	$GLOBALS['hj_stub'] = array(
		'options'  => array(),
		'posts'    => array(),
		'next_id'  => 100,
		'filters'  => array(),
		'actions'  => array(),
		'styles'   => array(),
		'scripts'  => array(),
		'inline'   => array(),
		'hooks'    => array(),
		'is_admin' => false,
		'is_page'  => false,
		'queried'  => 0,
		'uploads'  => null,
	);
}

class WP_Post {
	public $ID;
	public $post_type     = 'post';
	public $post_status   = 'publish';
	public $post_name     = '';
	public $post_title    = '';
	public $post_content  = '';
	public $comment_status = 'closed';
	public $ping_status    = 'closed';
	public $meta           = array();

	public function __construct( $data = array() ) {
		foreach ( $data as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return (string) $this->message;
	}
	public function get_error_code() {
		return (string) $this->code;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/' ) . '/';
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['hj_stub']['hooks']['activate'] = $callback;
}

function __return_false() {
	return false;
}

function __return_true() {
	return true;
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hj_stub']['filters'][ $tag ][] = array( $callback, $priority, $accepted_args );
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hj_stub']['actions'][ $tag ][] = array( $callback, $priority, $accepted_args );
}

function apply_filters( $tag, $value, ...$args ) {
	$callbacks = $GLOBALS['hj_stub']['filters'][ $tag ] ?? array();
	usort(
		$callbacks,
		static function ( $a, $b ) {
			return $a[1] <=> $b[1];
		}
	);
	foreach ( $callbacks as $entry ) {
		$value = call_user_func_array( $entry[0], array_merge( array( $value ), $args ) );
	}
	return $value;
}

function do_action( $tag, ...$args ) {
	$GLOBALS['hj_stub']['actions'][ $tag ][] = $args;
}

function delete_option( $name ) {
	unset( $GLOBALS['hj_stub']['options'][ $name ] );
	return true;
}

function current_user_can( $capability ) {
	return in_array( $capability, $GLOBALS['hj_stub']['caps'] ?? array( 'manage_options' ), true );
}

function get_bloginfo( $what = '' ) {
	return 'version' === $what ? ( $GLOBALS['hj_stub']['wp_version'] ?? '6.8.3' ) : '';
}

/**
 * Uploads location. Empty by default, which is WordPress' way of saying "this
 * install cannot upload"; the plugin must then use its packaged bundle.
 */
function hj_stub_set_uploads( $basedir, $baseurl ) {
	$GLOBALS['hj_stub']['uploads'] = array(
		'basedir' => $basedir,
		'baseurl' => $baseurl,
		'error'   => '',
	);
}

function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
	if ( isset( $GLOBALS['hj_stub']['uploads'] ) ) {
		return $GLOBALS['hj_stub']['uploads'];
	}
	return array(
		'basedir' => '',
		'baseurl' => '',
		'error'   => 'stub: no uploads configured',
	);
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['hj_stub']['options'] ) ? $GLOBALS['hj_stub']['options'][ $name ] : $default;
}

function update_option( $name, $value ) {
	$GLOBALS['hj_stub']['options'][ $name ] = $value;
	return true;
}

function get_post( $id ) {
	return $GLOBALS['hj_stub']['posts'][ (int) $id ] ?? null;
}

function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
	foreach ( $GLOBALS['hj_stub']['posts'] as $post ) {
		if ( $post->post_type === $post_type && $post->post_name === $slug ) {
			return $post;
		}
	}
	return null;
}

function wp_insert_post( $data, $wp_error = false ) {
	if ( ! empty( $GLOBALS['hj_stub']['fail_insert'] ) ) {
		return new WP_Error( 'db_insert_error', 'simulated database failure' );
	}
	$id = $GLOBALS['hj_stub']['next_id']++;

	$post = new WP_Post(
		array(
			'ID'          => $id,
			'post_type'   => $data['post_type'] ?? 'post',
			'post_status' => $data['post_status'] ?? 'draft',
			'post_name'   => $data['post_name'] ?? '',
			'post_title'  => $data['post_title'] ?? '',
			'post_content' => $data['post_content'] ?? '',
			'meta'        => $data['meta_input'] ?? array(),
		)
	);
	$GLOBALS['hj_stub']['posts'][ $id ] = $post;

	return $id;
}

function post_type_exists( $type ) {
	return (bool) ( $GLOBALS['hj_stub']['post_types'][ $type ] ?? false );
}

function register_post_type( $type ) {
	$GLOBALS['hj_stub']['post_types'][ $type ] = true;
}

function get_posts( $args = array() ) {
	$matches = array();
	foreach ( $GLOBALS['hj_stub']['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['post_status'] ) ) {
			$wanted = (array) $args['post_status'];
			if ( ! in_array( 'any', $wanted, true ) && ! in_array( $post->post_status, $wanted, true ) ) {
				continue;
			}
		}
		if ( isset( $args['name'] ) && $post->post_name !== $args['name'] ) {
			continue;
		}
		$matches[] = $post;
	}
	$limit = (int) ( $args['posts_per_page'] ?? -1 );
	if ( $limit > 0 ) {
		$matches = array_slice( $matches, 0, $limit );
	}
	if ( 'ids' === ( $args['fields'] ?? '' ) ) {
		return array_map(
			static function ( $post ) {
				return $post->ID;
			},
			$matches
		);
	}
	return $matches;
}

function get_permalink( $id ) {
	$post = get_post( $id );
	if ( ! $post ) {
		return false;
	}
	return 'https://example.test/disease/' . $post->post_name . '/';
}

function sanitize_title( $title ) {
	$title = strtolower( trim( (string) $title ) );
	return trim( preg_replace( '/[^a-z0-9\-]+/', '-', $title ), '-' );
}

function is_admin() {
	return (bool) $GLOBALS['hj_stub']['is_admin'];
}

function wp_doing_ajax() {
	return false;
}

function is_page( $page = '' ) {
	if ( empty( $GLOBALS['hj_stub']['is_page'] ) ) {
		return false;
	}
	if ( '' === $page || is_array( $page ) ) {
		return true;
	}
	return (int) $page === (int) $GLOBALS['hj_stub']['queried'];
}

function get_queried_object_id() {
	return (int) $GLOBALS['hj_stub']['queried'];
}

function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
	return json_encode( $data, $flags, $depth );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return esc_html( $text );
}

function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
	$GLOBALS['hj_stub']['styles'][ $handle ] = array( 'src' => $src, 'version' => $version );
}

function wp_enqueue_script( $handle, $src = '', $deps = array(), $version = false, $footer = false ) {
	$GLOBALS['hj_stub']['scripts'][ $handle ] = array( 'src' => $src, 'version' => $version, 'footer' => $footer );
}

function wp_add_inline_script( $handle, $data, $position = 'after' ) {
	$GLOBALS['hj_stub']['inline'][ $handle ][] = array( 'data' => $data, 'position' => $position );
}

function language_attributes() {
	echo 'lang="fa-IR"';
}

function bloginfo( $what ) {
	echo 'UTF-8' === $what ? 'UTF-8' : 'https://example.test';
}

function body_class( $class = '' ) {
	echo 'class="' . esc_attr( $class ) . '"';
}
