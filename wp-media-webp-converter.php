<?php
/**
 * Plugin Name: WP Media WebP Converter
 * Description: Convert all media images to WebP format with progress bar, single image conversion, automatic conversion on upload, and lossless compression.
 * Version: 1.0.0
 * Author: Subrata Sarkar
 * Author URI: https://github.com/emfluenceindia
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-media-webp-converter
 * Method Prefix: wpmwc_
 * 
 * ********************** TEST ***********************************************
 * Sample call: https://techblog.lndo.site/wp-admin/admin-ajax.php?action=wpmwc_convert_individual_image&nonce=123456&id=10&overwrite=false&image_quality=50&conversion_mode=new
 * Change _GET to _POST
 * Uncomment chack_ajax_referrer
 */

defined('ABSPATH') || exit;

if( is_admin() ) {
    require_once 'includes/wpmwc-converter.php';
}

/**
 * Enqueue admin scripts
 */
function wpmwc_enqueue_scripts( $hook ) {
    // Why I need this???
    //if( ! in_array( $hook, ['tools_page_webp-converter', 'upload.php'] ) ) return;

    // enqueue the script
    wp_enqueue_script( 
        'wpmwc-admin-script', // ID
        plugin_dir_url( __FILE__ ) . 'js/admin.js',  // script source
        ['jquery'], // dependency
        filemtime( plugin_dir_path( __FILE__ ) . 'js/admin.js' ), // version
        true //
    );

    //script localization
    wp_localize_script( 'wpmwc-admin-script', 'WPMWC', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wpmwc_nonce' )
    ] );

    // enqueue CSS
    wp_enqueue_style( 
        'wpmwc-admin-style', 
        plugin_dir_url( __FILE__ ) . 'css/admin.css',
        [], 
        filemtime( plugin_dir_path( __FILE__ ) . 'css/admin.css' ) 
    );
}

add_action( 'admin_enqueue_scripts', 'wpmwc_enqueue_scripts' );

add_action( 'admin_footer-upload.php', function() {
    wp_enqueue_script( 
        'upload-page-embed', // handle
        plugin_dir_url( __FILE__ ) . 'js/upload-embed.js', // source
        array( 'jquery' ),  // dependency
        filemtime( plugin_dir_path( __FILE__ ) . 'js/upload-embed.js' )  // version
    );
} );

/**
 * Add a plugin management / settigns page in the admin
 */
function wpmwc_add_management_page() {
    add_options_page(
        __( 'Convert to WebP', 'wp-media-webp-converter' ),    // Page title
        __( 'Convert to WebP', 'wp-media-webp-converter' ),    // Menu title
        'manage_options',                                      // Capability
        'wpmwc-convert-to-webp',                               // Menu slug
        'wpmwc_render_settings_page'   // Callback function to render the page
    );
}

add_action( 'admin_menu', 'wpmwc_add_management_page' );

/**
 * Add a Settings link (plugin action link) under the plugin name on main plugin page
 * 
 * @param  array $links An array of plugin action links
 * @return array An updated array of plugin action links
 */
function wpmwc_add_plugin_settings_links( $links ) {
    // Build the URL
    $settings_url = admin_url( 'options-general.php?page=wpmwc-convert-to-webp' );

    // Create the HTML for the link
    $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'wp-media-webp-converter' ) . '</a>';

    // Add the link to the beginning of the links array
    array_unshift( $links, $settings_link );

    // Return the modified $links array
    return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpmwc_add_plugin_settings_links' );

/**
 * Fetch and count image files by MIME types
 */
function wpmwc_count_images_by_mime_types() {
    global $wpdb;

    $mime_types = array(
        'image/jpeg'    => 'JPEG',
        'image/png'     => 'PNG',
        'image/gif'     => 'GIF',
        //'image/svg+xml' => 'SVG'
    );

    $counts = array();

    foreach( $mime_types as $mime => $label ) {
        $count = $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts
            WHERE post_type = 'attachment' AND post_mime_type = %s", $mime )
        );

        $counts[ $label ] = $count;
    }

    return $counts;
}

/**
 * Display image counts by MIME types
 */
