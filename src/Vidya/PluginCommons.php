<?php
namespace Vidya;

//require_once 'functions.php';
//require_once 'vendor/autoload.php';

require_once "REST/Controller.php";
require_once 'REST/models/PostModel.php';
require_once 'REST/models/TermModel.php';

error_reporting(E_ALL);
/**
 * Check if the request is an AJAX request
 */
if( !function_exists( 'Vidya\is_ajax' ) ) {
    function is_ajax() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }
}



/**
 * Main plugin class
 */
if( !class_exists('Vidya\PluginCommons') ) {

class PluginCommons {

    //private static $common_options;
    protected static $registered_plugins;
    protected $options;
    protected $default_options = array (
        'rest_path'   => 'rest'
    );
    protected $strings_js;
    protected $notice_view = array();
    protected $admin_pages_hooks = array();

    /**
     * Mustache engine instance reference
     */
    public $m = null;


    /**
     * REST controller instance
     */
    protected $rest_controller = null;


    // /**
    //  * Class constructor
    //  */
    // function __construct( $config ) 
    // {
    //     // Return existing singleton if exists
    //     if( !is_null( self::$instance ) ) {
    //         return self::$instance;
    //     }

    //     $this->constructor( $config );

    //     // Populate static instance reference
    //     self::$instance = $this;
    // }


    // /**
    //  * Get the unique singleton instance
    //  */
    // public static function get_instance()
    // {
    //     if( is_null( self::$instance ) ) {
    //         self::$instance = new Portfolios();
    //     }
    //     return self::$instance;
    // }


    /**
     * Class constructor
     */
    function __construct( $config ) 
    {
        // Return existing singleton if exists
        if( !is_null( static::$instance ) ) {
            return static::$instance;
        }

        foreach( $config as $k => $v ) {
            $this->$k = $v;
        }

        // Setup textdomain and option_name
        $this->textdomain = str_replace('-', '_', $this->plugin_name);
        $this->option_name = $this->textdomain . '_options';

        // Retrieve options
        $options = get_option($this->option_name);

        $temp_options = $this->default_options;
        if( !empty ($options)) {
          foreach($options as $key => $value) {
                $temp_options[$key] = $options[$key];
            }
        }
        // else {
        //     $this->options = $this->default_options;
        // }
        $this->options = $temp_options;

        add_action( 'init', array(&$this, 'init') );
        add_action('plugins_loaded', array(&$this, 'load_textdomain'));

        register_activation_hook( realpath(__DIR__ . "/../../../../../../{$this->plugin_name}/{$this->plugin_name}.php"), array( &$this, 'create_term_meta_table' ) );

        // Instantiate Mustache engine
        $this->m = new \Mustache_Engine( array(
            'loader' => new \Mustache_Loader_FilesystemLoader(realpath(__DIR__ . '/../../../../../..') . "/{$this->plugin_name}/templates")
        ));

        $this->view = array(
            'url' => array(
                'home'  => home_url(),
                'rest'  => home_url('/' . $this->options['rest_path'] ),
                'theme' => get_template_directory_uri(),
                'plugin' => home_url("wp-content/plugins/{$this->plugin_name}")
            ),
            'options' =>  $this->options 
        );

        if( property_exists( $this, 'actions' ) ) {
            foreach( $this->actions as $action => $callback ) {
                add_action( $action, array( &$this, $callback ) );
            }
        }

        // Populate static instance reference
        static::$instance = $this;
    }


    /**
     * Get the unique singleton instance
     */
    public static function get_instance($config)
    {
        if( is_null( static::$instance ) ) {
            $class = get_called_class();
            static::$instance = new $class($config);
        }
        return static::$instance;
    }


    /**
     * Detect a REST API request, and setup REST controller if relevant
     */
    public function if_rest_setup() {
        // If plugin doesn't use REST API or the resquest isn't an AJAX request
        if( !$this->use_rest || !is_ajax() ) {
//        if( !is_ajax() ) {
            return false;
        }
        // Redirect AJAX requests matching the REST API relative URL to RESTController
        $rest_path = preg_quote( $this->options['rest_path'], '/' );
        $rest_url_pattern = '/.*\/' . $rest_path .'\/(\w+)\/?(\d+)?/';
        if( preg_match( $rest_url_pattern, $_SERVER['REQUEST_URI'], $matches ) === 0 ) {
            return false;
        }
        // if( !class_exists('Vidya\REST\Controller' ) ) {
        //     require "REST/Controller.php";
        // }
        $this->rest_controller = new REST\Controller( $matches );
    }

    /**
     * Initialize taxonomy metadata tables
     */
    public function init_taxonomy_meta( $taxonomy ) {
        global $wpdb;
        foreach( $this->taxonomies as $taxonomy => $def ) {
            $tax_meta_name = $taxonomy . 'meta';
            $wpdb->$tax_meta_name = $wpdb->prefix . $tax_meta_name;
        }
    }

    /**
     * Initialize custom types&taxonomies
     */
    public function init_custom_types() {

        foreach( $this->types as $type => $args ) {
            register_post_type( $type, $args );
        }
        foreach( $this->taxonomies as $taxonomy => $def ) {
            if( array_key_exists('args', $def ) ) {
                register_taxonomy( $taxonomy, $def['post_type'], $def['args'] );
            }
            if( array_key_exists('has_meta', $def ) && $def['has_meta'] ) {
                $this->init_taxonomy_meta( $taxonomy );
            }
        }
    }

    /**
     * Perform initialization
     */
    function init()
    {
        $this->init_custom_types();

        if( is_admin() || $this->if_rest_setup() ) {
            $this->init_backend();
        }
        else {
            $this->frontend_init_shortcodes();
            add_action('wp_enqueue_scripts', array(&$this, 'frontend_enqueue_styles'));
        }
    }

    /**
     * Load plugin textdomain
     */
    function load_textdomain() {
        $mo_file = realpath(__DIR__ . '/..') . "/{$this->plugin_name}/languages/{$this->textdomain}-fr_FR.mo";
        load_textdomain( 'vidya_portfolios', $mo_file );
    }

    /**
     * Perform admin back-end initialization
     */
    function init_backend()
    {

        add_action( 'admin_menu', array(&$this, 'register_menu_pages') );
        add_action( 'admin_enqueue_scripts',  array(&$this, 'admin_pages_enqueue_scripts') );
        add_action( 'admin_enqueue_scripts',  array(&$this, 'add_js_strings'), 9 );
        // add_action( 'admin_post_vidya_prt_export_json', array( &$this, 'export_json') );
        // add_action( 'admin_post_vidya_prt_import_json', array( &$this, 'import_json') );
        // add_action( 'admin_post_vidya_prt_options', array( &$this, 'update_options') );

        if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') ) {
            return;
        }
    }


    /**
     * Perform front-end initialization
     */
    function frontend_enqueue_styles()
    {
        // Extract the theme main style file from the wp registered styles
        global $wp_styles;
        $theme_style = array_filter($wp_styles->registered, function($style) {
            // Return the only file that should match style.css
            return preg_match('/.*\/style.css$/', $style->src);
        });
        $theme_style = empty($theme_style) ? array() : array( key($theme_style) );
        //$frontend_js = USE_MINIFIED_ASSETS ? "{$this->plugin_name}-frontend.min" : $this->plugin_name;
        if( ! USE_MINIFIED_ASSETS ) {
            foreach( $this->styles as $handle => $deps ) {
                // Add theme style to dependency list, so as to enforce that plugin style is loaded after theme style
                $deps[] = $theme_style;
                //wp_enqueue_style( $handle, plugins_url() . '/' . $this->plugin_name . "/css/$handle.css", $deps, $this->version );
                wp_enqueue_style( $handle, plugins_url() . '/' . $this->plugin_name . "/css/$handle.css", array(), $this->version );
            }
            foreach( $this->scripts as $handle => $deps ) {
                $deps[] = 'jquery';
                wp_enqueue_script( $handle, plugins_url() . '/' . $this->plugin_name . "/js/$handle.js", $deps, $this->version, true );
            }
        }
        else {
            wp_enqueue_style( $this->plugin_name + '-frontend', plugins_url() . '/' . $this->plugin_name . "/css/{$this->plugin_name}.min.css", $theme_style, $this->version );
            wp_enqueue_script( $this->plugin_name + '-frontend', plugins_url() . '/' . $this->plugin_name . "/js/{$this->plugin_name}.min.js", array('jquery'), $this->version, true );
        }
    }


    /**
     * Perform front-end initialization
     */
    function frontend_init_shortcodes()
    {
        $shortcodes_file = realpath( __DIR__ . "/../{$this->plugin_name}/{$this->plugin_name}-shortcodes.php" );
        if( file_exists( $shortcodes_file ) ) {
            require_once( $shortcodes_file );
        }
    }


    /**
     * Update plugin options
     */
    function update_options() {
        // if( !array_key_exists('plugin_id', $_POST ) && ! ) {
        //     throw new Exception('Option form should include plugin_id as hidden field');
        // }
        $options = array();
        // $options['expert_mode'] = isset($_POST['expert_mode']);
        // $options['rest_path'] = $_POST['rest_path'];
        foreach( $options as $key => $value ) {
            $this->options[$key] = $value;
        }
        update_option( $this->option_name, $this->options );
        // $redirect_url = add_query_arg( array('vprt_err' => VPRT_OPTIONS_UPDATE_SUCCESS), wp_get_referer() );
        // wp_safe_redirect( $redirect_url . '#vprt-options');
    }




    /**
     * Display a notice when JSON file import fails
     */
    function admin_notices() {
        // $error_messages = array(
        //     VPRT_OPTIONS_UPDATE_SUCCESS => __('Options updated', 'vidya_prt'),
        //     VPRT_JSON_IMPORT_SUCCESS     => __('JSON import successful', 'vidya_prt'),
        //     VPRT_JSON_IMPORT_ERR_MAXSIZE => __('JSON import failed: maximum file size (200kb) exceeded', 'vidya_prt'),
        //     VPRT_JSON_IMPORT_ERR_NOTJSON => __('JSON import failed: file is not a valid JSON file', 'vidya_prt'),
        //     VPRT_JSON_IMPORT_ERR_UNKNOWN => __('JSON import failed: unknown error (try to deactivate then reactivate the plugin)', 'vidya_prt')
        // );
        // $error_code = intval($_GET['vprt_err']);
        // $message = $error_messages[$error_code];
        // $has_error = $error_code >= 100;
        // $class = $has_error ? 'error' : 'updated';
        // $this->notice_view = array(
        //     'message' => $message,
        //     'class' => $class,
        //     'has_error' => $has_error
        // );
    }

    /**
     * Enqueue scripts&styles for Backbone.js app.
     *
     * Dependencies for the app are:
     * - Handlebars.js
     * - Pure.css
     * - Font Awesome
     *
     * @param string hook_suffix The page hook
     */
    function admin_pages_enqueue_scripts($hook_suffix) {
        if( !array_key_exists( $hook_suffix, $this->admin_pages_hooks ) ) {
            return;
        }
        $handle = $this->admin_pages_hooks[$hook_suffix];
        $handle_dashed = str_replace( '_', '-', $handle );


        // TODO: enqueue app css&js only on plugin page
        // http://codex.wordpress.org/Function_Reference/wp_enqueue_style
        if(USE_MINIFIED_ASSETS) {
            wp_enqueue_script( "{$this->plugin_name}-$handle_dashed", plugins_url() . '/' . $this->plugin_name . "/js/$handle_dashed.min.js", array('jquery', 'backbone'));
            wp_enqueue_style( "{$this->plugin_name}-$handle_dashed", plugins_url() . '/' . $this->plugin_name . "/css/$handle_dashed.min.css");
        }
        else {

            $main_js_deps = array( "{$this->plugin_name}-$handle_dashed-templates", 'vidya-string-functions', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable' );

            if( array_key_exists('scripts', $this->admin_pages[$handle])) {
                //var_dump($this->admin_pages[$handle]['scripts']);die();
                foreach( $this->admin_pages[$handle]['scripts'] as $script_handle => $script_def ) {
                    wp_enqueue_script($script_handle, plugins_url() . '/' . $this->plugin_name . '/' . $script_def['file'], $script_def['deps']);
                    $main_js_deps[] = $script_handle;
                }
            }
            if( array_key_exists('styles', $this->admin_pages[$handle])) {
                //var_dump($this->admin_pages[$handle]['scripts']);die();
                foreach( $this->admin_pages[$handle]['styles'] as $style_handle => $style_def ) {
                    wp_enqueue_style($style_handle, plugins_url() . '/' . $this->plugin_name . '/' . $style_def['file'], $style_def['deps']);
                }
            }


            wp_enqueue_script( 'vidya-string-functions', plugins_url() . '/' . $this->plugin_name . '/js/string-functions.js');
            wp_enqueue_script( 'handlebars', plugins_url() . '/' . $this->plugin_name . '/js/vendor/handlebars.min.js', array('jquery', 'backbone') );
            wp_enqueue_script( "{$this->plugin_name}-$handle_dashed-templates", plugins_url() . '/' . $this->plugin_name . "/js/$handle_dashed-templates.js", array('handlebars'));
            wp_enqueue_script( "{$this->plugin_name}-$handle_dashed", plugins_url() . '/' . $this->plugin_name . "/js/$handle_dashed-app.js", $main_js_deps );
            //wp_enqueue_style('pure-min', plugins_url() . '/' . $this->plugin_name . '/frontend/bower_components/pure/pure-min.css');
            wp_enqueue_style('font-awesome', plugins_url() . '/' . $this->plugin_name . '/css/font-awesome.min.css');
            wp_enqueue_style('app-styles', plugins_url() . '/' . $this->plugin_name . "/css/$handle_dashed.css");
            wp_enqueue_style("{$this->plugin_name}-$handle_dashed", plugins_url() . '/' . $this->plugin_name . "/css/$handle_dashed.css");
        }


        wp_localize_script( "{$this->plugin_name}-$handle_dashed", "{$this->plugin_code}_strings", $this->strings_js);
    }


    /**
     * Insert link to the Pricing tables single-page app into admin menu
     */
    function register_menu_pages() {
        foreach( $this->admin_pages as $handle => $def) {
            extract( $def );
            $this->admin_pages_hooks["toplevel_page_$handle"] = $handle;
            add_menu_page( $title, $title, $capacity, $handle, array(&$this, "page_$handle"), '', 30 + rand() * 5 );
            //add_submenu_page( "edit.php?post_type={$post_type}", $title, $title, $capacity, $handle, array(&$this, "page_$handle") );
            add_action( 'admin_head', array(&$this, "page_{$handle}_js") );
        }
    }


    /**
     * Here, methods of which names match the pages declared in admin_pages array with page_ suffix
     */
    // function page_handle() {
    // }


    /**
     * Create meta table form pricing categories on plugin activation
     * @global type $wpdb
     */
    function create_term_meta_table() {
        global $wpdb;
        foreach( $this->taxonomies as $taxonomy => $has_meta ) {
            if( !$has_meta ) continue;
            $tax_meta_name = $taxonomy . 'meta';
            $table_name = $wpdb->prefix . $tax_meta_name;

            if (!empty ($wpdb->charset))
                $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
            if (!empty ($wpdb->collate))
                $charset_collate .= " COLLATE {$wpdb->collate}";

            if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                return;
            }
            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                meta_id bigint(20) NOT NULL AUTO_INCREMENT,
                {$taxonomy}_id bigint(20) NOT NULL default 0,

                meta_key varchar(255) DEFAULT NULL,
                meta_value longtext DEFAULT NULL,

                UNIQUE KEY meta_id (meta_id)
            ) {$charset_collate};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}

}
?>