<?php
/**
 * Plugin Name: Media WebP Converter
 * Description: Converts JPEG, PNG and GIF media images to WebP formats in bulk. Choose from predefined image qualitiesand select whether or not to create new media attachments for the converted WebP files.
 * Version: 1.0.0
 * Author: Subrata Sarkar
 * Author URI: https://github.com/emfluenceindia
 * Public Github URI: https://github.com/emfluenceindia/media-webp-converter
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: media-webp-converter
 * Method Prefix: mwc_
 */

defined('ABSPATH') || exit;

if( is_admin() ) {
    require_once 'includes/mwc-converter.php';
}

/**
 * Enqueue admin scripts
 */
function mwc_enqueue_scripts() {
    // enqueue the script
    wp_enqueue_script( 
        'mwc-admin-script', // ID
        plugin_dir_url( __FILE__ ) . 'js/admin.js',  // script source
        ['jquery'], // dependency
        filemtime( plugin_dir_path( __FILE__ ) . 'js/admin.js' ), // version
        true //
    );

    //script localization
    wp_localize_script( 'mwc-admin-script', 'MWC', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'mwc_nonce' )
    ] );

    // enqueue CSS
    wp_enqueue_style( 
        'mwc-admin-style', 
        plugin_dir_url( __FILE__ ) . 'css/admin.css',
        [], 
        filemtime( plugin_dir_path( __FILE__ ) . 'css/admin.css' ) 
    );
}

add_action( 'admin_enqueue_scripts', 'mwc_enqueue_scripts' );

/**
 * Add a plugin management / settigns page in the admin
 */
function mwc_add_management_page() {
    add_options_page(
        __( 'Convert to WebP', 'media-webp-converter' ),    // Page title
        __( 'Media WebP Converter', 'media-webp-converter' ),    // Menu title
        'manage_options',                                      // Capability
        'mwc-convert-to-webp',                                 // Menu slug
        'mwc_render_settings_page'   // Callback function to render the page
    );
}

add_action( 'admin_menu', 'mwc_add_management_page' );

/**
 * Add a Settings link (plugin action link) under the plugin name on main plugin page
 * 
 * @param  array $links An array of plugin action links
 * @return array An updated array of plugin action links
 */
function mwc_add_plugin_settings_links( $links ) {
    // Build the URL
    $settings_url = admin_url( 'options-general.php?page=mwc-convert-to-webp' );

    // Create the HTML for the link
    $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'media-webp-converter' ) . '</a>';

    // Add the link to the beginning of the links array
    array_unshift( $links, $settings_link );

    // Return the modified $links array
    return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mwc_add_plugin_settings_links' );

/**
  * WordPress does not automatically remove non-standard files like .webp.
  * Hence we need to hook into the deletion process to manually remove these files
  * physically from the disk whenever an attachment media is deleted from the media library
 */

 function mwc_delete_file_from_disk( $attachment_id ) {
    $file_path = get_attached_file( $attachment_id );

    // Physical file absent
    if( ! $file_path || ! file_exists( $file_path ) ) {
        // return;
    }

    $info      = pathinfo( $file_path );
    $basedir   = $info[ 'dirname' ];
    $basename  = $info[ 'filename' ];
    $extension = $info[ 'extension' ];
    $webp_url  = wp_upload_dir()['url'] . '/' . $basename . '.webp'; // get the full virtual path

    if( "webp" === strtolower( $extension ) ) {
        /**
         * Check for the physical existence of the file
         * Get all attachment metadata and remove them from the disk
         * Remove the main webp file from the disk
         * Let's do it by calling a separate method initially
         */

        $webp_main = $basedir . '/' . $basename . '.' . $extension;
        mwc_jump_delete_webp_file( $attachment_id, $basedir, $webp_main );
    } else {
        // First we remove the main .webp version (same name having .webp extension)
        $webp_main = $basedir . '/' . $basename . '.webp'; // get the physical path on disk

        // Check if this .webp is a separate attachment already
        $webp_attachment_id = mwc_check_if_attachment_already_exists( $webp_url );

        if( $webp_attachment_id > 0 ) return; // This is an attachment. Do not delete

        mwc_jump_delete_webp_file( $attachment_id, $basedir, $webp_main ); 
    }
 }

 add_action( 'delete_attachment', 'mwc_delete_file_from_disk' );

 /**
  * Remove webp files from the disk on attachment removal
  */
  function mwc_jump_delete_webp_file( $attachment_id, $basedir, $webp_physical_path ) {
    $meta = wp_get_attachment_metadata( $attachment_id );

    if( ! empty( $meta[ 'sizes' ] ) ) {
        foreach( $meta[ 'sizes' ] as $size ) {
            $size_filename = $basedir . '/' . pathinfo( $size['file'], PATHINFO_FILENAME ) . '.webp';
            if( file_exists( $size_filename ) ) {
                wp_delete_file( $size_filename );
            }
        }
    }

    if( file_exists( $webp_physical_path ) ) {
        wp_delete_file( $webp_physical_path );
    }
  }

