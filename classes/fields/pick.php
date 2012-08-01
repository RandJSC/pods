<?php
class PodsField_Pick extends PodsField {

    /**
     * Field Type Identifier
     *
     * @var string
     * @since 2.0.0
     */
    public static $type = 'pick';

    /**
     * Field Type Label
     *
     * @var string
     * @since 2.0.0
     */
    public static $label = 'Relationship';

    /**
     * Do things like register/enqueue scripts and stylesheets
     *
     * @since 2.0.0
     */
    public function __construct () {

    }

    /**
     * Add options and set defaults to
     *
     * @param array $options
     *
     * @since 2.0.0
     */
    public function options () {
        $options = array(
            'pick_format_type' => array(
                'label' => __( 'Selection Type', 'pods' ),
                'help' => __( 'help', 'pods' ),
                'default' => 'single',
                'type' => 'pick',
                'data' => array(
                    'single' => __( 'Single Select', 'pods' ),
                    'multi' => __( 'Multiple Select', 'pods' )
                ),
                'dependency' => true
            ),
            'pick_format_single' => array(
                'label' => __( 'Format', 'pods' ),
                'help' => __( 'help', 'pods' ),
                'depends-on' => array( 'pick_format_type' => 'single' ),
                'default' => 'dropdown',
                'type' => 'pick',
                'data' => apply_filters(
                    'pods_form_ui_field_pick_format_single_options',
                    array(
                        'dropdown' => __( 'Drop Down', 'pods' ),
                        'radio' => __( 'Radio Buttons', 'pods' ),
                        'autocomplete' => __( 'Autocomplete', 'pods' )
                    )
                ),
                'dependency' => true
            ),
            'pick_format_multi' => array(
                'label' => __( 'Format', 'pods' ),
                'help' => __( 'help', 'pods' ),
                'depends-on' => array( 'pick_format_type' => 'multi' ),
                'default' => 'checkbox',
                'type' => 'pick',
                'data' => apply_filters(
                    'pods_form_ui_field_pick_format_multi_options',
                    array(
                        'checkbox' => __( 'Checkboxes', 'pods' ),
                        'multiselect' => __( 'Multi Select', 'pods' ),
                        'autocomplete' => __( 'Autocomplete', 'pods' )
                    )
                ),
                'dependency' => true
            ),
            'pick_limit' => array(
                'label' => __( 'Selection Limit', 'pods' ),
                'help' => __( 'help', 'pods' ),
                'depends-on' => array( 'pick_format_type' => 'multi' ),
                'default' => 0,
                'type' => 'number'
            ),
            'pick_display' => array(
                'label' => __( 'Display Field in Selection List', 'pods' ),
                'help' => __( 'You can use {@magic_tags} to reference field names on the related object.', 'pods' ),
                'excludes-on' => array( 'pick_object' => 'custom-simple' ),
                'default' => '{@name}',
                'type' => 'text'
            ),
            'pick_where' => array(
                'label' => __( '', 'pods' ),
                'help' => __( 'help', 'pods' ),
                'excludes-on' => array( 'pick_object' => 'custom-simple' ),
                'default' => '',
                'type' => 'text'
            ),
            'pick_orderby' => array(
                'label' => __( '', 'pods' ),
                'help' => __( 'help', 'pods' ),
                'excludes-on' => array( 'pick_object' => 'custom-simple' ),
                'default' => '',
                'type' => 'text'
            ),
            'pick_groupby' => array(
                'label' => __( '', 'pods' ),
                'help' => __( 'help', 'pods' ),
                'excludes-on' => array( 'pick_object' => 'custom-simple' ),
                'default' => '',
                'type' => 'text'
            ),
            'pick_size' => array(
                'label' => __( 'Field Size', 'pods' ),
                'default' => 'medium',
                'type' => 'pick',
                'data' => array(
                    'small' => __( 'Small', 'pods' ),
                    'medium' => __( 'Medium', 'pods' ),
                    'large' => __( 'Large', 'pods' )
                )
            )
        );
        return $options;
    }

    /**
     * Define the current field's schema for DB table storage
     *
     * @param array $options
     *
     * @return array
     * @since 2.0.0
     */
    public function schema ( $options = null ) {
        $schema = false;

        return $schema;
    }

