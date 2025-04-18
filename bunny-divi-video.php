<?php
/*
Plugin Name: Bunny Divi Video Embed
Plugin URI:  https://example.com/bunny-divi-video
Description: TinyMCE plugin for inserting Bunny.net videos into Divi (or Classic) posts.
Version:     1.0
Author:      Your Name
Author URI:  https://example.com
Text Domain: bunny-divi-video
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 1) Register & sanitize the default access key setting
 */
add_action( 'admin_init', 'bdv_register_settings' );
function bdv_register_settings() {
    register_setting( 'bdv_settings_group', 'bunny_default_access_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );

    add_settings_section(
        'bdv_settings_section',
        'Bunny.net Settings',
        function() {
            echo '<p>Enter your Bunny.net default Access Key. This will be used to fetch your video libraries.</p>';
        },
        'bunny-divi-video'
    );

    add_settings_field(
        'bunny_default_access_key',
        'Default Access Key',
        function() {
            $key = get_option( 'bunny_default_access_key', '' );
            printf(
                '<input type="text" id="bunny_default_access_key" name="bunny_default_access_key" value="%s" class="regular-text" />',
                esc_attr( $key )
            );
        },
        'bunny-divi-video',
        'bdv_settings_section'
    );
}

/**
 * 2) Add a submenu under Settings → Bunny Video
 */
add_action( 'admin_menu', 'bdv_add_admin_menu' );
function bdv_add_admin_menu() {
    add_options_page(
        'Bunny Divi Video Settings',
        'Bunny Video',
        'manage_options',
        'bunny-divi-video',
        function() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            ?>
            <div class="wrap">
              <h1>Bunny Divi Video Settings</h1>
              <form method="post" action="options.php">
                <?php
                  settings_fields( 'bdv_settings_group' );
                  do_settings_sections( 'bunny-divi-video' );
                  submit_button();
                ?>
              </form>
            </div>
            <?php
        }
    );
}

/**
 * 3) Enqueue the TinyMCE plugin script on post edit screens
 */
add_action( 'admin_enqueue_scripts', 'bdv_enqueue_scripts' );
function bdv_enqueue_scripts( $hook ) {
    // Only load on the post editor
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        return;
    }

    // Enqueue your JS
    wp_enqueue_script(
        'bunny-divi-video',
        plugin_dir_url( __FILE__ ) . 'bunny-divi-video.js',
        [ 'jquery', 'tinymce' ],
        '1.0',
        true
    );

    // Pass the default key into JS
    wp_localize_script(
        'bunny-divi-video',
        'bunnySettings',
        [ 'defaultAccessKey' => get_option( 'bunny_default_access_key', '' ) ]
    );

    // Tell TinyMCE to load your plugin and add your button
    add_filter( 'mce_external_plugins', 'bdv_add_tinymce_plugin' );
    add_filter( 'mce_buttons', 'bdv_register_tinymce_button' );
}

function bdv_add_tinymce_plugin( $plugins ) {
    $plugins['bunny_divi_video'] = plugin_dir_url( __FILE__ ) . 'bunny-divi-video.js';
    return $plugins;
}

function bdv_register_tinymce_button( $buttons ) {
    array_push( $buttons, 'bunnyVideo' );
    return $buttons;
}
