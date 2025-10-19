<?php
/**
 * Hello Elementor – streamlined bootstrap
 * - Guarded constants
 * - Asset versioning (filemtime in dev)
 * - Correct parent/child enqueue order
 * - Local CSS (reset/theme/header-footer) + self-hosted fonts.css
 * - Typekit + resource hints
 * - Elementor locations
 * - Excerpt -> <meta name="description">
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------------- *
 * Constants (guarded)
 * ------------------------------------------------------------------------- */
if ( ! defined( 'HELLO_ELEMENTOR_VERSION' ) ) define( 'HELLO_ELEMENTOR_VERSION', '3.4.4' );
if ( ! defined( 'HELLO_THEME_PATH' ) )        define( 'HELLO_THEME_PATH',        get_template_directory() );
if ( ! defined( 'HELLO_THEME_URL' ) )         define( 'HELLO_THEME_URL',         get_template_directory_uri() );
if ( ! defined( 'HELLO_THEME_ASSETS_PATH' ) ) define( 'HELLO_THEME_ASSETS_PATH', HELLO_THEME_PATH . '/assets' );
if ( ! defined( 'HELLO_THEME_ASSETS_URL' ) )  define( 'HELLO_THEME_ASSETS_URL',  HELLO_THEME_URL  . '/assets' );

/**
 * Version helper: uses filemtime() in WP_DEBUG to bust cache in Local; falls back to theme version.
 */
function hello_asset_ver( $rel_path_from_theme_root ) {
        $abs = trailingslashit( get_template_directory() ) . ltrim( $rel_path_from_theme_root, '/\\' );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $abs ) ) {
                $mtime = @filemtime( $abs );
                if ( $mtime ) return (string) $mtime;
        }
        return HELLO_ELEMENTOR_VERSION;
}

/* ------------------------------------------------------------------------- *
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
        add_theme_support( 'html5', [ 'search-form','comment-form','comment-list','gallery','caption','script','style','navigation-widgets' ] );
        add_theme_support( 'custom-logo', [ 'height'=>100, 'width'=>350, 'flex-height'=>true, 'flex-width'=>true ] );
        add_theme_support( 'align-wide' );
        add_theme_support( 'responsive-embeds' );
        add_theme_support( 'editor-styles' );
        add_editor_style( 'editor-styles.css' );

        register_nav_menus( [
                'menu-1' => esc_html__( 'Header Menu', 'hello-elementor' ),
                'menu-2' => esc_html__( 'Footer Menu', 'hello-elementor' ),
        ] );

        // Excerpts on pages (used for meta description below)
        add_post_type_support( 'page', 'excerpt' );

        // Optional Woo
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

/* ------------------------------------------------------------------------- *
 * Styles & Fonts
 * ------------------------------------------------------------------------- */
/**
 * Enqueue theme styles + local assets.
 * Expected files (put them if you use them):
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
                wp_enqueue_style( 'hello-parent',
                        trailingslashit( get_template_directory_uri() ) . 'style.css',
                        [],
                        hello_asset_ver( 'style.css' )
                );
                wp_enqueue_style( 'hello-child',
                        trailingslashit( get_stylesheet_directory_uri() ) . 'style.css',
                        [ 'hello-parent' ],
                        hello_asset_ver( 'style.css' )
                );
                $base_dep = 'hello-child';
        } else {
                wp_enqueue_style( 'hello-base',
                        trailingslashit( get_template_directory_uri() ) . 'style.css',
                        [],
                        hello_asset_ver( 'style.css' )
                );
                $base_dep = 'hello-base';
        }

        $css_url = trailingslashit( HELLO_THEME_ASSETS_URL )  . 'css/';
        // reset.css
        if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
                if ( file_exists( HELLO_THEME_ASSETS_PATH . '/css/reset.css' ) ) {
                        wp_enqueue_style( 'hello-reset', $css_url . 'reset.css', [ $base_dep ], hello_asset_ver( 'assets/css/reset.css' ) );
                }
                // theme.css
                if ( file_exists( HELLO_THEME_ASSETS_PATH . '/css/theme.css' ) ) {
                        wp_enqueue_style( 'hello-theme', $css_url . 'theme.css', [ 'hello-reset' ], hello_asset_ver( 'assets/css/theme.css' ) );
                } else {
                        // If no reset/theme, keep chain sane:
                        wp_register_style( 'hello-theme', false, [ $base_dep ], HELLO_ELEMENTOR_VERSION );
                        wp_enqueue_style( 'hello-theme' );
                }
        }

        // header-footer.css (after theme.css)
        if ( apply_filters( 'hello_elementor_header_footer', true ) && file_exists( HELLO_THEME_ASSETS_PATH . '/css/header-footer.css' ) ) {
                wp_enqueue_style( 'hello-header-footer', $css_url . 'header-footer.css', [ 'hello-theme' ], hello_asset_ver( 'assets/css/header-footer.css' ) );
        }

        // Self-hosted fonts.css (after everything so it wins)
        if ( file_exists( HELLO_THEME_ASSETS_PATH . '/css/fonts.css' ) ) {
                wp_enqueue_style( 'hello-fonts', $css_url . 'fonts.css', [ 'hello-theme' ], hello_asset_ver( 'assets/css/fonts.css' ) );
        }

        // Typekit disabled - using self-hosted Google Fonts instead
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_enqueue_styles_clean', 20 );

/**
 * TRI-UU Custom Styles: Global header styling, menu spacing & drop shadows
 * Added 2025-10-19
 * Updated to include all custom CSS in child theme (safe from parent theme updates)
 */
