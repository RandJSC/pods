<?php
/**
 * Created by JetBrains PhpStorm.
 * User: sclark
 * Date: 8/8/12
 * Time: 2:51 PM
 * To change this template use File | Settings | File Templates.
 */
class PodsUpgrade_2_0 {

    public $tables = array();

    private $progress = array();

    function __construct () {
        $this->get_tables();
        $this->get_progress();
    }

    function get_tables () {
        global $wpdb;

        $tables = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}pod%'", ARRAY_N );

        if ( !empty( $tables ) ) {
            foreach ( $tables as $table ) {
                $this->tables[] = $table[ 0 ];
            }
        }
    }

    function get_progress () {
        $methods = get_class_methods( $this );

        foreach ( $methods as $method ) {
            if ( 0 === strpos( $method, 'migrate_' ) ) {
                $this->progress[ str_replace( 'migrate_', '', $method ) ] = false;

                if ( 'migrate_pod' == $method )
                    $this->progress[ str_replace( 'migrate_', '', $method ) ] = array();
            }
        }

        $progress = (array) get_option( 'pods_framework_upgrade_2_0', array() );

        if ( !empty( $progress ) )
            $this->progress = array_merge( $this->progress, $progress );
    }

    function install () {
        global $wpdb;

        $pods_version = get_option( 'pods_version' );

        if ( !empty( $pods_version ) && version_compare( $pods_version, '2.0.0', '<' ) ) {
            $sql = file_get_contents( PODS_DIR . 'sql/dump.sql' );
            $sql = apply_filters( 'pods_install_sql', $sql, PODS_VERSION, $pods_version );

            $charset_collate = 'DEFAULT CHARSET utf8';

            if ( !empty( $wpdb->charset ) )
                $charset_collate = "DEFAULT CHARSET {$wpdb->charset}";

            if ( !empty( $wpdb->collate ) )
                $charset_collate .= " COLLATE {$wpdb->collate}";

            if ( 'DEFAULT CHARSET utf8' != $charset_collate )
                $sql = str_replace( 'DEFAULT CHARSET utf8', $charset_collate, $sql );

            $sql = explode( ";\n", str_replace( array( "\r", 'wp_' ), array( "\n", $wpdb->prefix ), $sql ) );

            for ( $i = 0, $z = count( $sql ); $i < $z; $i++ ) {
                pods_query( trim( $sql[ $i ] ), 'Cannot setup SQL tables' );
            }
        }
    }

    function ajax ( $params ) {
        if ( !isset( $params->step ) )
            return pods_error( __( 'Invalid upgrade process.', 'pods' ) );

        if ( !isset( $params->type ) )
            return pods_error( __( 'Invalid upgrade method.', 'pods' ) );

        if ( !method_exists( $this, $params->step . '_' . $params->type ) )
            return pods_error( __( 'Upgrade method not found.', 'pods' ) );

        return call_user_func( array( $this, $params->step . '_' . $params->type ), $params );
    }

    function prepare_pods () {
        global $wpdb;

        if ( !in_array( "{$wpdb->prefix}pod_types", $this->tables ) )
            return pods_error( __( 'Table not found, it cannot be migrated', 'pods' ) );

        $count = @count( (array) pods_query( "SELECT * FROM `@wp_pod_types`", false ) );

        return $count;
    }

    function prepare_fields () {
        global $wpdb;

        if ( !in_array( "{$wpdb->prefix}pod_fields", $this->tables ) )
            return pods_error( __( 'Table not found, it cannot be migrated', 'pods' ) );

        $count = @count( (array) pods_query( "SELECT * FROM `@wp_pod_fields`", false ) );

        return $count;
    }

    function prepare_relationships () {
        global $wpdb;

        if ( !in_array( "{$wpdb->prefix}pod_fields", $this->tables ) )
            return pods_error( __( 'Table not found, it cannot be migrated', 'pods' ) );

        $count = @count( (array) pods_query( "SELECT * FROM `@wp_pod_rel`", false ) );

        return $count;
    }

    function prepare_index () {
        global $wpdb;

        if ( !in_array( "{$wpdb->prefix}pod", $this->tables ) )
            return pods_error( __( 'Table not found, it cannot be migrated', 'pods' ) );

        $count = @count( (array) pods_query( "SELECT * FROM `@wp_pod`", false ) );

        return $count;
    }

    function prepare_templates () {
        global $wpdb;

        if ( !in_array( "{$wpdb->prefix}pod_templates", $this->tables ) )
            return pods_error( __( 'Table not found, it cannot be migrated', 'pods' ) );

        $count = @count( (array) pods_query( "SELECT * FROM `@wp_pod_templates`", false ) );

        return $count;
    }

    function prepare_pages () {
        global $wpdb;

        if ( !in_array( "{$wpdb->prefix}pod_pages", $this->tables ) )
            return pods_error( __( 'Table not found, it cannot be migrated', 'pods' ) );

        $count = @count( (array) pods_query( "SELECT * FROM `@wp_pod_pages`", false ) );

        return $count;
    }

    function prepare_helpers () {
        global $wpdb;

        if ( !in_array( "{$wpdb->prefix}pod_helpers", $this->tables ) )
            return pods_error( __( 'Table not found, it cannot be migrated', 'pods' ) );

        $count = @count( (array) pods_query( "SELECT * FROM `@wp_pod_helpers`", false ) );

        return $count;
    }

    function prepare_pod ( $params ) {
        global $wpdb;

        if ( !isset( $params->pod ) )
            return pods_error( __( 'Invalid Pod.', 'pods' ) );

        $pod = pods_sanitize( pods_clean_name( $params->pod ) );

        if ( !in_array( "{$wpdb->prefix}pod_tbl_{$pod}", $this->tables ) )
            return pods_error( __( 'Table not found, it cannot be migrated', 'pods' ) );

        $count = @count( (array) pods_query( "SELECT * FROM `@wp_pod_tbl_{$pod}`", false ) );

        return $count;
    }

    function migrate_pods () {
        if ( true === $this->check_progress( __FUNCTION__ ) )
            return '1';

        $api = pods_api();

        $pod_types = pods_query( "SELECT * FROM `@wp_pod_types`", false );

        $pod_ids = array();

        if ( empty( $pod_types ) )
            return $pod_ids;

        foreach ( $pod_types as $pod_type ) {
            $field_rows = pods_query( "SELECT * FROM `@wp_pod_fields` WHERE `datatype` = {$pod_type->id} ORDER BY `weight`, `name`" );

            $fields = array(
                array(
                    'name' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'weight' => 0,
                    'options' => array(
                        'required' => '1'
                    )
                ),
                array(
                    'name' => 'created',
                    'label' => 'Date Created',
                    'type' => 'date',
                    'weight' => 1
                ),
                array(
                    'name' => 'modified',
                    'label' => 'Date Modified',
                    'type' => 'date',
                    'weight' => 2
                ),
                array(
                    'name' => 'author',
                    'label' => 'Author',
                    'type' => 'pick',
                    'pick_object' => 'user',
                    'weight' => 3
                )
            );

            $weight = 4;

            foreach ( $field_rows as $row ) {
                if ( 'name' == $row->name )
                    continue;

                if ( in_array( $row->name, array( 'created', 'modified', 'author' ) ) )
                    $row->name .= '2';

                $field_type = $row->coltype;

                if ( 'txt' == $field_type )
                    $field_type = 'text';
                elseif ( 'desc' == $field_type )
                    $field_type = 'paragraph';
                elseif ( 'bool' == $field_type )
                    $field_type = 'boolean';
                elseif ( 'num' == $field_type )
                    $field_type = 'number';

                $field_params = array(
                    'name' => trim( $row->name ),
                    'label' => trim( $row->label ),
                    'type' => $field_type,
                    'weight' => $weight,
                    'options' => array(
                        'required' => $row->required,
                        'unique' => $row->unique,
                        'input_helper' => $row->input_helper
                    )
                );

                if ( 'pick' == $field_type ) {
                    $field_params[ 'pick_object' ] = 'pod';
                    $field_params[ 'pick_val' ] = $row->pickval;

                    if ( 'wp_user' == $row->pickval ) {
                        $field_params[ 'pick_object' ] = 'user';
                        $field_params[ 'pick_val' ] = '';
                    }
                    elseif ( 'wp_post' == $row->pickval ) {
                        $field_params[ 'pick_object' ] = 'post_type';
                        $field_params[ 'pick_val' ] = 'post';
                    }
                    elseif ( 'wp_page' == $row->pickval ) {
                        $field_params[ 'pick_object' ] = 'post_type';
                        $field_params[ 'pick_val' ] = 'page';
                    }
                    elseif ( 'wp_taxonomy' == $row->pickval ) {
                        $field_params[ 'pick_object' ] = 'taxonomy';
                        $field_params[ 'pick_val' ] = 'category';
                    }

                    $field_params[ 'sister_field_id' ] = $row->sister_field_id;
                    $field_params[ 'pick_filter' ] = $row->pick_filter;
                    $field_params[ 'pick_orderby' ] = $row->pick_orderby;
                    $field_params[ 'pick_display' ] = '{@name}';
                    $field_params[ 'pick_size' ] = 'medium';

                    if ( 1 == $row->multiple ) {
                        $field_params[ 'pick_format_type' ] = 'multi';
                        $field_params[ 'pick_format_multi' ] = 'checkbox';
                        $field_params[ 'pick_limit' ] = 0;
                    }
                    else {
                        $field_params[ 'pick_format_type' ] = 'single';
                        $field_params[ 'pick_format_single' ] = 'dropdown';
                    }
                }
                elseif ( 'number' == $field_type ) {
                    $field_params[ 'number_format_type' ] = 'plain';
                    $field_params[ 'number_decimals' ] = 2;
                }

                $fields[] = $field_params;

                $weight++;
            }

            $pod_params = array(
                'name' => $pod_type->name,
                'label' => $pod_type->label,
                'type' => 'pod',
                'storage' => 'table',
                'fields' => $fields,
                'options' => array(
                    'pre_save_helpers' => $pod_type->pre_save_helpers,
                    'post_save_helpers' => $pod_type->post_save_helpers,
                    'pre_delete_helpers' => $pod_type->pre_drop_helpers,
                    'post_delete_helpers' => $pod_type->post_drop_helpers,
                    'show_in_menu' => $pod_type->is_toplevel,
                    'detail_url' => $pod_type->detail_page,
                ),
            );

            $pod_id = $api->save_pod( $pod_params );
            $pod_ids[] = $pod_id;
        }

        $this->get_tables();

        $this->update_progress( __FUNCTION__, true );

        return '1';
    }

    function migrate_fields () {
        return '1';
    }

    function migrate_relationships () {
        if ( true === $this->check_progress( __FUNCTION__ ) )
            return '1';

        global $wpdb;

        $api = pods_api();

        $last_id = (int) $this->check_progress( __FUNCTION__ );

        $sql = "
            SELECT *
            FROM `@wp_pod_rel`
            WHERE {$last_id} < `id`
            ORDER BY `id`
            LIMIT 0, 800
        ";

        $rel = pods_query( $sql );

        $last_id = true;

        $pod_types = pods_query( "SELECT `id`, `name` FROM `@wp_pod_types` ORDER BY `id`" );

        $types = array();

        $x = 0;

        if ( !empty( $rel ) && !empty( $pod_types ) ) {
            foreach ( $pod_types as $type ) {
                $types[ $type->id ] = $api->load_pod( array( 'name' => $type->name ) );

                $pod_fields = pods_query( "SELECT `id`, `name` FROM `@wp_pod_fields` WHERE `datatype` = {$type->id} ORDER BY `id`" );

                foreach ( $pod_fields as $field ) {
                    $types[ 'old_fields' ][ $field->id ] = $field->name;
                }
            }

            foreach ( $rel as $r ) {
                $r->pod_id = (int) $r->pod_id;

                $p = pods_query( "SELECT `tbl_row_id`, `datatype` FROM `@wp_pod` WHERE `id` = {$r->pod_id}");

                if ( empty( $p ) )
                    continue;

                if ( !isset( $types[ $p->datatype ] ) || !isset( $types[ $p->datatype ][ 'old_fields' ][ $r->field_id ] ) )
                    continue;

                if ( !isset( $types[ $p->datatype ][ 'fields' ][ $types[ $p->datatype ][ 'old_fields' ][ $r->field_id ] ] ) )
                    continue;

                $field = $types[ $p->datatype ][ 'fields' ][ $types[ $p->datatype ][ 'old_fields' ][ $r->field_id ] ];

                if ( !in_array( $field[ 'type' ], array( 'pick', 'file' ) ) )
                    continue;

                $pod_id = $types[ $p->datatype ][ 'id' ];
                $field_id = $field[ 'id' ];
                $item_id = $p->tbl_row_id;

                $related_pod_id = 0;
                $related_field_id = 0;
                $related_item_id = $r->tbl_row_id;

                if ( 'pick' == $field[ 'type' ] ) {
                    if ( 0 < (int) $field[ 'sister_field_id' ] ) {
                        $related_field = $api->load_field( array( 'id' => $field[ 'sister_field_id' ] ) );

                        if ( empty( $related_field ) )
                            continue;

                        $related_pod_id = $related_field[ 'pod_id' ];
                        $related_field_id = $related_field[ 'id' ];
                    }
                    elseif ( 'pod' == $field[ 'pick_object' ] && 0 < strlen( $field[ 'pick_val' ] ) ) {
                        $related_pod = $api->load_pod( array( 'name' => $field[ 'pick_val' ] ), false );

                        if ( empty( $related_pod ) )
                            continue;

                        $related_pod_id = $related_pod[ 'id' ];
                    }
                }

                $sql = "
                    REPLACE INTO `@wp_pods_rel`
                    ( `id`, `pod_id`, `field_id`, `item_id`, `related_pod_id`, `related_field_id`, `related_item_id`, `weight` )
                    VALUES ( {$r->id}, {$pod_id}, {$field_id}, {$item_id}, {$related_pod_id}, {$related_field_id}, {$related_item_id}, {$r->weight} )
                ";

                pods_query( $sql );

                $last_id = $r->id;

                $x++;

                if ( 10 < $x ) {
                    $this->update_progress( __FUNCTION__, $last_id );

                    $x = 0;
                }
            }
        }

        $this->update_progress( __FUNCTION__, $last_id );

        if ( 800 == count( $rel ) )
            echo '-2';
        else
            echo '1';
    }

    function migrate_settings () {
        return $this->migrate_roles();
    }

    function migrate_roles () {
        if ( true === $this->check_progress( __FUNCTION__ ) )
            return '1';

        global $wpdb;

        $wp_roles = get_option( "{$wpdb->prefix}user_roles" );

        $old_roles = (array) @unserialize( get_option( 'pods_roles' ) );

        if ( !empty( $old_roles ) ) {
            foreach ( $old_roles as $role => $data ) {
                if ( $role == '_wpnonce' )
                    continue;

                $caps = $wp_roles[ $role ][ 'capabilities' ];

                foreach ( $data as $cap ) {
                    if ( 0 === strpos( 'manage_', $cap ) ) {
                        if ( in_array( $cap, array( 'manage_roles', 'manage_content' ) ) )
                            continue;

                        $cap = pods_str_replace( 'manage_', 'pods_', $cap, 1 );
                        $cap = pods_str_replace( 'pod_pages', 'pages', $cap, 1 );

                        $caps[ $cap ] = true;
                    }
                    elseif ( 0 === strpos( 'pod_', $cap ) ) {
                        $keys = array(
                            pods_str_replace( 'pod_', 'pods_new_', $cap, 1 ),
                            pods_str_replace( 'pod_', 'pods_edit_', $cap, 1 ),
                            pods_str_replace( 'pod_', 'pods_delete_', $cap, 1 ),
                        );

                        foreach ( $keys as $key ) {
                            $caps[ $key ] = true;
                        }
                    }
                }

                $wp_roles[ $role ][ 'capabilities' ] = $caps;
            }
        }

        update_option( "{$wpdb->prefix}user_roles", $wp_roles );

        $this->update_progress( __FUNCTION__, true );

        return '1';
    }

    function migrate_templates () {
        if ( true === $this->check_progress( __FUNCTION__ ) )
            return '1';

        $api = pods_api();

        $templates = pods_query( "SELECT * FROM `@wp_pod_templates`", false );

        $results = array();

        if ( empty( $templates ) )
            return $results;

        foreach ( $templates as $template ) {
            $params = array(
                'name' => $template->name,
                'code' => $template->code,
            );

            $results[] = $api->save_template( $params );
        }

        $this->update_progress( __FUNCTION__, true );

        return '1';
    }

    function migrate_pages () {
        if ( true === $this->check_progress( __FUNCTION__ ) )
            return '1';

        $api = pods_api();

        $pages = pods_query( "SELECT * FROM `@wp_pod_pages`", false );

        $results = array();

        if ( empty( $pages ) )
            return $results;

        foreach ( $pages as $page ) {
            $results[] = $api->save_page( $page );
        }

        $this->update_progress( __FUNCTION__, true );

        return '1';
    }

    function migrate_helpers () {
        if ( true === $this->check_progress( __FUNCTION__ ) )
            return '1';

        $api = pods_api();

        $helpers = pods_query( "SELECT * FROM `@wp_pod_helpers`", false );

        $results = array();

        if ( empty( $helpers ) )
            return $results;

        foreach ( $helpers as $helper ) {
            $params = array(
                'name' => $helper->name,
                'helper_type' => $helper->helper_type,
                'phpcode' => $helper->phpcode,
            );

            $results[] = $api->save_helper( $params );
        }

        $this->update_progress( __FUNCTION__, true );

        return '1';
    }

    function migrate_pod ( $params ) {
        global $wpdb;

        if ( !isset( $params->pod ) )
            return pods_error( __( 'Invalid Pod.', 'pods' ) );

        $pod = pods_sanitize( pods_clean_name( $params->pod ) );

        if ( !in_array( "{$wpdb->prefix}pod_tbl_{$pod}", $this->tables ) )
            return pods_error( __( 'Table not found, items cannot be migrated', 'pods' ) );

        if ( !in_array( "{$wpdb->prefix}pods_tbl_{$pod}", $this->tables ) )
            return pods_error( __( 'New table not found, items cannot be migrated', 'pods' ) );

        if ( !in_array( "{$wpdb->prefix}pod_types", $this->tables ) )
            return pods_error( __( 'Pod Types table not found, items cannot be migrated', 'pods' ) );

        if ( !in_array( "{$wpdb->prefix}pod", $this->tables ) )
            return pods_error( __( 'Pod table not found, items cannot be migrated', 'pods' ) );

        if ( true === $this->check_progress( __FUNCTION__, $pod ) )
            return '1';

        $pod_data = pods_api()->load_pod( array( 'name' => $pod ) );

        if ( empty( $pod_data ) )
            return pods_error( __( 'Pod not found, items cannot be migrated', 'pods' ) );

        $columns = array();

        foreach ( $pod_data[ 'fields' ] as $field ) {
            if ( !in_array( $field[ 'name' ], array( 'created', 'modified', 'author' ) ) && !in_array( $field[ 'type' ], array( 'file', 'pick' ) ) )
                $columns[] = pods_sanitize( $field[ 'name' ] );
        }

        $select = '`t`.`id`, `t`.`' . implode( '`, `t`.`', $columns ) . '`';
        $columns = '`id`, `' . implode( '`, `', $columns ) . '`';

        // Copy content from the old table into the new
        $sql = "
            REPLACE INTO `@wp_pods_tbl_{$pod}`
                ( {$columns} )
                ( SELECT {$select}
                  FROM `@wp_pod_tbl_{$pod}` AS `t` )
        ";

        pods_query( $sql );

        // Copy index data from the old index table into the new individual table
        $sql = "
            UPDATE `@wp_pods_tbl_{$pod}` AS `t`
            LEFT JOIN `@wp_pod_types` AS `x` ON `x`.`name` = '{$pod}'
            LEFT JOIN `@wp_pod` AS `p` ON `p`.`datatype` = `x`.`id` AND `p`.`tbl_row_id` = `t`.`id`
            SET `t`.`created` = `p`.`created`, `t`.`modified` = `p`.`modified`
            WHERE `x`.`id` IS NOT NULL AND `p`.`id` IS NOT NULL
        ";

        pods_query( $sql );

        // Copy name data from the old index table into the new individual table (if name empty in indiv table)
        $sql = "
            UPDATE `@wp_pods_tbl_{$pod}` AS `t`
            LEFT JOIN `@wp_pod_types` AS `x` ON `x`.`name` = '{$pod}'
            LEFT JOIN `@wp_pod` AS `p` ON `p`.`datatype` = `x`.`id` AND `p`.`tbl_row_id` = `t`.`id`
            SET `t`.`name` = `p`.`name`
            WHERE ( `t`.`name` IS NULL OR `t`.`name` = '' ) AND `x`.`id` IS NOT NULL AND `p`.`id` IS NOT NULL
        ";

        pods_query( $sql );

        $this->update_progress( __FUNCTION__, true, $pod );

        return '1';
    }

    function restart () {
        global $wpdb;

        foreach ( $this->table as $table ) {
            if ( false !== strpos( $table, "{$wpdb->prefix}pods" ) )
                pods_query( "TRUNCATE `{$table}`", false );
        }

        delete_option( 'pods_framework_upgrade_2_0' );
    }

    function cleanup () {
        global $wpdb;

        foreach ( $this->table as $table ) {
            if ( false !== strpos( $table, "{$wpdb->prefix}pod_" ) || "{$wpdb->prefix}pod" == $table )
                pods_query( "DROP TABLE `{$table}`", false );
        }

        delete_option( 'pods_roles' );
        delete_option( 'pods_version' );
        delete_option( 'pods_framework_upgrade_2_0' );

        /*
         * other options maybe not in 2.0
        delete_option( 'pods_disable_file_browser' );
        delete_option( 'pods_files_require_login' );
        delete_option( 'pods_files_require_login_cap' );
        delete_option( 'pods_disable_file_upload' );
        delete_option( 'pods_upload_require_login' );
        delete_option( 'pods_upload_require_login_cap' );
        delete_option( 'pods_page_precode_timing' );
        */
    }

    function update_progress ( $method, $v, $x = null ) {
        $method = str_replace( 'migrate_', '', $method );

        if ( null !== $x )
            $this->progress[ $method ][ $x ] = (boolean) $v;
        else
            $this->progress[ $method ] = $v;

        update_option( 'pods_framework_upgrade_2_0', $this->progress );
    }

    function check_progress ( $method, $x = null ) {
        $method = str_replace( 'migrate_', '', $method );

        if ( isset( $this->progress[ $method ] ) ) {
            if ( null === $x )
                return $this->progress[ $method ];
            elseif ( isset( $this->progress[ $method ][ $x ] ) )
                return (boolean) $this->progress[ $method ][ $x ];
        }

        return false;
    }
}
