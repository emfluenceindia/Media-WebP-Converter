<?php
/**
 * Include file
 * File name: wpmwc-converter.php
 * File path: wp-media-webp-converter/includes
 * Version: 1.1
 * Included in: wp-media-webp-converter/wp-media-webp-converter.php
 * Purpose: Separate file to store converter functions to keep the main plugin file clean and concise
 */

/**
 * Iterates the array, takes individual JPEG file path and conver them to WebP
 * @param array $thumb_files. The string array of file paths.
 * @param bool $overwrite. Decides whether or not to overwrite any existing WebP file
 */
function wpmwc_convert_jpeg_to_webp( $source_file_path, $thumb_files, $overwrite, $quality, $action_mode ) {
    if( ! function_exists( 'imagewebp' ) ) {
        wp_send_json_error( 'WebP is not supported' );
    }

    $converted = 0; $skipped = 0; $failed = 0;

    foreach( $thumb_files as $thumb ) {
        $path_info      = pathinfo( $thumb );
        $file_name      = $path_info[ 'filename' ];
        $webp_save_path = $path_info[ 'dirname' ] . '/' . $file_name . '.webp';

        if( ! $overwrite && file_exists( $webp_save_path ) ) {
            // wp_send_json_success( 'Already exists. Skipping...' );
            $skipped++;
        } else {
            try {
                $img = imagecreatefromjpeg( $thumb );
                $result = imagewebp( $img, $webp_save_path, $quality );
                $converted++;
            } catch ( Exception $ex ) {
                $failed++;
                $ex->getMessage();
            }
        }

        imagedestroy( $img );
    }

    if( $action_mode === 'new' ) { // Create new attachment and metadata for newly created WebP
        wpmwc_create_new_attachment( $source_file_path );
    }

    $summary = array(
        'status'  => 'Success',
        'message' => 'Conversion process completed',
        'summary' => array(
            'converted' => $converted,
            'skipped'   => $skipped,
            'failed'    => $failed
        ),
    );

    $output = json_encode( $summary );
}

/**
 * Iterates the array, takes individual JPEG file path and conver them to WebP
 * @param array $thumb_files. The string array of file paths.
 * @param bool $overwrite. Decides whether or not to overwrite any existing WebP file
 */
function wpmwc_convert_png_to_webp( $source_file_path, $thumb_files, $overwrite, $quality, $action_mode ) {
    if( ! function_exists('imagewebp' ) ) {
        wp_send_json_error( 'WebP is not supported' );
    }

    $converted = 0; $skipped = 0; $failed = 0;

    foreach( $thumb_files as $thumb ) {
        $path_info      = pathinfo( $thumb );
        $file_name      = $path_info[ 'filename' ];
        $webp_save_path = $path_info[ 'dirname' ] . '/' . $file_name . '.webp';

        if( ! $overwrite && file_exists( $webp_save_path ) ) {
            // wp_send_json_success( 'Already exists. Skipping...' );
            $skipped++;
        } else {
            try {
                $img = imagecreatefrompng( $thumb );
                imagepalettetotruecolor( $img );
                imagealphablending( $img, true );
                imagesavealpha( $img, true );

                $result = imagewebp( $img, $webp_save_path, $quality );
                $converted++;
            } catch ( Exception $ex ) {
                $failed++;
                $ex->getMessage();
            }
        }

        imagedestroy( $img );
    }

    if( $action_mode === 'new' ) { // Create new attachment and metadata for newly created WebP
        wpmwc_create_new_attachment( $source_file_path );
    }

    $summary = array(
        'status'  => 'Success',
        'message' => 'Conversion process completed',
        'summary' => array(
            'converted' => $converted,
            'skipped'   => $skipped,
            'failed'    => $failed
        ),
    );

    // $output = json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    $output = json_encode( $summary );
}

/**
 * Iterates the array, takes individual JPEG file path and conver them to WebP
 * @param array $thumb_files. The string array of file paths.
 * @param bool $overwrite. Decides whether or not to overwrite any existing WebP file
 */