/**
 * Fetch and count image files by MIME types
 */
function mwc_count_images_by_mime_types() {
    global $wpdb;

    $mime_types = array(
        'image/jpeg'    => 'JPEG',
        'image/png'     => 'PNG',
        'image/gif'     => 'GIF',
    );

    $counts = array();

    foreach( $mime_types as $mime => $label ) {
        $attachments = get_posts(  array(
            'post_type'      => 'attachment',
            'post_mime_type' => $mime,
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids', // Fetching a single field is enough to get the count.
        ) );

        $counts[ $mime ] = count( $attachments );
    }

    return $counts;
}

/**
 * Display image counts by MIME types
 */
function mwc_display_image_counts_by_mime_type() {
    $counts = mwc_count_images_by_mime_types(); ?>
    <div class="wrap">
        <?php 
            $image_sizes = get_intermediate_image_sizes();
        ?>
        <h2><?php echo wp_kses_post( 'Image count by File types', 'media-webp-converter' ) ?></h2>
        <table class="mwc-table-default" style="width: 50%;margin: 0;"  cellspacing=0 cellpadding=0>
            <tr>
                <th class="left"><?php echo wp_kses_post( 'MIME Type', 'media-webp-converter' ) ?></th>
                <th><?php echo wp_kses_post( 'Image count', 'media-webp-converter' ) ?></th>
            </tr>
            <?php foreach( $counts as $type => $count ) { ?>
                <tr>
                    <td class="left">
                        <?php 
                            if( "image/jpeg" === strtolower( $type ) ) $type =  __( 'JPEG/JPG', 'media-webp-converter' );
                            if( "image/png" === strtolower( $type ) ) $type = __( 'PNG', 'media-webp-converter' );
                            if( "image/gif" === strtolower( $type ) ) $type = __( 'GIF (Non-animated only)', 'media-webp-converter' );
                            echo esc_html( $type );
                        ?>
                    </td>
                    <td><?php echo esc_html( number_format_i18n( $count ) ); ?> files</td>
                </tr>
            <?php } ?>
        </table>
    </div>
<?php }


/**
 * Management page callback funciton body
 */