function wpmwc_display_image_counts_by_mime_type() {

    /** For debuggin. Remove on production */
    // $source_file_path = get_attached_file( 21 );
    // $path_info = pathinfo( $source_file_path );
    // $webp_filename = $path_info[ 'filename' ] . '.webp';
    // var_dump( $path_info );
    // var_dump(wp_upload_dir()['url'] . '/' . $webp_filename);
    /** */
    
    $counts = wpmwc_count_images_by_mime_types(); ?>
    <div class="wrap">
        <h2><?php echo __( 'Image count by MIME types', 'wp-media-webp-converter' ) ?></h2>
        <table class="wpmwc-table-default" style="width: 50%;margin: 0;"  cellspacing=0 cellpadding=0>
            <tr>
                <th class="left"><?php echo __( 'MIME Type', 'wp-media-webp-converter' ) ?></th>
                <th><?php echo __( 'Image count', 'wp-media-webp-converter' ) ?></th>
            </tr>
            <?php foreach( $counts as $type => $count ) { ?>
                <tr>
                    <td class="left"><?php echo $type; ?></td>
                    <td><?php echo number_format_i18n( $count ); ?> files</td>
                </tr>
            <?php } ?>
        </table>
    </div>
<?php }


/**
 * Management page callback funciton body
 */

