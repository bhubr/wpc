<?php
namespace Vidya\REST;
require_once 'Http.php';
require_once 'Inflect.php';

class Controller {

    protected static $instance = null;

    protected $path_prefix = '';
    protected $action_args = array();

    /**
     * Map HTTP methods to CRUD operations
     */
    private $method_action_map = array(
        'POST'   => 'create',
        'GET'    => 'read',
        'PUT'    => 'update',
        'DELETE' => 'delete'
    );

    /**
     * Store the object type (PostModel, TermModel, etc.)
     */
    protected $object_type;
    private $_is_rest = false;

    protected $url_bases = [];
    protected $base_url; // = '/rest';

    /**
     * Class constructor
     */
    function __construct( $rest_path ) {
        if( self::$instance !== null ) {
            return self::$instance;
        }

        // Return if not ajax
        if( !Http::is_ajax() ) { // || preg_match( $rest_url_pattern, $_SERVER['REQUEST_URI'], $url_matches ) === 0 ) {
            self::$instance = $this;
            return;
        }

        // Not found if no rights
        if( ! current_user_can('switch_themes') ) {
            Http::forbidden();
        }

        $this->_is_rest = true;
        $this->base_url =  '/' . $rest_path;
    }

    function post_init() {
        if( !$this->_is_rest ) {
            return;
        }

        // Redirect AJAX requests matching the REST API relative URL to RESTController
        if( !$this->parse_path() ) {
            self::$instance = $this;
            return;
        }


        // Pass the object type and id except for create
        $this->action_args = array( $url_matches[1] );
        if( count( $url_matches ) > 2 ) {
            $this->action_args[] = intval( $url_matches[2] );
        }
        
        add_action( 'wp_loaded', array(&$this, 'do_ajax_after_load') );

        self::$instance = $this;
    }


    function parse_path() {

        $rest_path_escaped = preg_quote( $this->base_url, '/' );
        // $rest_url_pattern = '/.*' . $rest_path_escaped .'\/(\w+)\/?(\d+)?/';
        $rest_url_pattern = '/(' . $rest_path_escaped .'\/\w+)\/?(\d+)?/';
        if( preg_match( $rest_url_pattern, $_SERVER['REQUEST_URI'], $url_matches ) === 0 ) {
            return false;
        }
        // var_dump($_SERVER['REQUEST_URI']);
        // var_dump($rest_url_pattern);
        // var_dump($url_matches);
        // var_dump($this->url_bases);
        // var_dump($this->url_bases[$url_matches[1]]);
        // die('ok');

        $this->path_prefix = $url_matches[1];
        if( !array_key_exists($this->path_prefix, $this->url_bases ) ) {
            return false;
        }
        $descriptor = $this->url_bases[$this->path_prefix];
        $this->object_type = ucfirst($descriptor['object_type']) . 'Model';
        return true;
        // Http::json($url_matches);
        // $post_types = get_post_types();
        // $taxonomies = get_taxonomies();

        // // Check if targeted object is post or term, send 404 if it's neither or set obj type accordingly.
        // if( array_key_exists( $this->path_prefix, $post_types ) ) {
        //     $this->object_type = 'PostModel';
        // }
        // else if( array_key_exists( $this->path_prefix, $taxonomies ) ) {
        //     $this->object_type = 'TermModel';
        // }
        // self::$path = $_SERVER['REQUEST_URI'];
        // return array_key_exists(self::$path, self::$url_bases);
    }


    public function is_rest()
    {
        return $this->_is_rest;
    }


    /**
     * Get the unique singleton instance
     */
    public static function get_instance()
    {
        if( is_null( self::$instance ) ) {
            self::$instance = new Controller();
        }
        return self::$instance;
    }


    function process_request() {
        Http::verify_nonce();

        $models_path = __DIR__ . '/models';
        if( !class_exists( "Vidya\\REST\\{$this->object_type}" ) ) {
            require "$models_path/{$this->object_type}.php";
        }

        $method = strtoupper( $_SERVER['REQUEST_METHOD'] );
        if( $method === 'POST' && !empty( $_FILES ) ) {
            // If no id is provided, this is a PUT
            if( count( $this->action_args ) === 2 ) {
                $method = 'PUT';
            }
            // Append the content of the payload
            $this->action_args[] = $_POST;
        }
        $action = $this->method_action_map[$method];

        // Call the static CRUD method on the target object type, die with error 400 if any error occurs
        try {
            $data = call_user_func_array ( array( "Vidya\\REST\\{$this->object_type}", $action ), $this->action_args );
        } catch(\Exception $e) {
            Http::bad_request( $e->getMessage() );
        }
        //if( !empty( $_FILES ) ) {
        if( isset( $_POST['_thumbnail_id'] ) ) {
            //foreach( $_FILES as $file_id => $file ) {
                $post_id = $this->object_type === 'PostModel' ? $data['id'] : 0;
              //  $attachment_id = media_handle_upload( $file_id, $post_id );
              //  if( $post_id ) {
                $success = update_post_meta ( $post_id, '_thumbnail_id', $_POST['_thumbnail_id'] );
              //  }
            //}
        }

        // Send the response
        Http::json( $data );
    }


    function do_ajax_after_load() {
        add_action( 'wp_ajax_' . $this->path_prefix, array(&$this,'process_request') );
        $_REQUEST['action'] = $this->path_prefix;
        require_once( ABSPATH . 'wp-admin/admin-ajax.php' );
    }

    function register_url($name, $type, $plugin_type) {
        $plural = \Inflect::pluralize($name);
        $url_base = $this->base_url . '/' . $plural;
        $this->url_bases[$url_base] = [
            'singular'     => $name,
            'object_type'  => $type,
            'payload_type' => $plugin_type
        ];
    }


    function register_taxonomy($taxo, $rest_type) {
        $this->register_url($taxo, 'term', $rest_type);
    }

    function register_type($type, $rest_type) {
        $this->register_url($type, 'post', $rest_type);
    }


}
?>