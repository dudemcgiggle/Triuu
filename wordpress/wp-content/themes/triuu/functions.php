<?php
/**
 * Hello Elementor – streamlined bootstrap (safe, consolidated)
 * - Guarded constants
 * - Asset versioning (filemtime in dev)
 * - Correct parent/child enqueue order
 * - Optional local CSS chain (reset/theme/header-footer) + self-hosted fonts.css
 * - Single inline <style> block for sitewide tweaks (late, wins cascade)
 * - Elementor core locations
 * - Meta description from page/post excerpt
 * - Services page CSS with existence guard
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 * Constants (guarded; parent = template directory)
 * ------------------------------------------------------------------------- */
if ( ! defined( 'HELLO_ELEMENTOR_VERSION' ) ) define( 'HELLO_ELEMENTOR_VERSION', '3.4.4' );
if ( ! defined( 'HELLO_THEME_PATH' ) )        define( 'HELLO_THEME_PATH',        get_template_directory() );
if ( ! defined( 'HELLO_THEME_URL' ) )         define( 'HELLO_THEME_URL',         get_template_directory_uri() );
if ( ! defined( 'HELLO_THEME_ASSETS_PATH' ) ) define( 'HELLO_THEME_ASSETS_PATH', HELLO_THEME_PATH . '/assets' );
if ( ! defined( 'HELLO_THEME_ASSETS_URL' ) )  define( 'HELLO_THEME_ASSETS_URL',  HELLO_THEME_URL  . '/assets' );

/**
 * Version helper: uses filemtime() in WP_DEBUG to bust cache locally;
 * falls back to HELLO_ELEMENTOR_VERSION in production.
 */
function hello_asset_ver( $rel_path_from_theme_root ) {
	$abs = trailingslashit( get_template_directory() ) . ltrim( $rel_path_from_theme_root, '/\\' );
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $abs ) ) {
		$mtime = @filemtime( $abs );
		if ( $mtime ) return (string) $mtime;
	}
	return HELLO_ELEMENTOR_VERSION;
}

/* -------------------------------------------------------------------------
 * Theme setup
 * ------------------------------------------------------------------------- */
function hello_elementor_setup_clean() {
	// Track theme version (optional)
	$opt = 'hello_theme_version';
	$cur = (string) get_option( $opt );
	if ( empty( $cur ) || version_compare( $cur, HELLO_ELEMENTOR_VERSION, '<' ) ) {
		update_option( $opt, HELLO_ELEMENTOR_VERSION );
	}

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form','comment-form','comment-list','gallery','caption','script','style','navigation-widgets' ) );
	add_theme_support( 'custom-logo', array( 'height'=>100, 'width'=>350, 'flex-height'=>true, 'flex-width'=>true ) );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'editor-styles.css' );

	register_nav_menus( array(
		'menu-1' => esc_html__( 'Header Menu', 'hello-elementor' ),
		'menu-2' => esc_html__( 'Footer Menu', 'hello-elementor' ),
	) );

	// Excerpts on pages (used for meta description)
	add_post_type_support( 'page', 'excerpt' );

	// Optional WooCommerce (can be disabled via filter)
	if ( apply_filters( 'hello_elementor_add_woocommerce_support', true ) ) {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}
}
add_action( 'after_setup_theme', 'hello_elementor_setup_clean' );

/** Content width */
function hello_elementor_content_width_clean() {
	$GLOBALS['content_width'] = (int) apply_filters( 'hello_elementor_content_width', 800 );
}
add_action( 'after_setup_theme', 'hello_elementor_content_width_clean', 0 );

/* -------------------------------------------------------------------------
 * Styles & Fonts
 * ------------------------------------------------------------------------- */
/**
 * Enqueue base styles (parent/child) and optional asset chain.
 * Expected optional files (put them if you use them):
 *   assets/css/reset.css
 *   assets/css/theme.css
 *   assets/css/header-footer.css
 *   assets/css/fonts.css      <-- your self-hosted @font-face rules
 */
function hello_elementor_enqueue_styles_clean() {
	if ( is_admin() ) return;

	$template   = get_template();
	$stylesheet = get_stylesheet();

	// Base style.css (parent/child aware)
	if ( $stylesheet !== $template ) {
		wp_enqueue_style(
			'hello-parent',
			trailingslashit( get_template_directory_uri() ) . 'style.css',
			array(),
			hello_asset_ver( 'style.css' )
		);
		wp_enqueue_style(
			'hello-child',
			trailingslashit( get_stylesheet_directory_uri() ) . 'style.css',
			array( 'hello-parent' ),
			hello_asset_ver( 'style.css' ) // child ver; harmless even if same
		);
		$base_dep = 'hello-child';
	} else {
		wp_enqueue_style(
			'hello-base',
			trailingslashit( get_template_directory_uri() ) . 'style.css',
			array(),
			hello_asset_ver( 'style.css' )
		);
		$base_dep = 'hello-base';
	}

	$css_url = trailingslashit( HELLO_THEME_ASSETS_URL )  . 'css/';
	// reset.css
	if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
		if ( file_exists( HELLO_THEME_ASSETS_PATH . '/css/reset.css' ) ) {
			wp_enqueue_style( 'hello-reset', $css_url . 'reset.css', array( $base_dep ), hello_asset_ver( 'assets/css/reset.css' ) );
		}
		// theme.css
		if ( file_exists( HELLO_THEME_ASSETS_PATH . '/css/theme.css' ) ) {
			$dep = wp_style_is( 'hello-reset', 'enqueued' ) ? array( 'hello-reset' ) : array( $base_dep );
			wp_enqueue_style( 'hello-theme', $css_url . 'theme.css', $dep, hello_asset_ver( 'assets/css/theme.css' ) );
		} else {
			// Keep a predictable chain if theme.css is absent
			wp_register_style( 'hello-theme', false, array( $base_dep ), HELLO_ELEMENTOR_VERSION );
			wp_enqueue_style( 'hello-theme' );
		}
	}

	// header-footer.css (after theme.css)
	if ( apply_filters( 'hello_elementor_header_footer', true ) && file_exists( HELLO_THEME_ASSETS_PATH . '/css/header-footer.css' ) ) {
		wp_enqueue_style( 'hello-header-footer', $css_url . 'header-footer.css', array( 'hello-theme' ), hello_asset_ver( 'assets/css/header-footer.css' ) );
	}

	// Self-hosted fonts.css (after everything so it wins)
	if ( file_exists( HELLO_THEME_ASSETS_PATH . '/css/fonts.css' ) ) {
		wp_enqueue_style( 'hello-fonts', $css_url . 'fonts.css', array( 'hello-theme' ), hello_asset_ver( 'assets/css/fonts.css' ) );
	}

	// Typekit deliberately disabled in this setup (prefer self-hosted)
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_enqueue_styles_clean', 20 );