function triuu_add_custom_shadow_styles() {
        echo "\n<style id=\"triuu-custom-styles\">\n";
        echo "/* ===================================== */\n";
        echo "/* TRI-UU: Global Custom Styles         */\n";
        echo "/* ===================================== */\n\n";
        
        echo "/* HEADER: Global header styling (all pages) */\n";
        echo "header {\n";
        echo "    background: var(--accent-color);\n";
        echo "    color: #666666;\n";
        echo "    padding: 0;\n";
        echo "    text-align: center;\n";
        echo "    margin-bottom: 1em;\n";
        echo "}\n\n";
        
        echo "/* ELEMENTOR HEADER: Menu spacing */\n";
        echo ".elementor-location-header { padding-bottom: 20px !important; }\n\n";
        
        echo "/* SECTION WIDTH: Constrain sections to 1200px to make drop shadows visible */\n";
        echo ".page-id-11 .elementor-section:first-of-type:not(.elementor-location-header .elementor-section),\n";
        echo ".page-id-591 .elementor-section:not(.elementor-location-header .elementor-section),\n";
        echo ".page-id-300 .elementor-section:not(.elementor-location-header .elementor-section) {\n";
        echo "    max-width: 1200px !important;\n";
        echo "    margin-left: auto !important;\n";
        echo "    margin-right: auto !important;\n";
        echo "}\n\n";
        
        echo "/* DROP SHADOWS: Apply to constrained sections */\n";
        echo ".page-id-11 .elementor-section:first-of-type { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important; }\n";
        echo ".page-id-591 .elementor-section { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important; }\n";
        echo ".page-id-300 .elementor-section { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important; }\n";
        echo ".page-id-1460 .page-wrapper { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important; }\n\n";
        
        echo "/* Exclude header from shadows */\n";
        echo ".elementor-location-header .elementor-section { box-shadow: none !important; }\n";
        echo "</style>\n";
}
add_action( 'wp_head', 'triuu_add_custom_shadow_styles', 999 );

// Resource hints for Typekit removed - using self-hosted fonts

/* ------------------------------------------------------------------------- *
 * Elementor locations
 * ------------------------------------------------------------------------- */
function hello_elementor_register_locations_clean( $manager ) {
        if ( apply_filters( 'hello_elementor_register_elementor_locations', true ) ) {
                $manager->register_all_core_location();
        }
}
add_action( 'elementor/theme/register_locations', 'hello_elementor_register_locations_clean' );

/* ------------------------------------------------------------------------- *
 * Meta description from Page/Post excerpt (singular only)
 * ------------------------------------------------------------------------- */
function hello_elementor_meta_description_clean() {
        if ( ! is_singular() || ! apply_filters( 'hello_elementor_description_meta_tag', true ) ) return;
        $post = get_queried_object();
        if ( ! empty( $post->post_excerpt ) ) {
                printf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) );
        }
}
add_action( 'wp_head', 'hello_elementor_meta_description_clean', 1 );

/* ------------------------------------------------------------------------- *
 * Services Page Modern Styling
 * ------------------------------------------------------------------------- */
function triuu_enqueue_services_page_styles() {
        if ( is_page( 'services' ) ) {
                wp_enqueue_style(
                        'services-page-modern',
                        content_url( 'uploads/elementor/css/services-page.css' ),
                        [],
                        filemtime( WP_CONTENT_DIR . '/uploads/elementor/css/services-page.css' )
                );
        }
}
add_action( 'wp_enqueue_scripts', 'triuu_enqueue_services_page_styles', 25 );

/* ------------------------------------------------------------------------- *
 * body_open shim (legacy)
 * ------------------------------------------------------------------------- */
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
        function hello_elementor_body_open() { wp_body_open(); }
}
