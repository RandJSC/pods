<?php
    $uri_hash = wp_create_nonce( 'pods_uri_' . $_SERVER[ 'REQUEST_URI' ] );
    $field_hash = wp_create_nonce( 'pods_fields_' . implode( ',', array_keys( $fields ) ) );

    $nonce = wp_create_nonce( 'pods_form_' . $pod->pod . '_' . session_id() . '_' . $pod->id() . '_' . $uri_hash . '_' . $field_hash );

    if ( isset( $_POST[ '_pods_nonce' ] ) ) {
        $action = __( 'saved', 'pods' );

        if ( 'create' == pods_var( 'do', 'post', 'save' ) )
            $action = __( 'created', 'pods' );

        try {
            $params = stripslashes_deep( (array) $_POST );
            $id = $pod->api->process_form( $params, $pod, $fields, $thank_you );

            $message = sprintf( __( '<strong>Success!</strong> %s %s successfully.', 'pods' ), $obj->item, $action );
            $error = sprintf( __( '<strong>Error:</strong> %s %s successfully.', 'pods' ), $obj->item, $action );

            if ( 0 < $id )
                echo $obj->message( $message );
            else
                echo $obj->error( $error );
        }
        catch ( Exception $e ) {
            echo $obj->error( $e->getMessage() );
        }
    }
    elseif ( isset( $_GET[ 'do' ] ) ) {
        $action = __( 'saved', 'pods' );

        if ( 'create' == pods_var( 'do', 'get', 'save' ) )
            $action = __( 'created', 'pods' );

        $message = sprintf( __( '<strong>Success!</strong> %s %s successfully.', 'pods' ), $obj->item, $action );
        $error = sprintf( __( '<strong>Error:</strong> %s %s successfully.', 'pods' ), $obj->item, $action );

        if ( 0 < $id )
            echo $obj->message( $message );
        else
            echo $obj->error( $error );
    }
?>