/* -------------------------------------------------------------------------
 * Single late inline style block (sitewide tweaks, safe & consolidated)
 * ------------------------------------------------------------------------- */
function triuu_print_inline_overrides() {
	// Build CSS in one buffer for readable output
	$css = array();

	// Header baseline styling
	$css[] = <<<CSS
/* HEADER: baseline styling */
header {
	background: var(--accent-color);
	color: #666666;
	padding: 0;
	text-align: center;
	margin-bottom: 1em;
}
CSS;

	// Elementor header spacing
	$css[] = <<<CSS
/* ELEMENTOR HEADER: menu spacing */
.elementor-location-header { padding-bottom: 20px !important; }
CSS;

	// Constrain sections on selected pages to show shadows (simplified :not)
	$css[] = <<<CSS
/* SECTION WIDTH: constrain on specific pages */
.page-id-11  .elementor-section:not(.elementor-location-header),
.page-id-591 .elementor-section:not(.elementor-location-header),
.page-id-300 .elementor-section:not(.elementor-location-header) {
	max-width: 1200px !important;
	margin-left: auto !important;
	margin-right: auto !important;
}
CSS;

	// Drop shadows (exclude header)
	$css[] = <<<CSS
/* DROP SHADOWS */
.page-id-11  .elementor-section { box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important; }
.page-id-591 .elementor-section { box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important; }
.page-id-300 .elementor-section { box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important; }
.page-id-1460 .page-wrapper   { box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important; }
.elementor-location-header .elementor-section { box-shadow: none !important; }
CSS;

	// Headings darker (handle linked titles too)
	$css[] = <<<CSS
/* TITLES: darker headings */
h1,
.entry-title,
.entry-title a,
.site-title,
.site-title a,
.page-title {
	color: #333 !important;
}
CSS;

	$out = implode("\n\n", $css);

	// Print very late so this wins the cascade
	echo "\n<style id=\"triuu-inline-overrides\">\n" . trim( $out ) . "\n</style>\n";
}
add_action( 'wp_head', 'triuu_print_inline_overrides', 999 );

/* -------------------------------------------------------------------------
 * Elementor locations
 * ------------------------------------------------------------------------- */
function hello_elementor_register_locations_clean( $manager ) {
	if ( apply_filters( 'hello_elementor_register_elementor_locations', true ) ) {
		$manager->register_all_core_location();
	}
}
add_action( 'elementor/theme/register_locations', 'hello_elementor_register_locations_clean' );

/* -------------------------------------------------------------------------
 * Meta description from Page/Post excerpt (singular only)
 * ------------------------------------------------------------------------- */
function hello_elementor_meta_description_clean() {
	if ( ! is_singular() || ! apply_filters( 'hello_elementor_description_meta_tag', true ) ) return;
	$post = get_queried_object();
	if ( ! empty( $post->post_excerpt ) ) {
		printf(
			"<meta name=\"description\" content=\"%s\" />\n",
			esc_attr( wp_strip_all_tags( $post->post_excerpt ) )
		);
	}
}
add_action( 'wp_head', 'hello_elementor_meta_description_clean', 1 );

/* -------------------------------------------------------------------------
 * Services Page Modern Styling (guarded filemtime)
 * ------------------------------------------------------------------------- */
function triuu_enqueue_services_page_styles() {
	if ( is_page( 'services' ) ) {
		$rel = '/uploads/elementor/css/services-page.css';
		$abs = WP_CONTENT_DIR . $rel;
		$url = content_url( ltrim( $rel, '/' ) );
		if ( file_exists( $abs ) ) {
			wp_enqueue_style(
				'services-page-modern',
				$url,
				array(),
				(string) filemtime( $abs )
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'triuu_enqueue_services_page_styles', 25 );

/* -------------------------------------------------------------------------
 * body_open shim (legacy)
 * ------------------------------------------------------------------------- */
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
	function hello_elementor_body_open() { wp_body_open(); }
}

/* -------------------------------------------------------------------------
 * NOTE:
 * The old inline append block was removed and merged into triuu_print_inline_overrides().
 * All sitewide custom CSS now prints once (priority 999) to avoid cascade surprises.
 * ------------------------------------------------------------------------- */