function mwc_render_settings_page() { ?>
    <div class="wrap">
        <h1><?php echo wp_kses_post( 'Convert images in Media Library to WebP in Bulk', 'media-webp-converter' ); ?></h1>
        <hr />
        <p><?php echo wp_kses_post( 'Convert JPEG, PNG and GIF images in WebP format in bulk. You can choose <strong>Image Quality</strong> and/or <strong>Create New Attachment</strong> while converting.', 'media-webp-converter' ); ?></p>
        <?php mwc_display_image_counts_by_mime_type(); ?>
        <form method="post" class="mwc-form-default">
            <div style="margin-top: 10px;">
                <table class="mwc-action-table" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="left">
                            <?php wp_nonce_field( 'mwc_bulk_conversion_action', 'mwc_bulk_conversion_nonce' ); ?>
                        </td>
                        <td class="option">
                            <input type="checkbox" id="overwrite" name="overwrite" value="1" checked disabled />
                            <label for="overwrite">
                                <?php echo wp_kses_post( 'Overwrite existing WebP files', 'media-webp-converter' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td class="left">&nbsp;</td>
                        <td>
                            <span><?php echo wp_kses_post( 'When <strong>Overwrite</strong> and <strong>Convert & Create New Attachment</strong> options are selected at the same time, a New Attachment will only be created if it doesn\'t exist already.', 'media-webp-converter' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td class="left">
                            <?php echo wp_kses_post( 'Image quality ', 'media-webp-converter' ); ?>
                        </td>
                        <td class="option">
                            <select id="image_quality" name="image_quality">
                                <option value=""><?php echo esc_html__( '-- Select --', 'media-webp-converter' ); ?></option>    
                                <option value="100"> <?php echo esc_html__( 'Maximum', 'media-webp-converter' ); ?> </option>
                                <option value="75"><?php echo esc_html__( 'High', 'media-webp-converter' ) ?></option>
                                <option value="50"><?php echo esc_html__( 'Optimized (Recommended)', 'media-webp-converter' ) ?></option>
                                <option value="30"><?php echo esc_html__( 'Low', 'media-webp-converter' ) ?></option>
                            </select>
                            &nbsp; <span><?php echo wp_kses_post( '<strong>Maximum</strong>: WebP file becomes larger.', 'media-webp-converter' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td class="left">
                            <?php echo wp_kses_post( 'I want to', 'media-webp-converter' ); ?>
                        </td>
                        <td class="option">
                            <select id="conversion_mode" name="conversion_mode">
                                <option value=""><?php echo esc_html__( '-- Select --', 'media-webp-converter' ); ?></option>
                                <option value="none"><?php echo esc_html__( 'Convert Only', 'media-webp-converter' ) ?></option>
                                <option value="new"> <?php echo esc_html__( 'Convert & Create New Attachment', 'media-webp-converter' ); ?> </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="left">&nbsp;</td>
                        <td class="option">
                            <div>
                                <?php echo wp_kses_post( '<b>Convert Only</b> <span>converts the image to WebP and stores the newly created file in the same folder. They are not available on the WordPress Media Library; however, they reside inside the folder.</span>' , 'media-webp-converter' ); ?>
                            </div>
                            <hr />
                            <div>
                                <?php echo wp_kses_post( '<b>Convert & Create New Attachment</b> <span>does the same thing, but additionally creates a new media attachment at the same time, only if the same attachment does not already exist. The attachment will become available in the Media Library along with the source file.</span>' , 'media-webp-converter' ); ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="left">&nbsp;</td>
                        <td>
                            <div>
                                <button id="mwc-start" class="button button-primary">
                                    <?php echo esc_html__( 'Start Conversion', 'media-webp-converter' ); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="left">&nbsp;</td>
                        <td>
                            <div id="mwc-progress-wrapper" style="display: none;margin-top: 20px;">
                                <div id="mwc-progress-bar">
                                    <div id="mwc-progress-inner"></div>
                                </div>
                                <div id="mwc-progress-status"  style="margin-top: 15px;">0%</div>
                            </div>
                            <div id="mwc-progress-log"></div>
                        </td>
                    </tr>
                </table>
            </div>
        </form>
    </div>
<?php }

/**
 * AJAX: Get the image ID
 */
function mwc_get_images() {
    check_ajax_referer( 'mwc_nonce', 'nonce' );

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

add_action( 'wp_ajax_mwc_get_images', 'mwc_get_images' );

/**
 * AJAX: Convert individual image to WebP
 */
function mwc_convert_individual_image() {
    check_ajax_referer( 'mwc_nonce', 'nonce' );

    if( ! isset( $_POST[ 'id'] ) || empty( $_POST[ 'id' ] ) ) return;
    if( ! is_numeric( $_POST[ 'id'] ) ) return;

    $id             = intval( $_POST['id'] );
    $overwrite      = isset( $_POST[ 'overwrite' ] ) ? (bool)$_POST[ 'overwrite' ] : false;
    $quality        = isset( $_POST[ 'image_quality' ] ) ?  sanitize_text_field( wp_unslash( intval( $_POST[ 'image_quality' ] ) ) ) : 100;
    $action_mode    = isset( $_POST[ 'conversion_mode' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'conversion_mode' ] ) ) : 'none';
    $file           = get_attached_file( $id );
    $info           = pathinfo( $file );

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

    if( $mime_type === 'image/jpeg' ) {
        mwc_convert_jpeg_to_webp( $file, $thumb_files, $overwrite, $quality, $action_mode );
    } else if( $mime_type === 'image/png') {
        mwc_convert_png_to_webp( $file, $thumb_files, $overwrite, $quality, $action_mode );
    } else if( $mime_type === 'image/gif' ) {
        mwc_convert_gif_to_webp( $file, $thumb_files, $overwrite, $quality, $action_mode );
    }
}

add_action( 'wp_ajax_mwc_convert_individual_image', 'mwc_convert_individual_image' );

/**
 * Media Library: Convert indivdual image
 */
function mwc_create_local_convert_action_link( $actions, $post ) {
    $link_html = '<a href="javascript: void(0);" class="mwc-convert-single" data-id="' . $post->ID . '">' . __( 'Convert to WebP', 'media-webp-converter' ) . '</a>';
    if( in_array( get_post_mime_type( $post ), [ 'image/jpeg', 'image/png' ] ) ) {
        $actions[ 'convert_webp' ] = $link_html;
    }

    return $actions;
}

add_filter( 'media_row_action', 'mwc_create_local_convert_action_link', 10, 2 );
 

function mwc_generate_attachment_metadata( $metadata, $attachment_id ) {
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

add_filter( 'wp_generate_attachment_metadata', 'mwc_generate_attachment_metadata', 10, 2 );