<?php
class PodsInit {

    static $no_conflict = array();

    static $meta;

    public $version;

    /**
     * Setup and Initiate Pods
     *
     * @license http://www.gnu.org/licenses/gpl-2.0.html
     * @since 1.8.9
     */
    function __construct () {
        $this->version = get_option( 'pods_framework_version' );

        add_action( 'init', array( $this, 'activate_install' ), 9 );

        if ( !empty( $this->version ) ) {
            // Init Pods Form
            pods_form();

            add_action( 'init', array( $this, 'init' ), 11 );

            add_action( 'init', array( $this, 'setup_content_types' ), 11 );
            add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );

            if ( is_admin() )
                add_action( 'init', array( $this, 'admin_init' ), 12 );
            else
                add_action( 'init', array( $this, 'page_check' ), 12 );

            // Show admin bar links
            add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar_links' ) );

            // Init Pods Meta
            self::$meta = pods_meta()->init();
        }
    }

    function init () {
        // Session start
        if ( ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || false === headers_sent() ) && '' == session_id() )
            @session_start();

        load_plugin_textdomain( 'pods', false, basename( plugin_basename( __FILE__ ) ) . '/languages/' );

        add_shortcode( 'pods', 'pods_shortcode' );

        $security_settings = array(
            'pods_disable_file_browser' => 0,
            'pods_files_require_login' => 1,
            'pods_files_require_login_cap' => '',
            'pods_disable_file_upload' => 0,
            'pods_upload_require_login' => 1,
            'pods_upload_require_login_cap' => ''
        );

        foreach ( $security_settings as $security_setting => $setting ) {
            $setting = get_option( $security_setting );
            if ( !empty( $setting ) )
                $security_settings[ $security_setting ] = $setting;
        }

        foreach ( $security_settings as $security_setting => $setting ) {
            if ( 0 == $setting )
                $setting = false;
            elseif ( 1 == $setting )
                $setting = true;

            if ( in_array( $security_setting, array( 'pods_files_require_login', 'pods_upload_require_login' ) ) ) {
                if ( 0 < strlen( $security_settings[ $security_setting . '_cap' ] ) )
                    $setting = $security_settings[ $security_setting . '_cap' ];
            }
            elseif ( in_array( $security_setting, array( 'pods_files_require_login_cap', 'pods_upload_require_login_cap' ) ) )
                continue;

            if ( !defined( strtoupper( $security_setting ) ) )
                define( strtoupper( $security_setting ), $setting );
        }

        $this->register_assets();

        $this->register_pods();

        add_action( 'widgets_init', array( $this, 'register_widgets' ) );
    }

    function register_assets () {
        if ( !wp_style_is( 'jquery-ui', 'registered' ) )
            wp_register_style( 'jquery-ui', PODS_URL . 'ui/css/smoothness/jquery-ui.custom.css', array(), '1.8.16' );

        wp_register_script( 'pods-json', PODS_URL . 'ui/js/jquery.json.js', array( 'jquery' ), '2.3' );

        wp_register_style( 'pods-qtip', PODS_URL . 'ui/css/jquery.qtip.min.css', array(), '2.0-2012-07-03' );
        wp_register_script( 'pods-qtip', PODS_URL . 'ui/js/jquery.qtip.min.js', array( 'jquery' ), '2.0-2012-07-03' );

        wp_register_style( 'pods', PODS_URL . 'ui/css/pods-form.css', array(), PODS_VERSION );
        wp_register_script( 'pods', PODS_URL . 'ui/js/jquery.pods.js', array(
            'jquery',
            'pods-json',
            'pods-qtip'
        ), PODS_VERSION );

        wp_register_style( 'pods-cleditor', PODS_URL . 'ui/css/jquery.cleditor.css', array(), '1.3.0' );
        wp_register_script( 'pods-cleditor-min', PODS_URL . 'ui/js/jquery.cleditor.min.js', array( 'jquery' ), '1.3.0' );

        if ( !wp_style_is( 'jquery-ui-timepicker', 'registered' ) )
            wp_register_style( 'jquery-ui-timepicker', PODS_URL . 'ui/css/jquery.ui.timepicker.css', array(), '1.0.1' );

        if ( !wp_script_is( 'jquery-ui-timepicker', 'registered' ) ) {
            wp_register_script( 'jquery-ui-timepicker', PODS_URL . 'ui/js/jquery.ui.timepicker.min.js', array(
                'jquery',
                'jquery-ui-core',
                'jquery-ui-datepicker',
                'jquery-ui-slider'
            ), '1.0.1' );
        }

        wp_register_style( 'pods-attach', PODS_URL . 'ui/js/jquery.pods.attach.css', array(), PODS_VERSION );
        wp_register_script( 'pods-attach', PODS_URL . 'ui/js/jquery.pods.attach.js', array(), PODS_VERSION );

        wp_register_style( 'pods-select2', PODS_URL . 'ui/css/select2.css', array(), '2.1' );
        wp_register_script( 'pods-select2', PODS_URL . 'ui/js/select2.js', array( 'jquery' ), '2.1' );

        wp_register_script( 'pods-handlebars', PODS_URL . 'ui/js/handlebars.js', array(), '1.0.0.beta.6' );
    }

    function register_pods () {
        $args = array(
            'label' => 'Pods',
            'labels' => array( 'singular_name' => 'Pod' ),
            'public' => false,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'pods_pod',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => array( 'title', 'content', 'author' )
        );
        register_post_type( '_pods_pod', apply_filters( 'pods_internal_register_post_type_pod', $args ) );

        $args = array(
            'label' => 'Pod Fields',
            'labels' => array( 'singular_name' => 'Pod Field' ),
            'public' => false,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'pods_pod',
            'has_archive' => false,
            'hierarchical' => true,
            'supports' => array( 'title', 'content', 'author' )
        );
        register_post_type( '_pods_field', apply_filters( 'pods_internal_register_post_type_field', $args ) );

        $args = array(
            'label' => 'Pod Templates',
            'labels' => array( 'singular_name' => 'Pod Template' ),
            'public' => false,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'pods_object_template',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => array( 'title', 'content', 'author' )
        );
        register_post_type( '_pods_object_template', apply_filters( 'pods_internal_register_post_type_object_template', $args ) );

        $args = array(
            'label' => 'Pod Pages',
            'labels' => array( 'singular_name' => 'Pod Page' ),
            'public' => false,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'pods_object_page',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => array( 'title', 'content', 'author' )
        );
        register_post_type( '_pods_object_page', apply_filters( 'pods_internal_register_post_type_object_page', $args ) );

        $args = array(
            'label' => 'Pod Helpers',
            'labels' => array( 'singular_name' => 'Pod Helper' ),
            'public' => false,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'pods_object_helper',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => array( 'title', 'content', 'author' )
        );
        register_post_type( '_pods_object_helper', apply_filters( 'pods_internal_register_post_type_object_helper', $args ) );
    }

    function admin_init () {
        global $pods_admin;

        $pods_admin = pods_admin();
    }

    function setup_content_types () {
        $post_types = PodsMeta::$post_types;
        $taxonomies = PodsMeta::$taxonomies;

        $existing_post_types = get_post_types();
        $existing_taxonomies = get_taxonomies();


        $wp_cpt_ct = get_transient( 'pods_wp_cpt_ct' );

        if ( false === $wp_cpt_ct ) {
            $wp_cpt_ct = array(
                'post_types' => array(),
                'taxonomies' => array()
            );

            $wp_post_types = $wp_taxonomies = array();
            $supported_post_types = $supported_taxonomies = array();

            foreach ( $post_types as $post_type ) {
                // Post Type exists already
                if ( empty( $post_type[ 'object' ] ) && isset( $existing_post_types[ $post_type[ 'name' ] ] ) )
                    continue;

                if ( !empty( $post_type[ 'object' ] ) && isset( $existing_post_types[ $post_type[ 'object' ] ] ) )
                    continue;

                $post_type[ 'options' ][ 'name' ] = $post_type[ 'name' ];
                $post_type = array_merge( $post_type, (array) $post_type[ 'options' ] );

                // Labels
                $cpt_label = esc_html( pods_var( 'label', $post_type, ucwords( str_replace( '_', ' ', pods_var( 'name', $post_type ) ) ), null, true ) );
                $cpt_singular = esc_html( pods_var( 'label_singular', $post_type, ucwords( str_replace( '_', ' ', pods_var( 'label', $post_type, pods_var( 'name', $post_type ), null, true ) ) ), null, true ) );
                $cpt_labels[ 'name' ] = $cpt_label;
                $cpt_labels[ 'singular_name' ] = $cpt_singular;
                $cpt_labels[ 'menu_name' ] = pods_var( 'menu_name', $post_type, $cpt_label, null, true );
                $cpt_labels[ 'add_new' ] = pods_var( 'add_new', $post_type, 'Add ' . $cpt_singular, null, true );
                $cpt_labels[ 'add_new_item' ] = pods_var( 'add_new_item', $post_type, 'Add New ' . $cpt_singular, null, true );
                $cpt_labels[ 'new_item' ] = pods_var( 'new_item', $post_type, 'New ' . $cpt_singular, null, true );
                $cpt_labels[ 'edit' ] = pods_var( 'edit', $post_type, 'Edit', null, true );
                $cpt_labels[ 'edit_item' ] = pods_var( 'edit_item', $post_type, 'Edit ' . $cpt_singular, null, true );
                $cpt_labels[ 'view' ] = pods_var( 'view', $post_type, 'View ' . $cpt_singular, null, true );
                $cpt_labels[ 'view_item' ] = pods_var( 'view_item', $post_type, 'View ' . $cpt_singular, null, true );
                $cpt_labels[ 'all_items' ] = pods_var( 'all_items', $post_type, 'All ' . $cpt_singular, null, true );
                $cpt_labels[ 'search_items' ] = pods_var( 'search_items', $post_type, 'Search ' . $cpt_label, null, true );
                $cpt_labels[ 'not_found' ] = pods_var( 'not_found', $post_type, 'No ' . $cpt_label . ' Found', null, true );
                $cpt_labels[ 'not_found_in_trash' ] = pods_var( 'not_found_in_trash', $post_type, 'No ' . $cpt_label . ' Found in Trash', null, true );
                $cpt_labels[ 'parent' ] = pods_var( 'parent', $post_type, 'Parent ' . $cpt_singular, null, true );
                $cpt_labels[ 'parent_item_colon' ] = pods_var( 'parent_item_colon', $post_type, 'Parent ' . $cpt_singular . ':', null, true );

                // Supported
                $cpt_supported = array(
                    'title' => (boolean) pods_var( 'supports_title', $post_type, true ),
                    'editor' => (boolean) pods_var( 'supports_editor', $post_type, true ),
                    'author' => (boolean) pods_var( 'supports_author', $post_type, false ),
                    'thumbnail' => (boolean) pods_var( 'supports_thumbnail', $post_type, false ),
                    'excerpt' => (boolean) pods_var( 'supports_excerpt', $post_type, false ),
                    'trackbacks' => (boolean) pods_var( 'supports_trackbacks', $post_type, false ),
                    'custom-fields' => (boolean) pods_var( 'supports_custom_fields', $post_type, false ),
                    'comments' => (boolean) pods_var( 'supports_comments', $post_type, false ),
                    'revisions' => (boolean) pods_var( 'supports_revisions', $post_type, false ),
                    'page-attributes' => (boolean) pods_var( 'supports_page_attributes', $post_type, false ),
                    'post-formats' => (boolean) pods_var( 'supports_post_formats', $post_type, false )
                );
                $cpt_supports = array();

                foreach ( $cpt_supported as $cpt_support => $cpt_supported ) {
                    if ( false !== $cpt_supported )
                        $cpt_supports[] = $cpt_support;
                }

                // Rewrite
                $cpt_rewrite = pods_var( 'rewrite', $post_type, true );
                $cpt_rewrite_array = array(
                    'slug' => pods_var( 'custom_rewrite_slug', $post_type, pods_var( 'name', $post_type ) ),
                    'with_front' => (boolean) pods_var( 'rewrite_with_front', $post_type, true ),
                    'feeds' => pods_var( 'rewrite_feeds', $post_type, pods_var( 'has_archive', $post_type, false ) ),
                    'pages' => (boolean) pods_var( 'rewrite_pages', $post_type, true )
                );

                if ( false !== $cpt_rewrite )
                    $cpt_rewrite = $cpt_rewrite_array;

                $capability_type = pods_var( 'capability_type', $post_type, 'post' );

                if ( 'custom' == $capability_type )
                    $capability_type = pods_var( 'capability_type_custom', $post_type, 'post' );

                // Register Post Type
                $wp_post_types[ pods_var( 'name', $post_type ) ] = array(
                    'label' => $cpt_label,
                    'labels' => $cpt_labels,
                    'description' => esc_html( pods_var( 'description', $post_type ) ),
                    'public' => (boolean) pods_var( 'public', $post_type, false ),
                    'publicly_queryable' => (boolean) pods_var( 'publicly_queryable', $post_type, (boolean) pods_var( 'public', $post_type, false ) ),
                    'exclude_from_search' => (boolean) pods_var( 'exclude_from_search', $post_type, ( pods_var( 'public', $post_type, false ) ? false : true ) ),
                    'show_ui' => (boolean) pods_var( 'show_ui', $post_type, (boolean) pods_var( 'public', $post_type, false ) ),
                    'show_in_menu' => (boolean) pods_var( 'show_in_menu', $post_type, true ),
                    'show_in_admin_bar' => (boolean) pods_var( 'show_in_admin_bar', $post_type, (boolean) pods_var( 'show_in_menu', $post_type, true ) ),
                    'menu_position' => (int) pods_var( 'menu_position', $post_type, 20, null, true ),
                    'menu_icon' => pods_var( 'menu_icon', $post_type, null, null, true ),
                    'capability_type' => $capability_type,
                    //'capabilities' => $cpt_capabilities,
                    'map_meta_cap' => (boolean) pods_var( 'map_meta_cap', $post_type, true ),
                    'hierarchical' => (boolean) pods_var( 'hierarchical', $post_type, false ),
                    'supports' => $cpt_supports,
                    //'register_meta_box_cb' => array($this, 'manage_meta_box'),
                    //'permalink_epmask' => EP_PERMALINK,
                    'has_archive' => (boolean) pods_var( 'has_archive', $post_type, false ),
                    'rewrite' => $cpt_rewrite,
                    'query_var' => ( false !== pods_var( 'query_var', $post_type, true ) ? pods_var( 'query_var_string', $post_type, pods_var( 'name', $post_type ) ) : false ),
                    'can_export' => (boolean) pods_var( 'can_export', $post_type, true ),
                    'show_in_nav_menus' => (boolean) pods_var( 'show_in_nav_menus', $post_type, (boolean) pods_var( 'public', $post_type, false ) )
                );

                // Taxonomies
                $cpt_taxonomies = array();
                $_taxonomies = get_taxonomies();
                $_taxonomies = array_merge_recursive( $_taxonomies, $wp_taxonomies );
                $ignore = array( 'nav_menu', 'link_category', 'post_format' );

                foreach ( $_taxonomies as $taxonomy => $label ) {
                    if ( in_array( $taxonomy, $ignore ) )
                        continue;

                    if ( false !== (boolean) pods_var( 'built_in_taxonomies_' . $taxonomy, $post_type, false ) ) {
                        $cpt_taxonomies[] = $taxonomy;

                        if ( isset( $supported_post_types[ $taxonomy ] ) && !in_array( pods_var( 'name', $post_type ), $supported_post_types[ $taxonomy ] ) )
                            $supported_post_types[ $taxonomy ][] = pods_var( 'name', $post_type );
                    }
                }

                if ( isset( $supported_taxonomies[ pods_var( 'name', $post_type ) ] ) )
                    $supported_taxonomies[ pods_var( 'name', $post_type ) ] = array_merge( (array) $supported_taxonomies[ pods_var( 'name', $post_type ) ], $cpt_taxonomies );
                else
                    $supported_taxonomies[ pods_var( 'name', $post_type ) ] = $cpt_taxonomies;
            }

            foreach ( $taxonomies as $taxonomy ) {
                // Taxonomy exists already
                if ( empty( $taxonomy[ 'object' ] ) && isset( $existing_taxonomies[ $taxonomy[ 'name' ] ] ) )
                    continue;

                if ( !empty( $taxonomy[ 'object' ] ) && isset( $existing_taxonomies[ $taxonomy[ 'object' ] ] ) )
                    continue;

                $taxonomy[ 'options' ][ 'name' ] = $taxonomy[ 'name' ];
                $taxonomy = array_merge( $taxonomy, (array) $taxonomy[ 'options' ] );

                // Labels
                $ct_label = esc_html( pods_var( 'label', $taxonomy, pods_var( 'name', $taxonomy ), null, true ) );
                $ct_singular = esc_html( pods_var( 'label_singular', $taxonomy, pods_var( 'label', $taxonomy, pods_var( 'name', $taxonomy ), null, true ), null, true ) );
                $ct_labels[ 'name' ] = $ct_label;
                $ct_labels[ 'singular_name' ] = $ct_singular;
                $ct_labels[ 'search_items' ] = pods_var( 'search_items', $taxonomy, 'Search ' . $ct_label, null, true );
                $ct_labels[ 'popular_items' ] = pods_var( 'popular_items', $taxonomy, 'Popular ' . $ct_label, null, true );
                $ct_labels[ 'all_items' ] = pods_var( 'all_items', $taxonomy, 'All ' . $ct_label, null, true );
                $ct_labels[ 'parent_item' ] = pods_var( 'parent_item', $taxonomy, 'Parent ' . $ct_singular, null, true );
                $ct_labels[ 'parent_item_colon' ] = pods_var( 'parent_item_colon', $taxonomy, 'Parent ' . $ct_singular . ':', null, true );
                $ct_labels[ 'edit_item' ] = pods_var( 'edit_item', $taxonomy, 'Edit ' . $ct_singular, null, true );
                $ct_labels[ 'update_item' ] = pods_var( 'update_item', $taxonomy, 'Update ' . $ct_singular, null, true );
                $ct_labels[ 'add_new_item' ] = pods_var( 'add_new_item', $taxonomy, 'Add New ' . $ct_singular, null, true );
                $ct_labels[ 'new_item_name' ] = pods_var( 'new_item_name', $taxonomy, 'New ' . $ct_singular . ' Name', null, true );
                $ct_labels[ 'separate_items_with_commas' ] = pods_var( 'separate_items_with_commas', $taxonomy, 'Separate ' . $ct_label . ' with commas', null, true );
                $ct_labels[ 'add_or_remove_items' ] = pods_var( 'add_or_remove_items', $taxonomy, 'Add or remove ' . $ct_label, null, true );
                $ct_labels[ 'choose_from_most_used' ] = pods_var( 'choose_from_most_used', $taxonomy, 'Choose from the most used ' . $ct_label, null, true );
                $ct_labels[ 'menu_name' ] = pods_var( 'menu_name', $taxonomy, $ct_label, null, true );

                // Rewrite
                $ct_rewrite = pods_var( 'rewrite', $taxonomy, true );
                $ct_rewrite_array = array(
                    'slug' => pods_var( 'custom_rewrite_slug', $taxonomy, pods_var( 'name', $taxonomy ) ),
                    'with_front' => (boolean) pods_var( 'rewrite_with_front', $taxonomy, true ),
                    'hierarchical' => pods_var( 'rewrite_hierarchical', $taxonomy, pods_var( 'hierarchical', $taxonomy, false ) )
                );

                if ( false !== $ct_rewrite )
                    $ct_rewrite = $ct_rewrite_array;

                // Register Taxonomy
                $wp_taxonomies[ pods_var( 'name', $taxonomy ) ] = array(
                    'label' => $ct_label,
                    'labels' => $ct_labels,
                    'public' => (boolean) pods_var( 'public', $taxonomy, true ),
                    'show_in_nav_menus' => (boolean) pods_var( 'show_in_nav_menus', $taxonomy, pods_var( 'public', $taxonomy, true ) ),
                    'show_ui' => (boolean) pods_var( 'show_ui', $taxonomy, pods_var( 'public', $taxonomy, true ) ),
                    'show_tagcloud' => (boolean) pods_var( 'show_tagcloud', $taxonomy, pods_var( 'show_ui', $taxonomy, pods_var( 'public', $taxonomy, true ) ) ),
                    'hierarchical' => (boolean) pods_var( 'hierarchical', $taxonomy ),
                    //'update_count_callback' => pods_var('update_count_callback', $taxonomy),
                    'query_var' => ( false !== pods_var( 'query_var', $taxonomy, true ) ? pods_var( 'query_var_string', $taxonomy, pods_var( 'name', $taxonomy ) ) : false ),
                    'rewrite' => $ct_rewrite
                );

                // Post Types
                $ct_post_types = array();
                $_post_types = get_post_types();
                $_post_types = array_merge_recursive( $_post_types, $wp_post_types );
                $ignore = array( 'attachment', 'revision', 'nav_menu_item' );

                foreach ( $_post_types as $post_type => $options ) {
                    if ( in_array( $post_type, $ignore ) )
                        continue;

                    if ( false !== (boolean) pods_var( 'built_in_post_types_' . $post_type, $taxonomy, false ) ) {
                        $ct_post_types[] = $post_type;

                        if ( isset( $supported_taxonomies[ $post_type ] ) && !in_array( pods_var( 'name', $taxonomy ), $supported_taxonomies[ $post_type ] ) )
                            $supported_taxonomies[ $post_type ][] = pods_var( 'name', $taxonomy );
                    }
                }

                if ( isset( $supported_post_types[ pods_var( 'name', $taxonomy ) ] ) )
                    $supported_post_types[ pods_var( 'name', $taxonomy ) ] = array_merge( $supported_post_types[ pods_var( 'name', $taxonomy ) ], $ct_post_types );
                else
                    $supported_post_types[ pods_var( 'name', $taxonomy ) ] = $ct_post_types;
            }

            $wp_post_types = apply_filters( 'pods_wp_post_types', $wp_post_types );
            $wp_taxonomies = apply_filters( 'pods_wp_taxonomies', $wp_taxonomies );

            $supported_post_types = apply_filters( 'pods_wp_supported_post_types', $supported_post_types );
            $supported_taxonomies = apply_filters( 'pods_wp_supported_taxonomies', $supported_taxonomies );

            foreach ( $wp_taxonomies as $taxonomy => $options ) {
                $ct_post_types = null;
                if ( isset( $supported_post_types[ $taxonomy ] ) && !empty( $supported_post_types[ $taxonomy ] ) )
                    $ct_post_types = $supported_post_types[ $taxonomy ];

                $wp_cpt_ct[ 'taxonomies' ][ $taxonomy ] = array(
                    'post_types' => $ct_post_types,
                    'options' => $options
                );
            }

            foreach ( $wp_post_types as $post_type => $options ) {
                if ( isset( $supported_taxonomies[ $post_type ] ) && !empty( $supported_taxonomies[ $post_type ] ) )
                    $options[ 'taxonomies' ] = $supported_taxonomies[ $post_type ];

                $wp_cpt_ct[ 'post_types' ][ $post_type ] = $options;
            }

            set_transient( 'pods_wp_cpt_ct', $wp_cpt_ct );
        }

        foreach ( $wp_cpt_ct[ 'taxonomies' ] as $taxonomy => $options ) {
            $ct_post_types = $options[ 'post_types' ];
            $options = $options[ 'options' ];

            $options = apply_filters( 'pods_register_taxonomy_' . $taxonomy, $options );

            register_taxonomy( $taxonomy, $ct_post_types, $options );
        }

        foreach ( $wp_cpt_ct[ 'post_types' ] as $post_type => $options ) {
            $options = apply_filters( 'pods_register_post_type_' . $post_type, $options );

            register_post_type( $post_type, $options );
        }
    }

    function page_check () {
        global $pod_page_exists, $pods;

        if ( !defined( 'PODS_DISABLE_POD_PAGE_CHECK' ) ) {
            if ( null === $pod_page_exists )
                $pod_page_exists = pod_page_exists();

            if ( false !== $pod_page_exists ) {
                $pods = apply_filters( 'pods_global', $pods, $pod_page_exists );

                if ( 404 != $pods && ( !is_object( $pods ) || !is_wp_error( $pods ) ) ) {
                    add_action( 'template_redirect', array( $this, 'template_redirect' ) );
                    add_filter( 'redirect_canonical', '__return_false' );
                    add_action( 'wp_head', array( $this, 'wp_head' ) );
                    add_filter( 'wp_title', array( $this, 'wp_title' ), 0, 3 );
                    add_filter( 'body_class', array( $this, 'body_class' ), 0, 1 );
                    add_filter( 'status_header', array( $this, 'status_header' ) );
                    add_action( 'after_setup_theme', array( $this, 'precode' ) );
                    add_action( 'wp', array( $this, 'silence_404' ) );
                }
            }
        }
    }

    function activate_install () {
        // Activate and Install
        // @todo: VIP constant check, display notice with a link for user to run install instead of auto install
        register_activation_hook( __FILE__, array( $this, 'activate' ) );

        add_action( 'wpmu_new_blog', array( $this, 'new_blog' ), 10, 6 );

        $pods_version = $this->version;

        if ( empty( $pods_version ) || false === strpos( $pods_version, '.' ) || version_compare( $pods_version, PODS_VERSION, '<' ) )
            $this->setup();
    }

    function activate () {
        global $wpdb;
        if ( function_exists( 'is_multisite' ) && is_multisite() && isset( $_GET[ 'networkwide' ] ) && 1 == $_GET[ 'networkwide' ] ) {
            $_blog_ids = $wpdb->get_col( $wpdb->prepare( "SELECT `blog_id` FROM {$wpdb->blogs}" ) );

            foreach ( $_blog_ids as $_blog_id ) {
                $this->setup( $_blog_id );
            }
        }
        else
            $this->setup();
    }

    function new_blog ( $_blog_id, $user_id, $domain, $path, $site_id, $meta ) {
        if ( function_exists( 'is_multisite' ) && is_multisite() && is_plugin_active_for_network( 'pods/pods.php' ) )
            $this->setup( $_blog_id );
    }

    function setup ( $_blog_id = null ) {
        global $wpdb;

        // Switch DB table prefixes
        if ( null !== $_blog_id && $_blog_id != $wpdb->blogid )
            switch_to_blog( pods_absint( $_blog_id ) );
        else
            $_blog_id = null;

        // Setup DB tables
        $pods_version = $this->version;

        if ( 0 < strlen( $pods_version ) ) {
            if ( false === strpos( $pods_version, '.' ) )
                $pods_version = pods_version_to_point( $pods_version );

            if ( version_compare( $pods_version, '1.15', '<' ) ) {
                do_action( 'pods_update', PODS_VERSION, $pods_version, $_blog_id );

                if ( false !== apply_filters( 'pods_update_run', null, PODS_VERSION, $pods_version, $_blog_id ) && !isset( $_GET[ 'pods_bypass_update' ] ) )
                    include( PODS_DIR . 'sql/update.1.x.php' );

                do_action( 'pods_update_post', PODS_VERSION, $pods_version, $_blog_id );
            }

            if ( version_compare( $pods_version, PODS_VERSION, '<' ) ) {
                do_action( 'pods_update', PODS_VERSION, $pods_version, $_blog_id );

                if ( false !== apply_filters( 'pods_update_run', null, PODS_VERSION, $pods_version, $_blog_id ) && !isset( $_GET[ 'pods_bypass_update' ] ) )
                    include( PODS_DIR . 'sql/update.php' );

                do_action( 'pods_update_post', PODS_VERSION, $pods_version, $_blog_id );
            }
        }
        else {
            do_action( 'pods_install', PODS_VERSION, $pods_version, $_blog_id );

            if ( false !== apply_filters( 'pods_install_run', null, PODS_VERSION, $pods_version, $_blog_id ) && !isset( $_GET[ 'pods_bypass_install' ] ) ) {
                $sql = file_get_contents( PODS_DIR . 'sql/dump.sql' );
                $sql = apply_filters( 'pods_install_sql', $sql, PODS_VERSION, $pods_version, $_blog_id );

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

            do_action( 'pods_install_post', PODS_VERSION, $pods_version, $_blog_id );
        }

        delete_option( 'pods_framework_version' );
        add_option( 'pods_framework_version', PODS_VERSION );

        // Restore DB table prefix (if switched)
        if ( null !== $_blog_id )
            restore_current_blog();
    }

    // Delete Attachments from relationships
    function delete_attachment ( $_ID ) {
        global $wpdb;

        do_action( 'pods_delete_attachment', $_ID );

        pods_query( "DELETE rel FROM `@wp_pods_rel` AS rel
            LEFT JOIN {$wpdb->posts} AS p
                ON p.`post_type` = '_pods_field' AND ( p.ID = rel.`field_id` OR p.ID = rel.`related_field_id` )
            LEFT JOIN {$wpdb->postmeta} AS pm
                ON pm.`post_id` = p.`ID` AND pm.`meta_key` = 'type' AND pm.`meta_value` = 'file'
            WHERE p.`ID` IS NOT NULL AND pm.`meta_id` IS NOT NULL AND rel.`item_id` = " . (int) $_ID );
    }

    // Pod Page Code
    function precode () {
        global $pods, $pod_page_exists;

        if ( false !== $pod_page_exists ) {
            $content = false;
            if ( 0 < strlen( trim( $pod_page_exists[ 'precode' ] ) ) )
                $content = $pod_page_exists[ 'precode' ];

            if ( false !== $content && !defined( 'PODS_DISABLE_EVAL' ) || PODS_DISABLE_EVAL ) {
                pods_deprecated( 'Use WP Page Templates or hook into the pods_page_precode action instead of using Pod Page Precode', '2.1.0' );

                eval( "?>$content" );
            }

            do_action( 'pods_page_precode', $pod_page_exists, $pods );

            if ( !is_object( $pods ) && ( 404 == $pods || is_wp_error( $pods ) ) ) {
                remove_action( 'template_redirect', array( $this, 'template_redirect' ) );
                remove_action( 'wp_head', array( $this, 'wp_head' ) );
                remove_filter( 'redirect_canonical', '__return_false' );
                remove_filter( 'wp_title', array( $this, 'wp_title' ) );
                remove_filter( 'body_class', array( $this, 'body_class' ) );
                remove_filter( 'status_header', array( $this, 'status_header' ) );
                remove_action( 'wp', array( $this, 'silence_404' ) );
            }
        }
    }

    function wp_head () {
        global $pods;

        do_action( 'pods_wp_head' );

        if ( !defined( 'PODS_DISABLE_VERSION_OUTPUT' ) || !PODS_DISABLE_VERSION_OUTPUT ) {
?>
    <!-- Pods Framework <?php echo PODS_VERSION; ?> -->
<?php
        }
        if ( ( !defined( 'PODS_DISABLE_META' ) || !PODS_DISABLE_META ) && is_object( $pods ) && !is_wp_error( $pods ) ) {

            if ( isset( $pods->meta ) && is_array( $pods->meta ) ) {
                foreach ( $pods->meta as $name => $content ) {
                    if ( 'title' == $name )
                        continue;
?>
    <meta name="<?php echo esc_attr( $name ); ?>" content="<?php echo esc_attr( $content ); ?>" />
<?php
                }
            }

            if ( isset( $pods->meta_properties ) && is_array( $pods->meta_properties ) ) {
                foreach ( $pods->meta_properties as $property => $content ) {
?>
    <meta property="<?php echo esc_attr( $property ); ?>" content="<?php echo esc_attr( $content ); ?>" />
<?php
                }
            }

            if ( isset( $pods->meta_extra ) && 0 < strlen( $pods->meta_extra ) )
                echo $pods->meta_extra;
        }
    }

    function wp_title ( $title, $sep, $seplocation ) {
        global $pods, $pod_page_exists;

        $page_title = $pod_page_exists[ 'title' ];

        if ( 0 < strlen( trim( $page_title ) ) ) {
            if ( is_object( $pods ) && !is_wp_error( $pods ) )
                $page_title = preg_replace_callback( "/({@(.*?)})/m", array( $pods, "parse_magic_tags" ), $page_title );

            $title = ( 'right' == $seplocation ) ? $page_title . " $sep " : " $sep " . $page_title;
        }
        else {
            $uri = explode( '?', $_SERVER[ 'REQUEST_URI' ] );
            $uri = preg_replace( "@^([/]?)(.*?)([/]?)$@", "$2", $uri[ 0 ] );
            $uri = preg_replace( "@(-|_)@", " ", $uri );
            $uri = explode( '/', $uri );

            $title = '';

            foreach ( $uri as $key => $page_title ) {
                $title .= ( 'right' == $seplocation ) ? ucwords( $page_title ) . " $sep " : " $sep " . ucwords( $page_title );
            }
        }

        if ( ( !defined( 'PODS_DISABLE_META' ) || !PODS_DISABLE_META ) && is_object( $pods ) && !is_wp_error( $pods ) && isset( $pods->meta ) && is_array( $pods->meta ) && isset( $pods->meta[ 'title' ] ) )
            $title = $pods->meta[ 'title' ];

        return apply_filters( 'pods_title', $title, $sep, $seplocation );
    }

    function body_class ( $classes ) {
        global $pods, $pod_page_exists;

        $classes[] = 'pods';

        $uri = explode( '?', $pod_page_exists[ 'uri' ] );
        $uri = explode( '#', $uri[ 0 ] );

        $class = str_replace( array( '*', '/' ), array( '_w_', '-' ), $uri[ 0 ] );
        $class = sanitize_title( $class );
        $class = str_replace( array( '_', '--', '--' ), '-', $class );
        $class = trim( $class, '-' );

        $classes[] = 'pod-page-' . $class;

        if ( is_object( $pods ) && !is_wp_error( $pods ) ) {
            $class = sanitize_title( $pods->pod );
            $class = str_replace( array( '_', '--', '--' ), '-', $class );
            $class = trim( $class, '-' );
            $classes[] = 'pod-' . $class;
        }

        if ( ( !defined( 'PODS_DISABLE_BODY_CLASSES' ) || !PODS_DISABLE_BODY_CLASSES ) && is_object( $pods ) && !is_wp_error( $pods ) && isset( $pods->body_classes ) )
            $classes[] = $pods->body_classes;

        return apply_filters( 'pods_body_class', $classes, $uri );
    }

    function status_header () {
        return $_SERVER[ 'SERVER_PROTOCOL' ] . ' 200 OK';
    }

    function silence_404 () {
        global $wp_query;

        $wp_query->query_vars[ 'error' ] = '';
        $wp_query->is_404 = false;
    }

    function template_redirect () {
        global $pods, $pod_page_exists;

        if ( false !== $pod_page_exists ) {
            /*
             * Create pods.php in your theme directory, and
             * style it to suit your needs. Some helpful functions:
             *
             * get_header()
             * pods_content()
             * get_sidebar()
             * get_footer()
             */
            $template = $pod_page_exists[ 'page_template' ];
            $template = apply_filters( 'pods_page_template', $template, $pod_page_exists );

            $render_function = apply_filters( 'pods_template_redirect', 'false', $template, $pod_page_exists );

            do_action( 'pods_page', $template, $pod_page_exists );

            if ( is_callable( $render_function ) )
                call_user_func( $render_function );
            elseif ( ( !defined( 'PODS_DISABLE_DYNAMIC_TEMPLATE' ) || !PODS_DISABLE_DYNAMIC_TEMPLATE ) && is_object( $pods ) && !is_wp_error( $pods ) && isset( $pods->page_template ) && !empty( $pods->page_template ) && '' != locate_template( array( $pods->page_template ), true ) ) {
                $template = $pods->page_template;
                // found the template and included it, we're good to go!
            }
            elseif ( !empty( $pod_page_exists[ 'page_template' ] ) && '' != locate_template( array( $pod_page_exists[ 'page_template' ] ), true ) ) {
                $template = $pod_page_exists[ 'page_template' ];
                // found the template and included it, we're good to go!
            }
            elseif ( '' != locate_template( apply_filters( 'pods_page_default_templates', array( 'pods.php' ) ), true ) ) {
                $template = 'pods.php';
                // found the template and included it, we're good to go!
            }
            else {
                // templates not found in theme, default output
                do_action( 'pods_page_default', $template, $pod_page_exists );

                get_header();
                pods_content();
                get_sidebar();
                get_footer();
            }

            do_action( 'pods_page_end', $template, $pod_page_exists );

            exit;
        }
    }

    function register_widgets () {
        $widgets = array(
            'PodsWidgetSingle',
            'PodsWidgetList',
            'PodsWidgetColumn'
        );

        if ( defined( 'PODS_DEVELOPER' ) && PODS_DEVELOPER )
            $widgets[] = 'PodsWidgetForm';

        foreach ( $widgets as $widget ) {
            require_once PODS_DIR . 'classes/widgets/' . basename( $widget ) . '.php';

            register_widget( $widget );
        }
    }

    public function admin_bar_links () {
        global $wp_admin_bar, $pods;
        $api = pods_api();
        $all_pods = $api->load_pods();
        $non_cpt_pods = array();

        // Round up all the non-CPT pod types
        foreach ( $all_pods as $pod ) {
            if ( $pod[ 'type' ] == "pod" )
                $non_cpt_pods[] = $pod;
        }

        // Add New item links for all non-CPT pods
        foreach ( $non_cpt_pods as $pod ) {
            $label = isset( $pod[ 'options' ][ 'label' ] ) ? $pod[ 'options' ][ 'label' ] : $pod[ 'name' ];
            $wp_admin_bar->add_node( array(
                'id' => 'new-pod-' . $pod[ 'name' ],
                'title' => $label,
                'parent' => 'new-content',
                'href' => admin_url( 'admin.php?page=pods-manage-' . $pod[ 'name' ] . '&action=add' )
            ) );
        }

        // Add edit link if we're on a pods page (this requires testing)
        // @todo Fill in correct href and test this once PodsAPI is capable of adding new pod items to the database
        if ( is_object( $pods ) && !is_wp_error( $pods ) && isset( $pods->id ) ) {
            $wp_admin_bar->add_node( array(
                'title' => 'Edit Pod Item',
                'id' => 'edit-pod',
                'href' => '#'
            ) );
        }

    }
}