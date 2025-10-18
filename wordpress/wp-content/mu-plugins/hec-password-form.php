<?php
/**
 * Plugin Name: HEC Custom Password Form
 * Description: Safely overrides the WordPress password form with custom HTML, CSS, and animated Lottie icon.
 * Version:     1.0.1
 * Author:      Your Name
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enqueue custom CSS and Lottie animation
 */
add_action( 'wp_enqueue_scripts', function() {
    $base_url = content_url( '/mu-plugins/assets/' );

    // Custom password form styles
    wp_enqueue_style(
        'hec-password-style',
        $base_url . 'password-form.css',
        [],
        null
    );

    // Lottie animation runtime
    wp_enqueue_script(
        'hec-lottie-web',
        'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.9.4/lottie.min.js',
        [],
        null,
        true
    );

    // Custom animation loader
    wp_enqueue_script(
        'hec-lock-init',
        $base_url . 'password-lock.js',
        [ 'hec-lottie-web' ],
        null,
        true
    );

    // Inject asset base path into JS for lock.json reference
    wp_localize_script( 'hec-lock-init', 'themeAssetsUrl', [
        'base' => $base_url
    ]);
}, 20 );

/**
 * Override WordPress password-protected post form
 */
add_filter( 'the_password_form', function( $form ) {
    global $post;
    $label_id = 'pwbox-' . ( empty( $post->ID ) ? wp_rand() : $post->ID );
    $form_action = esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) );

    ob_start(); ?>
    <div class="hec-pf-container">
      <div class="hec-pf-card">
        <div class="hec-pf-icon-wrap">
          <span class="hec-pf-icon" id="hec-lock"></span>
        </div>
        <h2 class="hec-pf-title">Secret Access</h2>
        <p class="hec-pf-subtitle">Enter your secret code to unlock this page:</p>
        <form action="<?php echo $form_action; ?>" method="post" class="hec-pf-form">
          <div class="hec-pf-field">
            <label for="<?php echo esc_attr( $label_id ); ?>" class="hec-pf-label">Password</label>
            <input name="post_password" id="<?php echo esc_attr( $label_id ); ?>" type="password" class="hec-pf-input" placeholder="Your password" required>
          </div>
          <div class="hec-pf-tip">
            <em class="hec-pf-tip-text">Need help? Contact support.</em>
          </div>
          <div class="hec-pf-button-wrap">
            <button type="submit" class="hec-pf-button">Enter</button>
          </div>
        </form>
        <p class="hec-pf-footer-text">Powered by MySiteCorp</p>
      </div>
    </div>
    <?php
    return ob_get_clean();
});
