jQuery(document).ready(function($) {
    // Nonce for AJAX.
    var nonce = '<?php echo wp_create_nonce( "aiht_nonce" ); ?>'; // Add in PHP.

    // Local storage for generations.
    var generations = localStorage.getItem('aiht_generations') ? parseInt(localStorage.getItem('aiht_generations')) : 0;
    var unlimited = localStorage.getItem('aiht_unlimited') === 'true';

    // Tabs.
    $('.aiht-tab').click(function() {
        $('.aiht-tab').removeClass('active');
        $(this).addClass('active');
        var gender = $(this).data('gender');
        $('.aiht-style-item').hide();
        $('.aiht-style-item[data-gender="' + gender + '"]').show();
    });
    $('.aiht-tab.active').click(); // Initial.

    // Hairstyle select.
    $('.aiht-style-item').click(function() {
        $('.aiht-style-item').removeClass('selected');
        $(this).addClass('selected');
        aiht_generate();
    });

    // Color change.
    $('#aiht-color').change(function() {
        aiht_generate();
    });

    // Upload change.
    $('#aiht-upload').change(function() {
        aiht_generate();
    });

    // Reset.
    $('.aiht-reset-button').click(function() {
        $('#aiht-upload').val('');
        $('#aiht-color').val('#000000');
        $('.aiht-gallery').empty();
        $('.aiht-book-button, .aiht-download-button').hide();
    });

    // Generate function.
    function aiht_generate() {
        var style_id = $('.aiht-style-item.selected').data('style-id');
        var color = $('#aiht-color').val();
        var files = $('#aiht-upload')[0].files;

        if ( ! style_id || files.length === 0 ) return;

        if ( generations >= aiht_data.free_limit && ! unlimited ) {
            // Trigger popup.
            // Assume Elementor popup trigger via ID.
            if ( typeof elementorPro !== 'undefined' ) {
                elementorPro.modules.popup.showPopup( { id: aiht_data.exploration_popup_id } );
            } else {
                alert( 'Please fill out the form to continue.' );
            }
            return;
        }

        $('.aiht-loading-spinner').show();

        var formData = new FormData();
        formData.append( 'action', 'aiht_generate' );
        formData.append( 'nonce', nonce );
        formData.append( 'style_id', style_id );
        formData.append( 'color', color );
        for ( var i = 0; i < files.length; i++ ) {
            formData.append( 'uploads[]', files[i] );
        }

        $.ajax({
            url: aiht_data.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function( resp ) {
                if ( resp.success ) {
                    $('.aiht-gallery').empty();
                    for ( var angle in resp.data ) {
                        $('.aiht-gallery').append( '<img src="' + resp.data[angle] + '" alt="' + angle + '">' );
                    }
                    $('.aiht-book-button, .aiht-download-button').show();
                    generations++;
                    localStorage.setItem( 'aiht_generations', generations );
                } else {
                    alert( resp.data || 'Error generating preview.' );
                }
                $('.aiht-loading-spinner').hide();
            },
            error: function() {
                alert( 'The AI service is temporarily unavailable—try again or check back soon.' );
                $('.aiht-loading-spinner').hide();
            }
        });
    }

    // Book now.
    $('.aiht-book-button').click(function() {
        // Trigger popup.
        if ( typeof elementorPro !== 'undefined' ) {
            elementorPro.modules.popup.showPopup( { id: aiht_data.book_popup_id } );
        } else {
            alert( 'Book now form.' );
        }
    });

    // Download.
    $('.aiht-download-button').click(function() {
        // Download gallery images.
        $('.aiht-gallery img').each(function() {
            var a = document.createElement('a');
            a.href = $(this).attr('src');
            a.download = 'preview.png';
            a.click();
        });
    });

    // Assume webhook sets unlimited after exploration form, but since client, perhaps listen for event or reload.
    // For simplicity, set unlimited manually in testing.
});