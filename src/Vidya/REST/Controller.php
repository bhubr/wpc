<?php
namespace Vidya\REST;
class Controller {

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
    public static function yo() {
        echo "Yo " . __FILE__ . "\n";
    } 

    /**
     * Class constructor
     */
    function __construct( $url_matches ) {
        $this->path_prefix = $url_matches[1];
        $post_types = get_post_types();
        $taxonomies = get_taxonomies();

        // Not found if no rights
        if( ! current_user_can('switch_themes') ) {
            $this->not_found();
        }

        // Check if targeted object is post or term, send 404 if it's neither or set obj type accordingly.
        if( array_key_exists( $this->path_prefix, $post_types ) ) {
            $this->object_type = 'PostModel';
        }
        else if( array_key_exists( $this->path_prefix, $taxonomies ) ) {
            $this->object_type = 'TermModel';
        }
        else {
            $this->not_found();
        }

        // Pass the object type and id except for create
        $this->action_args = array( $url_matches[1] );
        if( count( $url_matches ) > 2 ) {
            $this->action_args[] = intval( $url_matches[2] );
        }
        
        add_action( 'wp_loaded', array(&$this, 'do_ajax_after_load') );

    }

    /**
     * Die with error 404 (Not Found)
     *
     * See http://www.bennadel.com/blog/2400-handling-forbidden-restful-requests-401-vs-403-vs-404.htm
     * Here we don't want to be visible to non-logged-in users
     */
    function not_found() {
        header("HTTP/1.0 404 Not Found");
        wp_die('Not Found');
    }

    /**
     * Die with error 400 (Bad Request)
     *
     * See http://www.bennadel.com/blog/2400-handling-forbidden-restful-requests-401-vs-403-vs-404.htm
     * Here we don't want to be visible to non-logged-in users
     */
    function bad_request( $message ) {
        header("HTTP/1.0 400 Bad Request");
        die( $message );
    }

    function do_action() {
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
            $this->bad_request( $e->getMessage() );
        }
        //if( !empty( $_FILES ) ) {
        error_log(serialize($_POST));
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
        header('Content-Type: application/json');
        echo json_encode( $data );
        wp_die();
    }


    function do_ajax_after_load() {
        add_action( 'wp_ajax_' . $this->path_prefix, array(&$this,'do_action') );
        $_REQUEST['action'] = $this->path_prefix;
        require_once( ABSPATH . 'wp-admin/admin-ajax.php' );
    }


}
?>