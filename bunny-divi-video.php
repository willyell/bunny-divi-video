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
        // Register TinyMCE plugin script (ensure file exists)
    add_filter( 'mce_external_plugins', function( $plugins ) {
        $js_file = plugin_dir_path( __FILE__ ) . 'js/bunny-tinymce.js';
        if ( file_exists( $js_file ) ) {
            $plugins['bunny_video'] = plugin_dir_url( __FILE__ ) . 'js/bunny-tinymce.js';
        }
        return $plugins;
    });
    // Add button to toolbar
    add_filter( 'mce_buttons', function( $buttons ) {
        array_push( $buttons, 'bunny_video' );
        return $buttons;
    });
});

// ------------------------------
// ------------------------------
// 3) Divi Module
// ------------------------------
function register_bunny_divi_module() {
    if ( ! class_exists( 'ET_Builder_Module' ) ) {
        return;
    }
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
            // Fetch list of libraries via Bunny Master API
            $libraries = array();
            $lib_resp = wp_remote_get('https://video.bunnycdn.com/library', array(
                'headers' => array('AccessKey' => BUNNY_MASTER_API_KEY),
            ));
            if (!is_wp_error($lib_resp)) {
                $lib_data = json_decode(wp_remote_retrieve_body($lib_resp), true);
                if (isset($lib_data['items']) && is_array($lib_data['items'])) {
                    foreach ($lib_data['items'] as $lib_item) {
                        if (isset($lib_item['id'], $lib_item['name'])) {
                            $libraries[$lib_item['id']] = $lib_item['name'];
                        }
                    }
                }
            }
            if (empty($libraries)) {
                $libraries = array(BUNNY_LIBRARY_ID => 'Default Library');
            }
            // Determine selected library (existing prop or first)
            $selected_lib = isset($this->props['library_id']) && $this->props['library_id']
                ? $this->props['library_id']
                : key($libraries);
            // Fetch videos for selected library
            $videos = array();
            $vid_resp = wp_remote_get("https://video.bunnycdn.com/library/{$selected_lib}/videos", array(
                'headers' => array('AccessKey' => BUNNY_MASTER_API_KEY),
            ));
            if (!is_wp_error($vid_resp)) {
                $vid_data = json_decode(wp_remote_retrieve_body($vid_resp), true);
                if (isset($vid_data['items']) && is_array($vid_data['items'])) {
                    foreach ($vid_data['items'] as $video) {
                        if (isset($video['guid'], $video['title'])) {
                            $videos[$video['guid']] = $video['title'];
                        }
                    }
                }
            }
            if (empty($videos)) {
                $videos = array('' => esc_html__('No videos found', 'bunny-divi'));
            }
            return array(
                'library_id' => array(
                    'label'           => esc_html__('Select Library', 'bunny-divi'),
                    'type'            => 'select',
                    'options'         => $libraries,
                    'default'         => $selected_lib,
                    'option_category' => 'basic_option',
                    'description'     => esc_html__('Choose a Bunny Stream library.', 'bunny-divi'),
                ),
                'video_id' => array(
                    'label'           => esc_html__('Select Video', 'bunny-divi'),
                    'type'            => 'select',
                    'options'         => $videos,
                    'default'         => key($videos),
                    'option_category' => 'basic_option',
                    'description'     => esc_html__('Choose a Bunny Stream video.', 'bunny-divi'),
                ),
            );
        }

        public function render( $attrs, $content, $render_slug ) {
            $video_id = $this->props['video_id'];
            if ( ! $video_id ) {
                return '<p>Please select a video in module settings.</p>';
            }
            $zone       = '353748';
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
    // Instantiate the module
    new Bunny_Stream_Module();
}
// Hook into Divi builder initialization
add_action( 'et_builder_ready', 'register_bunny_divi_module' );

// end of code script

