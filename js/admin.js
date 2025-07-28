/**
 * Version: 1.4
 */
jQuery(document).ready( function( $ ) {
    $( "#mwc-start" ).on( 'click', function( e ){
        e.preventDefault();
        $( "#mwc-progress-log" ).html('');

        const image_quality = $( "#image_quality" );
        const action_mode   = $( "#conversion_mode" );
        const overwrite     = $( "#overwrite" ).is( "checked" ) ? true : false;

        if( $( image_quality ).val() === "" ) {
            alert( "Choose image quality to proceed" );
            return false;
        }

        if( $( action_mode ).val() === "" ) {
            alert( "Choose action for converted WebP files." );
            return false;
        }

        $( "#mwc-progress-wrapper" ).show();
        $( "#mwc-progress-status" ).text('Starting...');
        
        let index = 0;
        
        $.post( MWCNV.ajax_url, {
            action: 'media_webp_converter_get_images',
            nonce: MWCNV.nonce
        }, function( res ) {
            if( !res.success ) {
                console.log( 'Could not load images' );
                return;
            }

            const ids   = res.data;
            const total = ids.length;

            // Recursive
            function convertNext() {
                if( index >= total ) {
                    $( "#mwc-progress-status" ).text( '✅ Conversion process completed successfully.' );
                    return;
                }

                const current_id = ids[index];
                $( "#mwc-progress-status" ).text( `Converting ${index + 1} of ${total}` );

                $.post( MWCNV.ajax_url, {
                    action: 'media_webp_converter_convert_individual_image',
                    nonce: MWCNV.nonce,
                    id: current_id,
                    overwrite: overwrite,
                    image_quality: $( image_quality ).val(),
                    conversion_mode: $( action_mode ).val()
                }, function( res ) {
                    var logMessage = '';
                    if( res.status === "Success" ) {
                        logMessage = `✔ ID ${current_id}: 'Conversion successful`;
                    } else {
                        logMessage = `✖ ID ${current_id}: Error encountered. Conversion failed`;
                    }
                    // const logMessage = res.status === "Success" ? `✔ ID ${current_id}: ${res.data}` : `✖ ID ${current_id}: ${res.data || 'Error'}`;
                    // const logMessage = res.success ? `✔ ID ${current_id}: ${res.data}` : `✖ ID ${current_id}: ${res.data || 'Error'}`;
                    // $( "#mwc-progress-log" ).append( '<div>' + logMessage + '</div>' );

                    index++;
                    const percent = Math.round( ( index / total )  * 100 );
                    $( "#mwc-progress-inner" ).css( 'width', percent + '%' );
                    $( "#mwc-progress-status" ).text( `${percent}% complete` );

                    // Recusively call the function to convert the next available image
                    convertNext();
                } );
            }

            // Recusively call the function to convert the next available image
            convertNext();
        } );
    } );
} );