    /**
     * Change the way the value of the field is displayed with Pods::get
     *
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param string $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function display ( &$value, $name, $options, $fields, &$pod, $id ) {

    }

    /**
     * Customize output of the form field
     *
     * @param string $name
     * @param string $value
     * @param array $options
     * @param string $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function input ( $name, $value = null, $options = null, $pod = null, $id = null ) {
        global $wpdb;

        $options = (array) $options;

        $options[ 'grouped' ] = 1;

        $custom = pods_var( 'pick_custom', $options, false );

        if ( 'custom-simple' == pods_var( 'pick_object', $options ) && !empty( $custom ) ) {
            if ( !is_array( $custom ) )
                $custom = explode( "\n", $custom );

            $options[ 'data' ] = array();

            foreach ( $custom as $custom_value ) {
                $custom_label = explode( '|', $custom_value );

                if ( empty( $custom_label ) )
                    continue;

                if ( 1 == count( $custom_label ) )
                    $custom_label = $custom_value;
                else {
                    $custom_label = $custom_label[ 1 ];
                    $custom_value = $custom_label[ 0 ];
                }

                $options[ 'data' ][ $custom_value ] = $custom_label;
            }
        }
        elseif ( '' != pods_var( 'pick_object', $options, '' ) && array() == pods_var( 'data', $options, array() ) ) {
            $options[ 'data' ] = array( '' => __( '-- Select One --', 'pods' ) );

            $table = $field_id = $field_name = false;

            switch ( pods_var( 'pick_object', $options ) ) {
                case 'pod':
                    $table = $wpdb->prefix . 'pods_tbl_' . pods_var( 'pick_val', $options );
                    $field_id = 'id';
                    $field_name = 'name';
                    break;
                case 'post_type':
                case 'media':
                    $table = $wpdb->posts;
                    $field_id = 'ID';
                    $field_name = 'post_title';
                    break;
                case 'taxonomy':
                    $table = $wpdb->taxonomy;
                    $field_id = 'term_id';
                    $field_name = 'name';
                    break;
                case 'user':
                    $table = $wpdb->users;
                    $field_id = 'ID';
                    $field_name = 'display_name';
                    break;
                case 'comment':
                    $table = $wpdb->comments;
                    $field_id = 'comment_ID';
                    $field_name = 'comment_date';
                    break;
                case 'table':
                    $table = pods_var( 'pick_val', $options );
                    $field_id = 'id';
                    $field_name = 'name';
                    break;
            }

            $data = pods_data();
            $data->table = $table;
            $data->field_id = $field_id;
            $data->field_name = $field_name;

            $results = $data->select( array(
                'select' => "`{$data->field_id}`, `{$data->field_name}`",
                'table' => $data->table,
                'identifier' => $data->field_id,
                'index' => $data->field_name,
                'where' => pods_var( 'pick_where', $options, null, null, true ),
                'orderby' => pods_var( 'pick_where', $options, null, null, true ),
                'groupby' => pods_var( 'pick_groupby', $options, null, null, true )
            ) );

            foreach ( $results as $result ) {
                $options[ 'data' ][ $result->{$field_id} ] = $result->{$field_name};
            }
        }

        if ( 'single' == $options[ 'pick_format_type' ] ) {
            if ( 'dropdown' == $options[ 'pick_format_single' ] )
                $field_type = 'select';
            elseif ( 'radio' == $options[ 'pick_format_single' ] )
                $field_type = 'radio';
            elseif ( 'autocomplete' == $options[ 'pick_format_single' ] )
                $field_type = 'select2';
            else {
                // Support custom integration
                do_action( 'pods_form_ui_field_pick_input_' . $options[ 'pick_format_type' ] . '_' . $options[ 'pick_format_single' ], $name, $value, $options, $pod, $id );
                do_action( 'pods_form_ui_field_pick_input', $options[ 'pick_format_type' ], $name, $value, $options, $pod, $id );
                return;
            }
        }
        elseif ( 'multi' == $options[ 'pick_format_type' ] ) {
            if ( 'checkbox' == $options[ 'pick_format_multi' ] )
                $field_type = 'checkbox';
            elseif ( 'multiselect' == $options[ 'pick_format_multi' ] )
                $field_type = 'select';
            elseif ( 'autocomplete' == $options[ 'pick_format_multi' ] )
                $field_type = 'select2';
            else {
                // Support custom integration
                do_action( 'pods_form_ui_field_pick_input_' . $options[ 'pick_format_type' ] . '_' . $options[ 'pick_format_multi' ], $name, $value, $options, $pod, $id );
                do_action( 'pods_form_ui_field_pick_input', $options[ 'pick_format_type' ], $name, $value, $options, $pod, $id );
                return;
            }
        }

        pods_view( PODS_DIR . 'ui/fields/' . $field_type . '.php', compact( array_keys( get_defined_vars() ) ) );
    }

    /**
     * Validate a value before it's saved
     *
     * @param string $value
     * @param string $name
     * @param array $options
     * @param array $data
     * @param object $api
     * @param string $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function validate ( &$value, $name, $options, $data, &$api, &$pod, $id = false ) {
        // check file size
        // check file extensions
    }

    /**
     * Change the value or perform actions after validation but before saving to the DB
     *
     * @param string $value
     * @param string $name
     * @param array $options
     * @param array $data
     * @param object $api
     * @param string $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function pre_save ( &$value, $name, $options, $data, &$api, &$pod, $id = false ) {

    }

    /**
     * Customize the Pods UI manage table column output
     *
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param string $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function ui ( $value, $name, $options, $fields, &$pod, $id ) {
        // link to file in new target
        // show thumbnail
    }
}