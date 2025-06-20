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
function wpmwc_convert_jpeg_to_webp( $thumb_files, $overwrite, $quality, $action_mode ) {
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

    echo $output;
}

/**
 * Iterates the array, takes individual JPEG file path and conver them to WebP
 * @param array $thumb_files. The string array of file paths.
 * @param bool $overwrite. Decides whether or not to overwrite any existing WebP file
 */
function wpmwc_convert_gif_to_webp( $thumb_files, $overwrite, $quality, $action_mode ) {
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
                $result = imagewebp( $img, $webp_save_path, $quality );
                $converted++;
            } catch ( Exception $ex ) {
                $failed++;
                $ex->getMessage();
            }
        }

        imagedestroy( $img );
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

    $output = json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

    echo $output;
}

/**
 * Iterates the array, takes individual JPEG file path and conver them to WebP
 * @param array $thumb_files. The string array of file paths.
 * @param bool $overwrite. Decides whether or not to overwrite any existing WebP file
 */
function wpmwc_convert_png_to_webp( $thumb_files, $overwrite, $quality, $action_mode ) {
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

    $summary = array(
        'status'  => 'Success',
        'message' => 'Conversion process completed',
        'summary' => array(
            'converted' => $converted,
            'skipped'   => $skipped,
            'failed'    => $failed
        ),
    );

    $output = json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

    echo $output;
}

/**
 * Creates a new attachment
 * @param string $file_path. The full path of the WebP file just created
 * @param bool $replace. Replace current attachment with new WebP if true. Leaves otherwise. Default = false
 */
function wpmwc_create_new_attachment( $file_path, $replace = false ) {
    $new_attachment = array(
        'guid'           => $file_path,
        'post_mime_type' => 'image/webp',
        'post_title'     => sanitize_file_name( basename( $file_path ) ),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    // Insert into the database
    $new_attachment_id = wp_insert_attachment( $new_attachment, $file_path, 0 ); // 0 = Unattached

    // Generate metadata
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $new_attachment_data = wp_generate_attachment_metadata( $new_attachment_id, $file_path );
    wp_update_attachment_metadata( $new_attachment_id, $new_attachment_data );

    if( $replace ) {
        // The replace routine goes here...
    }
}