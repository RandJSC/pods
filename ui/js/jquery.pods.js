(function ( $ ) {
    var pods_changed = false;
    var methods = {
        validate : function () {
            var $form = $( 'form.pods-submittable' );
            $form.on( 'change keyup', 'input.pods-validate.pods-validate-required, select.pods-validate.pods-validate-required, textarea.pods-validate.pods-validate-required', function () {
                var $el = $( this );

                $el.removeClass( 'pods-validate-error' );

                if ( !$el.is( ':visible' ) )
                    return;

                if ( 0 < $el.parent().find( 'label' ).length )
                    var label = $el.parent().find( 'label' ).html().trim();
                else
                    var label = $el.prop( 'name' ).trim().replace( '_', ' ' );

                if ( $el.is( 'input[type=checkbox]' ) && !$el.is( ':checked' ) ) {
                    if ( 0 == $el.parent().find( '.pods-validate-error-message' ).length )
                        $el.parent().append( '<div class="pods-validate-error-message">' + label + ' is required.</div>' );

                    $el.addClass( 'pods-validate-error' );
                }
                else if ( '' == $el.val() || 0 == $el.val() ) {
                    if ( 0 == $el.parent().find( '.pods-validate-error-message' ).length )
                        $el.parent().append( '<div class="pods-validate-error-message">' + label + ' is required.</div>' );

                    $el.addClass( 'pods-validate-error' );
                }
                else {
                    $el.parent().find( '.pods-validate-error-message' ).remove();
                    $el.removeClass( 'pods-validate-error' );
                }
            } );
        },
        submit : function () {
            var $submitbutton;

            // Handle submit of form and translate to AJAX
            $( 'form.pods-submittable' ).on( 'submit', function ( e ) {
                var $submittable = $( this );

                pods_changed = false;

                e.preventDefault();

                pods_ajaxurl = $submittable.attr( 'action' );
                pods_ajaxurl = pods_ajaxurl.replace( /\?nojs\=1/, '?pods_ajax=1' );

                if ( 'undefined' != typeof ajaxurl && ('' == pods_ajaxurl || '?pods_ajax=1' == pods_ajaxurl || document.location.href == pods_ajaxurl || document.location.href.replace( /\?nojs\=1/, '?pods_ajax=1' ) == pods_ajaxurl) )
                    pods_ajaxurl = ajaxurl + '?pods_ajax=1';

                postdata = {};
                field_data = {};

                var valid_form = true;

                var field_id = 0,
                    field_index = 0;

                $submittable.find( '.pods-submittable-fields' ).find( 'input, select, textarea' ).each( function () {
                    var $el = $( this );
                    var val = $el.val();

                    if ( '' != $el.prop( 'name' ) ) {
                        if ( $el.is( 'input[type=checkbox]' ) && 1 == val && !$el.is( ':checked' ) )
                            val = 0;
                        else if ( $el.is( 'input[type=radio]' ) && !$el.is( ':checked' ) )
                            val = '';

                        if ( $el.is( ':visible' ) && $el.hasClass( 'pods-validate pods-validate-required' ) && ('' == $el.val() || 0 == $el.val()) ) {
                            if ( 0 == $el.parent().find( '.pods-validate-error-message' ).length )
                                $el.parent().append( '<div class="pods-validate-error-message">' + $el.parent().find( 'label' ).html().trim() + ' is required.</div>' );

                            if ( false !== valid_form )
                                $el.focus();

                            $el.addClass( 'pods-validate-error' );
                            $el.focus();
                            valid_form = false;
                        }
                        else {
                            $el.parent().find( '.pods-validate-error-message' ).remove();
                            $el.removeClass( 'pods-validate-error' );
                        }

                        field_name = $el.prop( 'name' );

                        if ( 0 == field_name.indexOf( 'field_data' ) ) {
                            var field_array = field_name.match( /\[(\w*|)\]/gi ),
                                field_name = '';

                            for ( var i in field_array ) {
                                the_field = field_array[ i ].replace( '[', '' ).replace( ']', '' );

                                if ( 0 == i ) {
                                    if ( field_index != the_field )
                                        field_id++;

                                    field_index = the_field;

                                    if ( 'undefined' == typeof field_data[ field_id ] )
                                        field_data[ field_id ] = {};
                                }
                                else if ( 1 == i ) {
                                    field_name = the_field;

                                    if ( 2 == field_array.length )
                                        field_data[ field_id ][ field_name ] = val;
                                }
                                else if ( 2 == i ) {
                                    the_field = parseInt( the_field );

                                    if ( 'NaN' == the_field )
                                        field_data[ field_id ][ field_name ] = val;
                                    else {
                                        if ( 'undefined' == typeof field_data[ field_id ][ field_name ] )
                                            field_data[ field_id ][ field_name ] = {};

                                        while ( 'undefined' != typeof( field_data[ field_id ][ field_name ][ the_field ] ) ) {
                                            the_field++;
                                        }

                                        field_data[ field_id ][ field_name ][ the_field ] = val;
                                    }
                                }
                            }
                        }
                        else
                            postdata[ field_name ] = val;
                    }
                } );

                if ( false === valid_form )
                    return false;

                pods_ajaxurl = pods_ajaxurl + '&action=' + postdata.action;

                // $_POST doesn't like a LOT of items, JSON encode it as one string
                if ( !$.isEmptyObject( field_data ) )
                    postdata[ 'field_data' ] = $.toJSON( field_data );

                $submitbutton = $submittable.find( 'input[type=submit], button[type=submit]' );

                $.ajax( {
                    type : 'POST',
                    dataType : 'html',
                    url : pods_ajaxurl,
                    cache : false,
                    data : postdata,
                    success : function ( d ) {
                        if ( -1 == d.indexOf( '<e>' ) && -1 != d ) {
                            if ( 'undefined' != typeof pods_admin_submit_callback )
                                pods_admin_submit_callback( parseInt( d ) );
                            else if ( 'undefined' != typeof $submittable.data( 'location' ) )
                                document.location.href = $submittable.data( 'location' ).replace( 'X_ID_X', parseInt( d ) );
                            else
                                document.location.reload( true );
                        }
                        else if ( 'undefined' != typeof $submittable.data( 'error-location' ) )
                            document.location.href = $submittable.data( 'error-location' );
                        else {
                            var err_msg = d.replace( '<e>', '' ).replace( '</e>', '' );
                            if ( 'undefined' != typeof pods_admin_submit_error_callback )
                                pods_admin_submit_error_callback( err_msg );

                            $submitbutton.css( 'cursor', 'pointer' );
                            $submitbutton.prop( 'disabled', false );
                            $submitbutton.parent().find( '.waiting' ).fadeOut();

                            alert( 'Error: ' + err_msg );
                            console.log( err_msg );
                        }
                    },
                    error : function () {
                        $submitbutton.css( 'cursor', 'pointer' );
                        $submitbutton.prop( 'disabled', false );
                        $submitbutton.parent().find( '.waiting' ).fadeOut();

                        alert( 'Unable to process request, please try again.' );
                    }
                } );

            } );

            // Handle submit via link and translate to AJAX
            $( 'form.pods-submittable a.pods-submit' ).on( 'click', function ( e ) {
                var $submitbutton = $( this );

                e.preventDefault();

                pods_ajaxurl = $submitbutton.data( 'ajaxurl' );

                if ( 'undefined' != typeof pods_ajaxurl )
                    pods_ajaxurl = pods_ajaxurl.replace( /\?nojs\=1/, '?pods_ajax=1' );
                else if ( 'undefined' != typeof ajaxurl && ('undefined' == typeof pods_ajaxurl || '' == pods_ajaxurl || '?pods_ajax=1' == pods_ajaxurl || document.location.href == pods_ajaxurl || document.location.href.replace( /\?nojs\=1/, '?pods_ajax=1' ) == pods_ajaxurl) )
                    pods_ajaxurl = ajaxurl + '?pods_ajax=1';

                var postdata = $submitbutton.data();

                if ( 'undefined' != typeof $submitbutton.data( 'confirm' ) && !confirm( $submitbutton.data( 'confirm' ) ) )
                    return false;

                pods_changed = false;

                pods_ajaxurl = pods_ajaxurl + '&action=' + postdata.action;

                $.ajax( {
                    type : 'POST',
                    dataType : 'html',
                    url : pods_ajaxurl,
                    cache : false,
                    data : postdata,
                    success : function ( d ) {
                        if ( -1 == d.indexOf( '<e>' ) && -1 != d ) {
                            if ( 'undefined' != typeof pods_admin_submit_callback )
                                pods_admin_submit_callback( parseInt( d ) );
                            else if ( 'undefined' != typeof $submitbutton.data( 'location' ) )
                                document.location.href = $submitbutton.data( 'location' ).replace( 'X_ID_X', parseInt( d ) );
                            else
                                document.location.reload( true );
                        }
                        else if ( 'undefined' != typeof $submitbutton.data( 'error-location' ) )
                            document.location.href = $submitbutton.data( 'error-location' );
                        else {
                            var err_msg = d.replace( '<e>', '' ).replace( '</e>', '' );
                            if ( 'undefined' != typeof pods_admin_submit_error_callback )
                                pods_admin_submit_error_callback( err_msg );

                            $submitbutton.css( 'cursor', 'pointer' );
                            $submitbutton.prop( 'disabled', false );
                            $submitbutton.parent().find( '.waiting' ).fadeOut();

                            alert( 'Error: ' + err_msg );
                            console.log( err_msg );
                        }
                    },
                    error : function () {
                        $submitbutton.css( 'cursor', 'pointer' );
                        $submitbutton.prop( 'disabled', false );
                        $submitbutton.parent().find( '.waiting' ).fadeOut();

                        alert( 'Unable to process request, please try again.' );
                    }
                } );
            } );

            // Handle submit button and show waiting image
            $( 'form.pods-submittable' ).on( 'click', 'input[type=submit], button[type=submit]', function ( e ) {
                pods_changed = false;

                e.preventDefault();

                var $submitbutton = $( this );
                $submitbutton.css( 'cursor', 'default' );
                $submitbutton.prop( 'disabled', true );
                $submitbutton.parent().find( '.waiting' ).fadeIn();

                $( 'form.pods-submittable' ).trigger( 'submit' );
            } );
        },
        sluggable : function () {
            // Setup selector
            var $sluggable = $( '.pods-sluggable' ),
                last_slug = null;

            if ( 0 !== $sluggable.length ) {
                // Hold onto slug in-case change is cancelled
                if ( $sluggable.find( '.pods-slug-edit input[type=text]' )[ 0 ] ) {
                    last_slug = $sluggable.find( '.pods-slug-edit input[type=text]' ).val();

                    last_slug = last_slug.replace( /<(?:.)*?>/g, '' ).replace( /([^0-9a-zA-Z ])/g, '' );

                    $( '.pods-slugged-lower:not(.pods-slugged[data-sluggable])' ).html( last_slug.toLowerCase() );
                    $( '.pods-slugged:not(.pods-slugged[data-sluggable])' ).html( last_slug.charAt( 0 ).toUpperCase() + last_slug.slice( 1 ) );
                }

                // Handle click to edit
                $sluggable.on( 'click', '.pods-slug em, .pods-slug input[type=button]', function () {
                    $( this ).css( 'cursor', 'default' );
                    $( this ).prop( 'disabled', true );

                    $( this ).closest( '.pods-sluggable' ).find( '.pods-slug, .pods-slug-edit' ).toggle();
                    $( this ).closest( '.pods-sluggable' ).find( '.pods-slug-edit input[type=text]' ).focus();

                    $( this ).css( 'cursor', 'pointer' );
                    $( this ).prop( 'disabled', false );
                } );

                // Handle slug save
                $sluggable.on( 'click', '.pods-slug-edit input[type=button]', function () {
                    $( this ).css( 'cursor', 'default' );
                    $( this ).prop( 'disabled', true );

                    last_slug = $( this ).parent().find( 'input[type=text]' ).val();

                    last_slug = last_slug.replace( /<(?:.)*?>/g, '' ).replace( /([^0-9a-zA-Z ])/g, '' );

                    $( this ).closest( '.pods-sluggable' ).find( '.pods-slug em' ).html( last_slug );
                    $( '.pods-slugged-lower:not(.pods-slugged[data-sluggable])' ).html( last_slug.toLowerCase() );
                    $( ".pods-slugged:not(.pods-slugged[data-sluggable])" ).html( last_slug.charAt( 0 ).toUpperCase() + last_slug.slice( 1 ) );
                    $( this ).closest( '.pods-sluggable' ).find( '.pods-slug, .pods-slug-edit' ).toggle();

                    $( this ).css( 'cursor', 'pointer' );
                    $( this ).prop( 'disabled', false );
                } );

                // Handle cancel slug edit
                $sluggable.on( 'click', '.pods-slug-edit a.cancel', function ( e ) {
                    $( this ).css( 'cursor', 'default' );
                    $( this ).prop( 'disabled', true );

                    $( this ).parent().find( 'input[type=text]' ).val( last_slug );
                    $( this ).closest( '.pods-sluggable' ).find( '.pods-slug, .pods-slug-edit' ).toggle();

                    $( this ).css( 'cursor', 'pointer' );
                    $( this ).prop( 'disabled', false );

                    e.preventDefault();
                } );
                $sluggable.find( '.pods-slug-edit' ).hide();
            }

            var sluggables = [];

            $( '.pods-slugged[data-sluggable]' ).each( function () {
                if ( -1 == sluggables.indexOf( $( this ).data( 'sluggable' ) ) )
                    sluggables.push( $( this ).data( 'sluggable' ) );
            } );

            for ( var i in sluggables ) {
                var sluggable = sluggables[ i ];

                $slug = $( 'input[name="' + sluggable + '"]' );

                var slug = $( '.pods-sluggable .pods-slug-edit input[type=text]' ).val();

                if ( $slug[ 0 ] && 0 < $slug.val().length )
                    slug = $slug.val();

                slug = slug.replace( /<(?:.)*?>/g, '' ).replace( /([^0-9a-zA-Z ])/g, '' );

                $( '.pods-slugged-lower[data-sluggable="' + sluggable + '"]' ).html( slug.toLowerCase() );
                $( '.pods-slugged[data-sluggable="' + sluggable + '"]' ).html( slug.charAt( 0 ).toUpperCase() + slug.slice( 1 ) );

                if ( $slug[ 0 ] ) {
                    $slug.on( 'change', function () {
                        var slug = $( '.pods-sluggable .pods-slug-edit input[type=text]' ).val();

                        if ( 0 < $( this ).val().length )
                            slug = $( this ).val();

                        slug = slug.replace( /<(?:.)*?>/g, '' ).replace( /([^0-9a-zA-Z ])/g, '' );

                        $( '.pods-slugged-lower[data-sluggable="' + $( this ).prop( 'name' ) + '"]' ).html( slug.toLowerCase() );
                        $( '.pods-slugged[data-sluggable="' + $( this ).prop( 'name' ) + '"]' ).html( slug.charAt( 0 ).toUpperCase() + slug.slice( 1 ) );
                    } );
                }
            }
        },
        tabbed : function () {
            $( document ).on( 'click', '.pods-tabs .pods-tab a', function ( e ) {
                $( this ).css( 'cursor', 'default' );
                $( this ).prop( 'disabled', true );

                var tab_class = '.pods-tabbed';

                if ( 'undefined' != typeof $( this ).closest( '.pods-tabs' ).data( 'tabbed' ) )
                    tab_class = $( this ).closest( '.pods-tabs' ).data( 'tabbed' );

                var $tabbed = $( this ).closest( tab_class );
                var tab_hash = this.hash;

                if ( $tabbed.find( '.pods-tabs .pods-tab a[data-tabs]' )[ 0 ] ) {
                    $tabbed.find( '.pods-tabs .pods-tab a[data-tabs]' ).each( function () {
                        var tabs = $( this ).data( 'tabs' ),
                            this_tab_hash = this.hash;

                        if ( tab_hash != this_tab_hash )
                            $tabbed.find( tabs ).hide();
                        else
                            $tabbed.find( tabs ).show();
                    } );
                }
                else {
                    $tabbed.find( '.pods-tab-group .pods-tab' ).not( tab_hash ).slideUp( 'fast', function () {
                        $tabbed.find( '.pods-tab-group .pods-tab' ).filter( tab_hash ).slideDown( 'fast' );
                    } );
                }

                $tabbed.find( '.pods-tabs .pods-tab a' ).removeClass( 'selected' );

                $( this ).addClass( 'selected' );

                $( this ).css( 'cursor', 'pointer' );
                $( this ).prop( 'disabled', false );

                e.preventDefault();
            } );

            $( '.pods-tabbed' ).find( 'ul.pods-tabs .pods-tab:first a' ).addClass( 'selected' );
            $( '.pods-tabbed' ).find( '.pods-tab-group .pods-tab:first' ).show();
        },
        wizard : function () {
            var methods = {
                setFinished : function () {
                    $( '#pods-wizard-next' ).text( $( '#pods-wizard-next' ).data('finished' ) );
                },
                setProgress : function () {
                    $( '#pods-wizard-next' ).text( $( '#pods-wizard-next' ).data( 'next ' ) );
                },
                stepForward : function () {
                    // Show action bar for second panel if hidden
                    $( 'div.pods-wizard-hide-first' )
                        .removeClass( 'pods-wizard-hide-first' )
                        // Remember that first panel should hide action bar
                        .data( 'hide', 1 );

                    // Step toolbar menu state forwards
                    $( 'li.pods-wizard-menu-current' )
                        .removeClass( 'pods-wizard-menu-current' )
                        .addClass( 'pods-wizard-menu-complete' )
                        .next( 'li' )
                        .addClass( 'pods-wizard-menu-current' );

                    // Get current step #
                    var step = false;

                    if ( $( 'li.pods-wizard-menu-current[data-step]' )[ 0 ] )
                        step = $( 'li.pods-wizard-menu-current' ).data( 'step' );

                    // Show start over button
                    $( '#pods-wizard-start' ).show();

                    // Allow for override
                    var check = true;

                    // Check if last step
                    if ( $( 'div.pods-wizard-panel:visible' ).next( 'div.pods-wizard-panel' ).length ) {
                        // Show next panel
                        $( 'div.pods-wizard-panel:visible' )
                            .hide()
                            .next()
                            .show();

                        // Allow for override
                        if ( 'undefined' != typeof pods_admin_wizard_callback )
                            check = pods_admin_wizard_callback( step );

                        if ( false === check )
                            return check;
                    }
                    else if ( $( '#pods-wizard-box' ).closest( 'form' )[ 0 ] ) {
                        $( '#pods-wizard-next' ).css( 'cursor', 'pointer' );
                        $( '#pods-wizard-next' ).attr( 'disabled', true );
                        $( '#pods-wizard-next' ).text( $( '#pods-wizard-next' ).data( 'processing' ) );

                        $( '#pods-wizard-box' ).closest( 'form' ).submit();
                    }
                    else {
                        // Allow for override
                        if ( 'undefined' != typeof pods_admin_wizard_callback )
                            check = pods_admin_wizard_callback( step );

                        if ( false === check )
                            return check;

                        methods.setFinished();
                    }
                },
                startOver : function () {
                    // Reset next button text
                    methods.setProgress();

                    // If first panel and action bar is supposed to be hidden, hide it.
                    var $box = $( '#pods-wizard-box' );
                    if ( $box.data( 'hide' ) )
                        $box.addClass( 'pods-wizard-hide-first' );

                    // Revert to first current menu item
                    $( '#pods-wizard-heading ul li' )
                        .removeClass()
                        .first()
                        .addClass( 'pods-wizard-menu-current' );

                    // Revert to first panel
                    $( 'div.pods-wizard-panel' )
                        .hide()
                        .first()
                        .show();

                    // Hide start over button
                    $( '.pods-wizard-option-selected' ).removeClass();
                    $( '#pods-wizard-start' ).hide();
                    $( 'div.pods-wizard-option-cont' ).hide();
                    $( '#pods-wizard-choices' ).fadeIn( 'fast' );
                }
            }

            // Next button event binding
            $( '#pods-wizard-next' ).on( 'click', function ( e ) {
                e.preventDefault();

                methods.stepForward();
            } );

            // Start over button event binding
            $( '#pods-wizard-start' )
                .hide()
                .on( 'click', function ( e ) {
                         e.preventDefault();
                         methods.startOver();
                     } );

            // Upgrade choice button event binding
            $( '.pods-choice-button' ).on( 'click', function ( e ) {
                e.preventDefault();

                var target = $( this ).attr( 'href' );
                $( '#pods-wizard-choices' ).slideUp( 'fast' );
                $( target ).slideDown( 'fast' );
            } );

            // Create/extend option event binding
            $( '.pods-wizard-option a' ).on( 'click', function ( e ) {
                e.preventDefault();

                $( '.pods-wizard-option-content' ).hide();

                var target = $( this ).attr( 'href' );

                $( target ).show();

                if ( 'undefined' != typeof pods_admin_option_select_callback )
                    pods_admin_option_select_callback( $( this ) );

                methods.stepForward();
            } );

            // Initial step panel setup
            $( '.pods-wizard .pods-wizard-step' ).hide();
            $( '.pods-wizard .pods-wizard-step:first' ).show();
        },
        dependency : function () {
            // Hide all dependents
            $( '.pods-dependency .pods-depends-on' ).hide();

            // Handle dependent toggle
            $( document ).on( 'change', '.pods-dependency .pods-dependent-toggle', function ( e ) {
                var $el = $( this );
                var $current = $el.closest( '.pods-dependency' );
                var $field = $el;

                var dependent_flag = '.pods-depends-on-' + $el.data( 'name-clean' );
                var dependent_specific = dependent_flag + '-' + $el.val();

                $current.find( dependent_flag ).each( function () {
                    var $el = $( this );

                    if ( $el.parent().is( ':visible' ) ) {
                        if ( $field.is( 'input[type=checkbox]' ) && $field.is( ':checked' ) ) {
                            $el.slideDown().addClass( 'pods-dependent-visible' );
                            $el.find( '.pods-dependency .pods-depends-on' ).hide();
                            $el.find( '.pods-dependency .pods-excludes-on' ).hide();

                            $el.find( '.pods-dependency .pods-dependent-toggle' ).each( function () {
                                $( this ).trigger( 'change' );
                            } );
                        }
                        else if ( $el.is( dependent_specific ) ) {
                            $el.slideDown().addClass( 'pods-dependent-visible' );
                            $el.find( '.pods-dependency .pods-depends-on' ).hide();
                            $el.find( '.pods-dependency .pods-excludes-on' ).hide();

                            $el.find( '.pods-dependency .pods-dependent-toggle' ).each( function () {
                                $( this ).trigger( 'change' );
                            } );
                        }
                        else
                            $el.slideUp().removeClass( 'pods-dependent-visible' );
                    }
                    else {
                        if ( $field.is( 'input[type=checkbox]' ) && $field.is( ':checked' ) ) {
                            $el.show().addClass( 'pods-dependent-visible' );
                            $el.find( '.pods-dependency .pods-depends-on' ).hide();
                            $el.find( '.pods-dependency .pods-excludes-on' ).hide();

                            $el.find( '.pods-dependency .pods-dependent-toggle' ).each( function () {
                                $( this ).trigger( 'change' );
                            } );
                        }
                        else if ( $el.is( dependent_specific ) ) {
                            $el.show().addClass( 'pods-dependent-visible' );
                            $el.find( '.pods-dependency .pods-depends-on' ).hide();
                            $el.find( '.pods-dependency .pods-excludes-on' ).hide();

                            $el.find( '.pods-dependency .pods-dependent-toggle' ).each( function () {
                                $( this ).trigger( 'change' );
                            } );
                        }
                        else
                            $el.hide().removeClass( 'pods-dependent-visible' );
                    }
                } );

                var exclude_flag = '.pods-excludes-on-' + $el.data( 'name-clean' );
                var exclude_specific = exclude_flag + '-' + $el.val();

                $current.find( exclude_flag ).each( function () {
                    var $el = $( this );

                    if ( $el.parent().is( ':visible' ) ) {
                        if ( $field.is( 'input[type=checkbox]' ) && $field.is( ':checked' ) )
                            $el.slideUp().removeClass( 'pods-dependent-visible' );
                        else if ( $el.is( exclude_specific ) )
                            $el.slideUp().removeClass( 'pods-dependent-visible' );
                        else {
                            $el.slideDown().addClass( 'pods-dependent-visible' );
                            $el.find( '.pods-dependency .pods-depends-on' ).hide();
                            $el.find( '.pods-dependency .pods-excludes-on' ).hide();

                            $el.find( '.pods-dependency .pods-dependent-toggle' ).each( function () {
                                $( this ).trigger( 'change' );
                            } );
                        }
                    }
                    else {
                        if ( $field.is( 'input[type=checkbox]' ) && $field.is( ':checked' ) )
                            $el.hide().removeClass( 'pods-dependent-visible' );
                        else if ( $el.is( exclude_specific ) )
                            $el.hide().removeClass( 'pods-dependent-visible' );
                        else {
                            $el.show().addClass( 'pods-dependent-visible' );
                            $el.find( '.pods-dependency .pods-depends-on' ).hide();
                            $el.find( '.pods-dependency .pods-excludes-on' ).hide();

                            $el.find( '.pods-dependency .pods-dependent-toggle' ).each( function () {
                                $( this ).trigger( 'change' );
                            } );
                        }
                    }
                } );
            } );

            $( '.pods-dependency .pods-dependent-toggle' ).each( function () {
                $( this ).change();
            } );
        },
        dependency_tabs : function () {
            // Hide all dependents
            $( '.pods-dependency-tabs .pods-depends-on' ).hide();

            // Handle dependent toggle
            $( document ).on( 'click', '.pods-dependency-tabs .pods-dependent-tab', function ( e ) {
                var $el = $( this );
                var $current = $el.closest( '.pods-dependency-tabs' );
                var $field = $el;

                var dependent_flag = '.pods-depends-on-' + $el.data( 'name-clean' );
                var dependent_specific = dependent_flag + '-' + $el.val();

                $current.find( dependent_flag ).each( function () {
                    var $s_el = $( this );

                    if ( $s_el.parent().is( ':visible' ) ) {
                        if ( $field.is( 'input[type=checkbox]' ) && $field.is( ':checked' ) ) {
                            $s_el.slideDown().addClass( 'pods-dependent-visible' );
                            $s_el.find( '.pods-dependency-tabs .pods-depends-on' ).hide();
                            $s_el.find( '.pods-dependency-tabs .pods-excludes-on' ).hide();

                            $s_el.find( '.pods-dependency-tabs .pods-dependent-tab.pods-dependent-tab-active' ).each( function () {
                                $( this ).trigger( 'click' );
                            } );
                        }
                        else if ( $s_el.is( dependent_specific ) ) {
                            $s_el.slideDown().addClass( 'pods-dependent-visible' );
                            $s_el.find( '.pods-dependency-tabs .pods-depends-on' ).hide();
                            $s_el.find( '.pods-dependency-tabs .pods-excludes-on' ).hide();

                            $s_el.find( '.pods-dependency-tabs .pods-dependent-tab.pods-dependent-tab-active' ).each( function () {
                                $( this ).trigger( 'click' );
                            } );
                        }
                        else
                            $s_el.slideUp().removeClass( 'pods-dependent-visible' );
                    }
                    else {
                        if ( $field.is( 'input[type=checkbox]' ) && $field.is( ':checked' ) ) {
                            $s_el.show().addClass( 'pods-dependent-visible' );
                            $s_el.find( '.pods-dependency-tabs .pods-depends-on' ).hide();
                            $s_el.find( '.pods-dependency-tabs .pods-excludes-on' ).hide();

                            $s_el.find( '.pods-dependency-tabs .pods-dependent-tab.pods-dependent-tab-active' ).each( function () {
                                $( this ).trigger( 'click' );
                            } );
                        }
                        else if ( $s_el.is( dependent_specific ) ) {
                            $s_el.show().addClass( 'pods-dependent-visible' );
                            $s_el.find( '.pods-dependency-tabs .pods-depends-on' ).hide();
                            $s_el.find( '.pods-dependency-tabs .pods-excludes-on' ).hide();

                            $s_el.find( '.pods-dependency-tabs .pods-dependent-tab.pods-dependent-tab-active' ).each( function () {
                                $( this ).trigger( 'click' );
                            } );
                        }
                        else
                            $s_el.hide().removeClass( 'pods-dependent-visible' );
                    }
                } );

                var exclude_flag = '.pods-excludes-on-' + $el.data( 'name-clean' );
                var exclude_specific = exclude_flag + '-' + $el.val();

                $current.find( exclude_flag ).each( function () {
                    var $s_el = $( this );

                    if ( $s_el.parent().is( ':visible' ) ) {
                        if ( $field.is( 'input[type=checkbox]' ) && $field.is( ':checked' ) )
                            $s_el.slideUp().removeClass( 'pods-dependent-visible' );
                        else if ( $s_el.is( exclude_specific ) )
                            $s_el.slideUp().removeClass( 'pods-dependent-visible' );
                        else {
                            $s_el.slideDown().addClass( 'pods-dependent-visible' );
                            $s_el.find( '.pods-dependency-tabs .pods-depends-on' ).hide();
                            $s_el.find( '.pods-dependency-tabs .pods-excludes-on' ).hide();

                            $s_el.find( '.pods-dependency-tabs .pods-dependent-tab.pods-dependent-tab-active' ).each( function () {
                                $( this ).trigger( 'click' );
                            } );
                        }
                    }
                    else {
                        if ( $field.is( 'input[type=checkbox]' ) && $field.is( ':checked' ) )
                            $s_el.hide().removeClass( 'pods-dependent-visible' );
                        else if ( $s_el.is( exclude_specific ) )
                            $s_el.hide().removeClass( 'pods-dependent-visible' );
                        else {
                            $s_el.show().addClass( 'pods-dependent-visible' );
                            $s_el.find( '.pods-dependency-tabs .pods-depends-on' ).hide();
                            $s_el.find( '.pods-dependency-tabs .pods-excludes-on' ).hide();

                            $s_el.find( '.pods-dependency-tabs .pods-dependent-tab.pods-dependent-tab-active' ).each( function () {
                                $( this ).trigger( 'click' );
                            } );
                        }
                    }
                } );
            } );

            $( '.pods-dependency-tabs .pods-dependent-tab.pods-dependent-tab-active' ).each( function () {
                $( this ).click();
            } );
        },
        sortable : function () {
            $( 'tr.pods-manage-row:even' ).addClass( 'alternate' );
            $( 'tbody.pods-manage-list' ).addClass( 'pods-manage-sortable' ).sortable( {
                items : 'tr.pods-manage-row',
                axis : 'y',
                handle : '.pods-manage-sort',
                stop : function ( event, ui ) {
                    $( 'tr.pods-manage-row' ).removeClass( 'alternate' );
                    $( 'tr.pods-manage-row:even' ).addClass( 'alternate' );
                }
            } );
        },
        advanced : function () {
            $( '.pods-advanced' ).hide();

            $( document ).on( 'click', '.pods-advanced-toggle', function ( e ) {
                $advanced = $( this ).closest( 'div' ).find( '.pods-advanced' );

                if ( $advanced.is( ':visible' ) ) {
                    $( this ).text( $( this ).text().replace( '-', '+' ) );
                    $advanced.slideUp();
                }
                else {
                    $( this ).text( $( this ).text().replace( '+', '-' ) );
                    $advanced.slideDown();
                }

                e.preventDefault();
            } );
        },
        collapsible : function () {
            // Hide all rows
            $( 'div.pods-manage-row-wrapper' ).hide();

            var orig_fields = {};

            // Handle 'Edit' action
            $( 'tbody.pods-manage-list' ).on( 'click', 'a.pods-manage-row-edit', function ( e ) {
                $( this ).css( 'cursor', 'default' );
                $( this ).prop( 'disabled', true );

                var $row = $( this ).closest( 'tr.pods-manage-row' );
                var $row_label = $row.find( 'td.pods-manage-row-label' );
                var $row_content = $row_label.find( 'div.pods-manage-row-wrapper' );

                if ( 'undefined' == typeof orig_fields[$row.data( 'id' )] )
                    orig_fields[$row.data( 'id' )] = {};

                // Row active, hide it
                if ( $row_content.is( ':visible' ) ) {
                    $row_content.find( 'input, select' ).each( function () {
                        $( this ).val( orig_fields[$row.data( 'id' )][$( this ).prop( 'name' )] );
                    } );

                    $row_content.slideUp( 'slow', function () {
                        $row.toggleClass( 'pods-manage-row-expanded' );
                        $row_label.prop( 'colspan', '1' );
                    } );
                }
                // Row inactive, show it
                else {
                    $row_content.find( 'input, select' ).each( function () {
                        orig_fields[$row.data( 'id' )][$( this ).prop( 'name' )] = $( this ).val();
                    } );

                    $row.toggleClass( 'pods-manage-row-expanded' );
                    $row_label.prop( 'colspan', '3' );
                    $row_content.slideDown();
                }

                $( this ).css( 'cursor', 'pointer' );
                $( this ).prop( 'disabled', false );

                e.preventDefault();
            } );

            // Handle 'Save' action
            $( 'tbody.pods-manage-list' ).on( 'click', '.pods-manage-row-save a.button-primary', function ( e ) {
                $( this ).css( 'cursor', 'default' );
                $( this ).prop( 'disabled', true );

                var $row = $( this ).closest( 'tr.pods-manage-row' );
                var $row_label = $row.find( 'td.pods-manage-row-label' );
                var $row_content = $row_label.find( 'div.pods-manage-row-wrapper' );
                var color = $.curCSS( $row.get( 0 ), 'backgroundColor' );
                var row_id = $row.data( 'row' );

                $row.css( 'backgroundColor', '#FFFF33' ).animate(
                    { backgroundColor : color },
                    {
                        duration : 'slow',
                        complete : function () {
                            $( this ).css( 'backgroundColor', '' );
                        }
                    }
                );

                if ( 'undefined' != typeof pods_field_types && null !== pods_field_types ) {
                    $row.find( 'td.pods-manage-row-label a.row-label' ).html( $row_content.find( 'input#pods-form-ui-field-data-' + row_id + '-label' ).val() );

                    if ( $row_content.find( 'input#pods-form-ui-field-data-' + row_id + '-required' ).is( ':checked' ) )
                        $row.find( 'td.pods-manage-row-label abbr.required' ).show();
                    else
                        $row.find( 'td.pods-manage-row-label abbr.required' ).hide();

                    $row.find( 'td.pods-manage-row-name a' ).html( $row_content.find( 'input#pods-form-ui-field-data-' + row_id + '-name' ).val() );

                    var field_type = $row_content.find( 'select#pods-form-ui-field-data-' + row_id + '-type' ).val();
                    var pick_object = $row_content.find( 'select#pods-form-ui-field-data-' + row_id + '-pick-object' ).val();
                    var field_type_desc = '';

                    if ( 'pick' == field_type && 0 != pick_object ) {
                        $.each( pods_pick_objects, function ( i, n ) {
                            if ( pick_object == i ) {
                                field_type_desc = '<br /><span class="pods-manage-field-type-desc">&rsaquo; ' + n + '</span>';
                                return false;
                            }
                        } );
                    }
                    $.each( pods_field_types, function ( i, n ) {
                        if ( field_type == i ) {
                            field_type = n;
                            return false;
                        }
                    } );

                    $row.find( 'td.pods-manage-row-type' ).html( field_type
                        + field_type_desc
                        + ' <span class="pods-manage-row-more">[type: ' + $row_content.find( 'select#pods-form-ui-field-data-' + row_id + '-type' ).val() + ']</span>' );
                }

                $row_content.slideUp( 'slow', function () {
                    $row.toggleClass( 'pods-manage-row-expanded' );
                    $row_label.prop( 'colspan', '1' );
                } );

                $( this ).css( 'cursor', 'pointer' );
                $( this ).prop( 'disabled', false );

                e.preventDefault();
            } );

            // Handle 'Cancel' action
            $( 'tbody.pods-manage-list' ).on( 'click', '.pods-manage-row-actions a.pods-manage-row-cancel', function ( e ) {
                $( this ).closest( 'tr.pods-manage-row' ).find( 'a.pods-manage-row-edit' ).click();

                e.preventDefault();
            } );
        },
        toggled : function () {
            $( '.pods-toggled .handlediv, .pods-toggled h3' ).live( 'click', function () {
                $( this ).parent().find( '.inside' ).slideToggle();
                return false;
            } );
        },
        flexible : function ( row ) {
            var new_row = row,
                row_counter = 0;

            if ( new_row[ 0 ] ) {
                new_row = new_row.html();

                // Don't count flexible row
                row_counter = -1;
            }

            row_counter += $( 'tr.pods-manage-row' ).length;

            // Handle 'Add' action
            if ( 'undefined' != typeof new_row && null !== new_row ) {
                $( '.pods-manage-row-add' ).on( 'click', 'a', function ( e ) {
                    $( this ).css( 'cursor', 'default' );
                    $( this ).prop( 'disabled', true );

                    row_counter++;

                    var add_row = new_row.replace( /\_\_1/gi, row_counter ).replace( /\-\-1/gi, row_counter );
                    var $tbody = $( this ).parent().parent().find( 'tbody.pods-manage-list' );

                    $tbody.find( 'tr.no-items' ).hide();
                    $tbody.append( '<tr id="row-' + row_counter + '" class="pods-manage-row pods-field-' + row_counter + ' pods-submittable-fields" valign="top">' + add_row + '</tr>' );

                    $new_row = $tbody.find( 'tr#row-' + row_counter );

                    $new_row.data( 'row', row_counter );
                    $new_row.find( '.pods-dependency .pods-depends-on' ).hide();
                    $new_row.find( '.pods-dependency .pods-excludes-on' ).hide();

                    $new_row.find( '.pods-dependency .pods-dependent-toggle' ).each( function () {
                        $( this ).trigger( 'change' );
                    } );

                    $new_row.find( '.pods-manage-row-wrapper' ).hide( 0, function () {
                        $new_row.find( 'a.row-label.pods-manage-row-edit' ).click();
                    } );

                    $( '.pods-tabs .pods-tab:first a', $new_row ).addClass( 'selected' );
                    $( '.pods-tab-group', $new_row ).find( '.pods-tab:first' ).show();

                    if ( $.fn.sortable && $tbody.hasClass( 'pods-manage-sortable' ) )
                        $tbody.sortable( 'refresh' );

                    $( this ).css( 'cursor', 'pointer' );
                    $( this ).prop( 'disabled', false );

                    e.preventDefault();
                } );
            }

            // Handle 'Delete' action
            $( 'tbody.pods-manage-list' ).on( 'click', 'a.submitdelete', function ( e ) {
                $( this ).css( 'cursor', 'default' );
                $( this ).prop( 'disabled', true );

                // @todo: Make this confirm pretty so that it's inline instead of JS confirm
                if ( confirm( 'Are you sure you want to delete this field?' ) ) {
                    var $row = $( this ).closest( 'tr.pods-manage-row' );
                    var $tbody = $( this ).closest( 'tbody.pods-manage-list' );

                    $row.animate( {backgroundColor : '#B80000'} );

                    $row.fadeOut( 'slow', function () {
                        $( this ).remove();
                        if ( 0 == $( 'tbody.pods-manage-list tr.pods-manage-row' ).length )
                            $tbody.find( 'tr.no-items' ).show();
                    } );

                    if ( $.fn.sortable && $tbody.hasClass( 'pods-manage-sortable' ) )
                        $( this ).closest( 'tbody.pods-manage-list' ).sortable( 'refresh' );

                    //row_counter--;
                }

                $( this ).css( 'cursor', 'pointer' );
                $( this ).prop( 'disabled', false );

                e.preventDefault();
            } );
        },
        confirm : function () {
            $( 'a.pods-confirm' ).on( 'click', function ( e ) {
                var $el = $( this );

                if ( 'undefined' != typeof $el.data( 'confirm' ) && !confirm( $el.data( 'confirm' ) ) )
                    return false;
            } );
        },
        exit_confirm : function () {
            $( 'form.pods-submittable .pods-submittable-fields' ).on( 'change', 'input:not(:button,:submit),textarea,select', function () {
                pods_changed = true;

                window.onbeforeunload = function () {
                    if ( pods_changed )
                        return 'Navigating away from this page will discard any changes you have made.';
                }
            } );
        }
    };

    $.fn.Pods = function ( method ) {
        if ( methods[method] ) {
            return methods[ method ].apply( this, Array.prototype.slice.call( arguments, 1 ) );
        }
        // Don't need this part (yet)
        /*
         else if (typeof method === 'object' || !method) {
         return methods.init.apply(this, arguments);
         }
         */
        else {
            $.error( 'Method ' + method + ' does not exist on jQuery.Pods' );
        }
    };
})( jQuery );

jQuery( function ( $ ) {
    $( '.pods-qtip' ).qtip( {
        content : {
            attr : 'alt'
        },
        style : {
            classes : 'ui-tooltip-light ui-tooltip-shadow ui-tooltip-rounded'
        },
        show : {
            effect : function ( offset ) {
                $( this ).fadeIn( 'fast' );
            }
        },
        hide : {
            fixed : true,
            delay : 300
        },
        position : {
            my : 'bottom left',
            adjust : {
                y : -14
            }
        }
    } );
} );