function wpmwc_convert_gif_to_webp( $source_file_path, $thumb_files, $overwrite, $quality, $action_mode ) {
    if( ! function_exists('imagewebp' ) ) {
        wp_send_json_error( 'WebP is not supported' );
    }

    $converted = 0; $skipped = 0; $failed = 0;

    foreach( $thumb_files as $thumb ) {
        $path_info      = pathinfo( $thumb );
        $file_name      = $path_info[ 'filename' ];
        $webp_save_path = $path_info[ 'dirname' ] . '/' . $file_name . '.webp';

        if( ! $overwrite && file_exists( $webp_save_path ) ) {
            // wp_send_json_success( 'Already exists. Skipping...' );
            $skipped++;
        } else {
            try {
                $img = imagecreatefromgif( $thumb );
                imagepalettetotruecolor( $img );
                imagealphablending( $img, true );
                imagesavealpha( $img, true );

                $result = imagewebp( $img, $webp_save_path, $quality );
                $converted++;
            } catch ( Exception $ex ) {
                $failed++;
                $ex->getMessage();
            }
        }

        imagedestroy( $img );
    }

    if( $action_mode === 'new' ) { // Create new attachment and metadata for newly created WebP
        wpmwc_create_new_attachment( $source_file_path );
    }

    $summary = array(
        'status'  => 'Success',
        'message' => 'Conversion process completed',
        'summary' => array(
            'converted' => $converted,
            'skipped'   => $skipped,
            'failed'    => $failed
        ),
    );

    $output = json_encode( $summary );
}

/**
 * Creates a new attachment
 * @param string $source_file_path. The full path of the original attachment file
 */
function wpmwc_create_new_attachment( $source_file_path ) {
    $path_info      = pathinfo( $source_file_path );
    $webp_filename  = $path_info[ 'filename' ] . '.webp';
    $webp_url       = wp_upload_dir()['url'] . '/' . $webp_filename; // URL
    $webp_save_path = $path_info[ 'dirname' ] . '/' . $webp_filename; // Absolute path

    $attachmet_arg  = array(
        'guid'           => $webp_url,
        'post_mime_type' => 'image/webp',
        'post_title'     => sanitize_file_name( $path_info[ 'filename' ] ),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    $existing_attachment_id = wpmwc_check_if_attachment_already_exists( $webp_url );
    
    /**
     * Create a new attachment only if $existing_attachment_id = false.
     * Skip otherwise.
     */
    if( $existing_attachment_id > 0 ) return;

    // Insert into the database
    //$new_attachment_id = wp_insert_attachment( $attachmet_arg, $webp_url, 0 ); // 0 = Unattached. Replace with $post_id for association
    $new_attachment_id = wp_insert_attachment( $attachmet_arg, $webp_save_path, 0 ); // 0 = Unattached. Replace with $post_id for association

    // Generate metadata
    require_once ABSPATH . 'wp-admin/includes/image.php';
    //$new_attachment_data = wp_generate_attachment_metadata( $new_attachment_id, $webp_url );
    $new_attachment_data = wp_generate_attachment_metadata( $new_attachment_id, $webp_save_path );
    wp_update_attachment_metadata( $new_attachment_id, $new_attachment_data );
}

/**
 * Check whether there is already an attachment existing.
 * Create a new attachment if not, skip otherwise
 * 
 * Regex pattern ('compare' => 'REGEXP') gives more flexibility
 * over 'compare' => 'LIKE' or 'compare' => '=' while comparing
 * 
 * Other alternatives are:
 * 'value' => $filename, 'compare' => 'LIKE'
 * 'value' => $filename, 'compare' => '='
 * 
 * @param string $filename. The name of the WebP file to check.
 * @return int|bool. Returns attachment_id if exists, false otherwise.
 */
function wpmwc_check_if_attachment_already_exists( $filename ) {
    $filename = basename( $filename );

    $att_args = array(
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'numberposts' => 1,
        'meta_query'  => array(
            array(
                'key'     => '_wp_attached_file',
                'value'   =>  '^.*/' . $filename . '$',
                'compare' => 'REGEXP'
            )
        ),
    );

    $attachments = get_posts( $att_args );

    if( ! empty( $attachments ) ) {
        return $attachments[0]->ID;
    }

    return false;
}