function wpmwc_render_settings_page() { ?>
    <div class="wrap">
        <h1><?php echo __( 'Convert images to WebP in Bulk', 'wp-media-webp-converter' ); ?></h1>
        <hr />
        <p><strong><?php echo __( 'Convert all JPEG/PNG/GIF images in the media library to WebP format (lossless).', 'wp-media-webp-converter' ); ?></strong></p>
        <?php wpmwc_display_image_counts_by_mime_type(); ?>
        <form method="post" class="wpmwc-form-default">
            <div style="margin-top: 10px;">
                <table class="wpwmc-action-table">
                    <tr>
                        <td>
                            <?php wp_nonce_field( 'wpmwc_bulk_conversion_action', 'wpmwc_bulk_conversion_nonce' ); ?>
                        </td>
                        <td class="option">
                            <input type="checkbox" id="overwrite" name="overwrite" value="1" />
                            <?php echo __( 'Overwrite existing WebP files', 'wp-media-webp-converter' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php echo __( 'Image quality: ', 'wp-media-webp-converter' ); ?>
                        </td>
                        <td class="option">
                            <select id="image_quality" name="image_quality">
                                <option value=""><?php echo __( '-- Select --', 'wp-media-webp-converter' ); ?></option>    
                                <option value="100"> <?php echo __( 'Maximum (larger than source file)', 'wp-media-webp-converter' ); ?> </option>
                                <option value="75"><?php echo __( 'Optimized', 'wp-media-webp-converter' ) ?></option>
                                <option value="50"><?php echo __( 'Good', 'wp-media-webp-converter' ) ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php echo __( 'I want to ', 'wp-media-webp-converter' ); ?>
                        </td>
                        <td class="option">
                            <select id="conversion_mode" name="conversion_mode">
                                <option value=""><?php echo __( '-- Select --', 'wp-media-webp-converter' ); ?></option>
                                <option value="none"><?php echo __( 'Convert Only', 'wp-media-webp-converter' ) ?></option>
                                <option value="new"> <?php echo __( 'Convert & Create New Attachment', 'wp-media-webp-converter' ); ?> </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>&nbsp;</td>    
                        <td class="option">
                            <div>
                                <?php echo __( '<b>Convert Only</b> <span>converts the image to WebP and stores the newly created file in the same folder. They are not available on the WordPress Media Library; however, they reside inside the folder.</span>' , 'wp-media-webp-converter' ); ?>
                            </div>
                            <hr />
                            <div>
                                <?php echo __( '<b>Convert & Create New Attachment</b> <span>does the same thing as Convert Only, but additionally creates a new attachment at the same time to make it immediately available in the WordPress Media Library.</span>' , 'wp-media-webp-converter' ); ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <div style="margin-top: 30px;">
                <button id="wpmwc-start" class="button button-primary">
                    <?php echo __( 'Start Conversion', 'wp-media-webp-converter' ); ?>
                </button>
            </div>
        </form>
        <div id="wpmwc-progress-wrapper" style="display: none;margin-top: 20px;">
            <div id="wpmwc-progress-bar">
                <div id="wpmwc-progress-inner"></div>
            </div>
            <div id="wpmwc-progress-status"  style="margin-top: 15px;">0%</div>
        </div>
        <div id="wpmwc-progress-log"></div>
    </div>
<?php }

/**
 * AJAX: Get the image ID
 */
function wpmwc_get_images() {
    check_ajax_referer( 'wpmwc_nonce', 'nonce' );

    $attachments = get_posts(
        array(
            'post_type'      => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
            'posts_per_page' => -1, // fetch all
            'fields'         => 'ids',
        )
    );

    wp_send_json_success( $attachments );
}

add_action( 'wp_ajax_wpmwc_get_images', 'wpmwc_get_images' );

/**
 * AJAX: Convert individual image to WebP
 */
function wpmwc_convert_individual_image() {
    check_ajax_referer( 'wpmwc_nonce', 'nonce' );

    $id             = intval($_POST['id']);
    $overwrite      = isset( $_POST[ 'overwrite' ] ) ? (bool)$_POST[ 'overwrite' ] : false;
    $quality        = isset( $_POST[ 'image_quality' ] ) ? intval( $_POST[ 'image_quality' ] ) : 100;
    $action_mode    = isset( $_POST[ 'conversion_mode' ] ) ? $_POST[ 'conversion_mode' ] : 'none';
    $file           = get_attached_file( $id );
    $info           = pathinfo( $file );


    // /***** For debug. Remove on production */

    // $id             = intval($_REQUEST['id']);
    // $overwrite      = isset( $_REQUEST[ 'overwrite' ] ) ? (bool)$_REQUEST[ 'overwrite' ] : false;
    // $quality        = isset( $_REQUEST[ 'image_quality' ] ) ? intval( $_REQUEST[ 'image_quality' ] ) : 100;
    // $action_mode    = isset( $_REQUEST[ 'conversion_mode' ] ) ? $_REQUEST[ 'conversion_mode' ] : 'none';
    // $file           = get_attached_file( $id );
    // $info           = pathinfo( $file );

    // var_dump( $file );
    // echo '<hr />';

    // die();

    /** */

    $metadata       = wp_get_attachment_metadata( $id );
    $thumb_sizes    = $metadata[ 'sizes' ];
    
    $thumb_files    = array();
    foreach( $thumb_sizes as $thumb_size ) {
        $file_path = $info[ 'dirname' ] . '/' . $thumb_size[ 'file' ];
        array_push( $thumb_files, $file_path );
    }
    array_unshift( $thumb_files, $file );

    $file_info = getimagesize( $file );
    $mime_type =  trim( strtolower( $file_info[ 'mime' ] ) );

    /******************** */
    // var_dump( $file_info );
    // echo '<hr />';
    // var_dump( $mime_type );
    /******************** */

    if( $mime_type === 'image/jpeg' ) {
        wpmwc_convert_jpeg_to_webp( $file, $thumb_files, $overwrite, $quality, $action_mode );
    } else if( $mime_type === 'image/png') {
        wpmwc_convert_png_to_webp( $file, $thumb_files, $overwrite, $quality, $action_mode );
    } else if( $mime_type === 'image/gif' ) {
        wpmwc_convert_gif_to_webp( $file, $thumb_files, $overwrite, $quality, $action_mode );
    }

    //die();

    // if( $result ) {
    //     wp_send_json_success( 'Image has been successfully converted to WebP' );
    // } else {
    //     wp_send_json_error( 'Operation failed.' );
    // }
}

add_action( 'wp_ajax_wpmwc_convert_individual_image', 'wpmwc_convert_individual_image' );

/**
 * Media Library: Convert indivdual image
 */
function wpmwc_create_local_convert_action_link( $actions, $post ) {
    $link_html = '<a href="javascript: void(0);" class="wpmwc-convert-single" data-id="' . $post->ID . '">' . __( 'Convert to WebP', 'wp-media-webp-converter' ) . '</a>';
    if( in_array( get_post_mime_type( $post ), [ 'image/jpeg', 'image/png' ] ) ) {
        $actions[ 'convert_webp' ] = $link_html;
    }

    return $actions;
}

add_filter( 'media_row_action', 'wpmwc_create_local_convert_action_link', 10, 2 );
 

function wpmwc_generate_attachment_metadata( $metadata, $attachment_id ) {
    $mime = get_post_mime_type( $attachment_id );
    if( ! in_array( $mime, [ 'image/jpeg', 'image/png' ] ) ) return $metadata;

    $file = get_attached_file( $attachment_id );
    $info = pathinfo( $file );
    $webp = $info[ 'dirname' ] . '/' . $info[ 'filename' ] . '.webp';

    if( file_exists( $webp ) ) return $metadata;

    switch( strtolower( $info[ 'extension' ] ) ) {
        case 'jpg':
        case 'jpeg':
            $img = imagecreatefromjpeg( $file );
            break;
        case 'png':
            $img = imagecreatefrompng( $file );
            imagepalettetotruecolor( $img );
            imagealphablending( $img, true );
            imagesavealpha( $img, true );
            break;
        default:
            return $metadata;    
    }

    if( function_exists( 'imagewebp' ) ) {
        imagewebp( $img, $webp, 100 ); // Lossless compression
        imagedestroy( $img );
    }

    return $metadata;
}

add_filter( 'wp_generate_attachment_metadata', 'wpmwc_generate_attachment_metadata', 10, 2 );