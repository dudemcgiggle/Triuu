<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly.
}

// Version constant
define( 'HELLO_ELEMENTOR_VERSION', '3.4.4' );

// Paths & URLs
define( 'HELLO_THEME_PATH',        get_template_directory() );
define( 'HELLO_THEME_URL',         get_template_directory_uri() );
define( 'HELLO_THEME_ASSETS_PATH', HELLO_THEME_PATH . '/assets/' );
define( 'HELLO_THEME_ASSETS_URL',  HELLO_THEME_URL  . '/assets/' );
define( 'HELLO_THEME_STYLE_URL',   HELLO_THEME_ASSETS_URL . 'css/' );

/**
 * Theme setup
 */
function hello_elementor_setup() {
        if ( is_admin() ) {
                $opt = 'hello_theme_version';
                if ( ! get_option( $opt ) || version_compare( get_option( $opt ), HELLO_ELEMENTOR_VERSION, '<' ) ) {
                        update_option( $opt, HELLO_ELEMENTOR_VERSION );
                }
        }

        register_nav_menus([
                'menu-1' => esc_html__( 'Header Menu', 'hello-elementor' ),
                'menu-2' => esc_html__( 'Footer Menu', 'hello-elementor' ),
        ]);

        add_post_type_support( 'page', 'excerpt' );

        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'automatic-feed-links' );
        add_theme_support( 'title-tag' );
        add_theme_support( 'html5', [
                'search-form', 'comment-form', 'comment-list', 'gallery',
                'caption', 'script', 'style', 'navigation-widgets'
        ]);
        add_theme_support( 'custom-logo', [
                'height' => 100,
                'width'  => 350,
                'flex-height' => true,
                'flex-width'  => true,
        ]);
        add_theme_support( 'align-wide' );
        add_theme_support( 'responsive-embeds' );
        add_theme_support( 'editor-styles' );
        add_editor_style( 'editor-styles.css' );

        // Optional: WooCommerce support
        if ( apply_filters( 'hello_elementor_add_woocommerce_support', true ) ) {
                add_theme_support( 'woocommerce' );
                add_theme_support( 'wc-product-gallery-zoom' );
                add_theme_support( 'wc-product-gallery-lightbox' );
                add_theme_support( 'wc-product-gallery-slider' );
        }
}
add_action( 'after_setup_theme', 'hello_elementor_setup' );

/**
 * Enqueue styles
 */
function hello_elementor_scripts_styles() {
        if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
                wp_enqueue_style(
                        'hello-elementor-reset',
                        HELLO_THEME_STYLE_URL . 'reset.css',
                        [],
                        HELLO_ELEMENTOR_VERSION
                );
                wp_enqueue_style(
                        'hello-elementor-theme',
                        HELLO_THEME_STYLE_URL . 'theme.css',
                        [],
                        HELLO_ELEMENTOR_VERSION
                );
        }

        if ( apply_filters( 'hello_elementor_header_footer', true ) ) {
                wp_enqueue_style(
                        'hello-elementor-header-footer',
                        HELLO_THEME_STYLE_URL . 'header-footer.css',
                        [],
                        HELLO_ELEMENTOR_VERSION
                );
        }
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_scripts_styles' );

/**
 * Adobe Fonts - Disabled (using self-hosted Google Fonts instead)
 */
// function hello_elementor_enqueue_adobe_fonts() {
//      wp_enqueue_style(
//              'hello-elementor-typekit',
//              'https://use.typekit.net/dcl5phc.css',
//              [],
//              null
//      );
// }
// add_action( 'wp_enqueue_scripts', 'hello_elementor_enqueue_adobe_fonts' );

/**
 * Elementor Locations
 */
function hello_elementor_register_elementor_locations( $manager ) {
        if ( apply_filters( 'hello_elementor_register_elementor_locations', true ) ) {
                $manager->register_all_core_location();
        }
}
add_action( 'elementor/theme/register_locations', 'hello_elementor_register_elementor_locations' );

/**
 * Content width
 */
function hello_elementor_content_width() {
        $GLOBALS['content_width'] = apply_filters( 'hello_elementor_content_width', 800 );
}
add_action( 'after_setup_theme', 'hello_elementor_content_width', 0 );

/**
 * Meta description tag
 */
function hello_elementor_add_description_meta_tag() {
        if (
                ! is_singular() ||
                ! apply_filters( 'hello_elementor_description_meta_tag', true )
        ) {
                return;
        }
        $post = get_queried_object();
        if ( ! empty( $post->post_excerpt ) ) {
                echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) . '">' . "\n";
        }
}
add_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );

/**
 * Backward compatibility for body_open
 */
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
        function hello_elementor_body_open() {
                wp_body_open();
        }
}

/**
 * TRI-UU Custom Styles: Consistent menu spacing & drop shadows
 * Added 2025-10-19
 */
function triuu_add_custom_shadow_styles() {
        echo "\n<style id=\"triuu-custom-styles\">\n";
        echo "/* TRI-UU: Menu spacing & drop shadows */\n\n";
        
        echo "/* HEADER: One source of truth for menu spacing */\n";
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
