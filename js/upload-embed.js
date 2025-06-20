/**
 * Embed local script only when media upload page (upload.php) is loaded.
 * Hook: admin_footer-upload.php
 * 
 * admin_footer- is a dynamic WordPress action hook that fires in the footer of the admin area.
 * upload.php is the specific admin page where this action runs.
 * The full hook 'admin_footer-upload.php' ensures the function is only executed on the Media Library page, not on other admin pages.
 */


jQuery( document ).ready( function( $ ) {
    $(document).on( 'click', 'wpmwc-convert-single', function( e ) {
        e.preventDefault();
        const el = $( this );
        const id = el.data( 'id' );
        el.text( 'Converting...' );

        $.post( WPMC.ajaxUrl, {
            action: 'wpmwc_convert_individual_image',
            nonce: WPMC.nonce,
            id: id,
            overwrite: false,
            image_quality: 80,
            conversion_mode: 'new'
        }, function ( res ) {
            if( res.success ) {
                el.text( '' );
            } else {
                el.text( '' );
                alert( res.data );
            }
        } );
    } );
} );

// add_action( 'admin_footer-upload.php', 'wpmwc_upload_script_embed' );