<form action="" method="post" class="pods-submittable pods-form pods-form-pod-<?php echo $pod->pod; ?>">
    <div class="pods-submittable-fields">
        <?php echo PodsForm::field( 'action', 'pods_admin', 'hidden' ); ?>
        <?php echo PodsForm::field( 'method', 'process_form', 'hidden' ); ?>
        <?php echo PodsForm::field( 'do', ( 0 < $pod->id() ? 'save' : 'create' ), 'hidden' ); ?>
        <?php echo PodsForm::field( '_pods_nonce', $nonce, 'hidden' ); ?>
        <?php echo PodsForm::field( '_pods_pod', $pod->pod, 'hidden' ); ?>
        <?php echo PodsForm::field( '_pods_id', $pod->id(), 'hidden' ); ?>
        <?php echo PodsForm::field( '_pods_uri', $uri_hash, 'hidden' ); ?>
        <?php echo PodsForm::field( '_pods_form', implode( ',', array_keys( $fields ) ), 'hidden' ); ?>

        <div id="poststuff" class="metabox-holder has-right-sidebar"> <!-- class "has-right-sidebar" preps for a sidebar... always present? -->
            <div id="side-info-column" class="inner-sidebar">
                <div id="side-sortables" class="meta-box-sortables ui-sortable">
                    <!-- BEGIN PUBLISH DIV -->
                    <div id="submitdiv" class="postbox">
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        <h3 class="hndle"><span><?php _e( 'Manage', 'pods' ); ?></span></h3>

                        <div class="inside">
                            <div class="submitbox" id="submitpost">
                                <div id="minor-publishing">
                                    <div id="major-publishing-actions">
                                        <?php
                                            if ( is_super_admin() || current_user_can( 'pods_delete_' . $pod->pod ) ) {
                                        ?>
                                            <div id="delete-action">
                                                <a class="submitdelete deletion" href="#"><?php _e( 'Delete', 'pods' ); ?></a>
                                            </div>
                                            <!-- /#delete-action -->
                                        <?php } ?>

                                        <div id="publishing-action">
                                            <input type="submit" name="publish" id="publish" class="button-primary" value="<?php _e( 'Save' ); ?>" accesskey="p" />
                                        </div>
                                        <!-- /#publishing-action -->

                                        <div class="clear"></div>
                                    </div>
                                    <!-- /#major-publishing-actions -->
                                </div>
                                <!-- /#minor-publishing -->
                            </div>
                            <!-- /#submitpost -->
                        </div>
                        <!-- /.inside -->
                    </div>
                    <!-- /#submitdiv --><!-- END PUBLISH DIV --><!-- TODO: minor column fields -->
                </div>
                <!-- /#side-sortables -->
            </div>
            <!-- /#side-info-column -->

            <div id="post-body">
                <div id="post-body-content">
                    <div id="titlediv">
                        <div id="titlewrap">
                            <label class="hide-if-no-js" style="visibility:hidden" id="title-prompt-text" for="title"><?php echo apply_filters( 'pods_enter_name_here', __( 'Enter name here' ), $pod, $fields ); ?></label>
                            <input type="text" name="pods_field_<?php echo $pod->pod_data[ 'field_index' ]; ?>" data-name-clean="pods-field-<?php echo $pod->pod_data[ 'field_index' ]; ?>" id="title" size="30" tabindex="1" value="<?php echo esc_attr( htmlspecialchars( $pod->index() ) ); ?>" class="pods-form-ui-field-name-pods-field-<?php echo $pod->pod_data[ 'field_index' ]; ?>" autocomplete="off" />
                        </div>
                        <!-- /#titlewrap -->

                        <div class="inside">
                            <div id="edit-slug-box">
                            </div>
                            <!-- /#edit-slug-box -->
                        </div>
                        <!-- /.inside -->
                    </div>
                    <!-- /#titlediv -->

                    <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                        <div id="pods-meta-box" class="postbox" style="">
                            <div class="handlediv" title="Click to toggle"><br /></div>
                            <h3 class="hndle"><span><?php _e( 'More Fields', 'pods' ); ?></span></h3>

                            <div class="inside">
                                <table class="form-table pods-metabox">
                                    <?php
                                        foreach ( $fields as $field ) {
                                            if ( $pod->pod_data[ 'field_index' ] == $field[ 'name' ] )
                                                continue;
                                    ?>
                                        <tr class="form-field pods-field <?php echo 'pods-form-ui-row-type-' . $field[ 'type' ] . ' pods-form-ui-row-name-' . Podsform::clean( $field[ 'name' ], true ); ?>">
                                            <th scope="row" valign="top"><?php echo PodsForm::label( 'pods_field_' . $field[ 'name' ], $field[ 'label' ], $field[ 'help' ], $field ); ?></th>
                                            <td>
                                                <?php echo PodsForm::field( 'pods_field_' . $field[ 'name' ], $pod->field( array( 'name' => $field[ 'name' ], 'in_form' => true ) ), $field[ 'type' ], $field, $pod, $pod->id() ); ?>
                                                <?php echo PodsForm::comment( 'pods_field_' . $field[ 'name' ], $field[ 'description' ], $field ); ?>
                                            </td>
                                        </tr>
                                    <?php
                                        }
                                    ?>
                                </table>
                            </div>
                            <!-- /.inside -->
                        </div>
                        <!-- /#pods-meta-box -->
                    </div>
                    <!-- /#normal-sortables -->

                    <!--<div id="advanced-sortables" class="meta-box-sortables ui-sortable">
                    </div>
                     /#advanced-sortables -->

                </div>
                <!-- /#post-body-content -->

                <br class="clear" />
            </div>
            <!-- /#post-body -->

            <br class="clear" />
        </div>
        <!-- /#poststuff -->
    </div>
</form>
<!-- /#pods-record -->

<script type="text/javascript">
    jQuery( function ( $ ) {
        $( document ).Pods( 'validate' );
        $( document ).Pods( 'submit' );
        $( document ).Pods( 'dependency' );
        $( document ).Pods( 'confirm' );
    } );

    var pods_admin_submit_callback = function ( id ) {
        var thank_you = '<?php echo addslashes( $thank_you ); ?>';

        document.location = thank_you.replace( 'X_ID_X', id );
    }
</script>