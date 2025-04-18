<?php
/**
 * Plugin Name: Bunny Stream Video - Divi Module & TinyMCE
 * Description: Divi module and classic editor TinyMCE button to insert Bunny Stream videos.
 * Version: 1.1
 * Author: Will Yell
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ------------------------------
// 1) Shortcode for frontend embed
// ------------------------------
function bunny_video_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'library' => '',
        'id'      => '',
    ), $atts, 'bunny_video' );

    if ( empty( $atts['library'] ) || empty( $atts['id'] ) ) {
        return '<!-- bunny_video: missing library or id -->';
    }

    $src = sprintf(
        'https://iframe.mediadelivery.net/embed/%1$s/%2$s?autoplay=false',
        esc_attr( $atts['library'] ),
        esc_attr( $atts['id'] )
    );

    return sprintf(
        '<div style="position:relative;padding-top:56.25%%;"><iframe src="%1$s" style="position:absolute;top:0;left:0;width:100%%;height:100%%;border:0;" allow="autoplay; encrypted-media; picture-in-picture" loading="lazy" allowfullscreen></iframe></div>',
        esc_url( $src )
    );
}
add_shortcode( 'bunny_video', 'bunny_video_shortcode' );

// ------------------------------
// 2) TinyMCE button + modal
// ------------------------------
add_action( 'admin_init', function() {
    if ( ! current_user_can( 'edit_posts' ) || ! get_user_option( 'rich_editing' ) ) {
        return;
    }
    // Register TinyMCE plugin script
    add_filter( 'mce_external_plugins', function( $plugins ) {
        $plugins['bunny_video'] = plugin_dir_url( __FILE__ ) . 'js/bunny-tinymce.js';
        return $plugins;
    });
    // Add button to toolbar
    add_filter( 'mce_buttons', function( $buttons ) {
        array_push( $buttons, 'bunny_video' );
        return $buttons;
    });
});

// ------------------------------
// 3) Divi Module
// ------------------------------
if ( class_exists( 'ET_Builder_Module' ) ) {
    class Bunny_Stream_Module extends ET_Builder_Module {
        public $slug       = 'bunny_stream';
        public $vb_support = 'on';

        public function init() {
            $this->name = esc_html__( 'Bunny Stream Video', 'bunny-divi' );
            $this->settings_modal_toggles = array(
                'general' => array(
                    'toggles' => array(
                        'main_content' => esc_html__( 'Video Settings', 'bunny-divi' ),
                    ),
                ),
            );
        }

        public function get_fields() {
            $api_key = 'fcc29d6f-009b-4aa2-a97ac29f8bfe-6c27-4d36';
            $lib_id  = '353748';
            $options = array( '' => esc_html__( 'No videos found', 'bunny-divi' ) );

            $response = wp_remote_get( "https://video.bunnycdn.com/library/{$lib_id}/videos", array(
                'headers' => array( 'AccessKey' => $api_key ),
            ));
            if ( ! is_wp_error( $response ) ) {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
                    $options = array();
                    foreach ( $data['items'] as $video ) {
                        if ( isset( $video['guid'], $video['title'] ) ) {
                            $options[ $video['guid'] ] = $video['title'];
                        }
                    }
                }
            }

            return array(
                'video_id' => array(
                    'label'           => esc_html__( 'Select Video', 'bunny-divi' ),
                    'type'            => 'select',
                    'options'         => $options,
                    'option_category' => 'basic_option',
                    'description'     => esc_html__( 'Choose a Bunny Stream video.', 'bunny-divi' ),
                ),
            );
        }

        public function render( $attrs, $content, $render_slug ) {
            $video_id = $this->props['video_id'];
            if ( ! $video_id ) {
                return '<p>Please select a video in module settings.</p>';
            }
            $zone = '353748';
            $iframe_src = esc_url( "https://iframe.mediadelivery.net/embed/{$zone}/{$video_id}?autoplay=false" );

            ob_start();
            ?>
            <div class="bunny-video-wrapper" style="position:relative;padding-top:56.25%;overflow:hidden;">
                <iframe src="<?php echo $iframe_src; ?>" loading="lazy"
                        allow="autoplay; encrypted-media; picture-in-picture"
                        style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
                        allowfullscreen></iframe>
                <div class="bunny-overlay"
                     onclick="this.style.display='none'; var iframe=this.parentElement.querySelector('iframe'); iframe.src=iframe.src.replace('autoplay=false','autoplay=true');"
                     style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);cursor:pointer;">
                    <div style="position:absolute;top:10%;width:100%;text-align:center;color:#fff;font-family:Teko;font-size:2em;">Try A FREE Workout</div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }
    new Bunny_Stream_Module();
}

// end of code script
