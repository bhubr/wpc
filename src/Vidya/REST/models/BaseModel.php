<?php
namespace Vidya\REST;
if( !class_exists('Vidya\REST\BaseModel')) {

/**
 * Base model to represent WordPress objects
 *
 * As of now, only has two subclasses: PostModel and TermModel.
 * Subclasses implement static methods to perform CRUD operations,
 * using native WordPress per-object-type methods (e.g. wp_insert_post(), ...)
 *
 * Objects are created with some fields as built-in WP type fields (e.g. slug, term_id for terms),
 * others may be:
 *   - all serialized in *one* post or term meta
 *   - put in distinct term metas
 *
 * One exception to the common HTTP method => CRUD operation mapping is the upload of image files.
 * We want to let WP handle the media upload, so that all thumbnails, etc. are created.
 * So we forget about uploading images as base64-encoded strings embedded into the JSON payload
 * sent to the server.
 * We instead use the classic method using $_FILES and $_POST. So we need to distinguish between a
 * "standard JSON payload" (from the Backbone.model.save point of view) and an image payload.
 * We have 3 cases:
 *   1/ Post creation *AND* attachment creation at the same time
 *   2/ Post update and attachment creation
 *   3/ Post update and attachment update
 * All should be done through a POST request, however the ID won't be provided in the first case.
 *   1/ POST /<rest_root>/vidya_portfolio with regular post attrs in POST array and files attrs in FILES
 *   2/ and 3/ POST /<rest_root>/vidya_portfolio/<post_id>
 * Only tricky thing: a post can have *one* featured image and *many* attachments who have this post as
 * parent. In the first case a _thumbnail_id meta_key with the attachment id as value is added to the post
 * In the 2nd case attachments are created
 */
abstract class BaseModel {

    protected $object_id = null;
    protected $data;
    static $map_fields;
    static $required_fields;
    //static $extra_fields = null;
    static $skip_fields = array();

    /**
     * Each subclass is free to have any number of constructor parameters
     *
     * e.g. CatModel's cant have associated terms
     */
    private function __construct( $data = null ) {
        
    }

    /**
     * Get a data field
     */
    public function get( $data_field ) {
        if( array_key_exists($data_field, $this->data)) {
            return $this->data[$data_field];
        }
        else throw new Exception("field $data_field does not exist");
    }

    /**
     * Return as JSON
     */
    public function toJSON() {
        return json_encode($this->data);
    }

    // CRUD OPERATIONS : Create, Read, Update, Get
    // Which one is executed depends on the HTTP request method

    /**
     * Create a model
     */
    //abstract public static function create( $json = null );

    /**
     * Read/fetch a model
     */
    // abstract public function read( $object_id );
 
    /**
     * Update a model
     */
    // abstract public function update( $object_id, $json = null );

    /**
     * Update a model
     */
    // abstract public function delete( $object_id );

    /**
     * Prepare data structure for object creation or update.
     *
     * Called only from post() and put() methods.
     * The data structure can be prepared from a JSON object, if provided.
     * Otherwise the raw PHP request is json-decoded.
     */
    protected static function from_json( $object_or_array = null ) {
        // Parse json payload
        $payload = is_null($object_or_array) ? json_decode( file_get_contents("php://input") ) : $object_or_array;

        if( !is_object($payload) && !is_array($payload) ) {
            // var_dump($_FILES);
            // var_dump($_POST);
            throw new \Exception('Invalid json' );
        }
        $is_object = is_object($payload);
        // Populate arguments for the insert/update terms functions
        $map_fields = array();

        $fields_done = array();

        // Normal fields
        foreach( static::$map_fields as $k => $f ) {
            // If key is a string, that means we must map the payload key to the target object key
            $key = is_string( $k ) ? $k : $f;
            if( ( $is_object && !property_exists( $payload, $key ) ) || !array_key_exists( $key, $payload ) ) {
                if( in_array($key, static::$required_fields ) ) { //|| $key === static::$id_key && $_SERVER['REQUEST_METHOD'] === 'PUT' ) {
                    throw new \Exception("Missing required key in data payload: $key");
                }
                else {
                    continue;
                }
            }
            $map_fields[$f] = $is_object ? $payload->$key : $payload[$key];
            $fields_done[] = $key;
            //unset($payload->$key);
            //}
            // else {
            //     if( !property_exists( $payload, $f ) ) {
            //         if( $f !== static::$id_key ) {
            //             throw new Exception("Missing required key in data payload: $f");
            //         }
            //         else {
            //             continue;
            //         }
            //     }
            //     $map_fields[$f] = $payload->$f;
            //     unset($payload->$f);
            // }
            
        }

        // Extra fields
        $extra_fields = array();
        foreach( $payload as $k => $f ) {
            if( !in_array( $k, static::$skip_fields ) && !in_array($k, $fields_done) ) {
                $extra_fields[$k] = htmlentities($f); //addslashes($payload->$f);
            }
        }
        $map_fields['__meta__'] = $extra_fields;

        // if( !is_null( static::$extra_fields ) ) {
        //     $extra_arr = array();
        //     foreach( static::$extra_fields as $f ) {
        //         $extra_arr[$f] = htmlentities($payload->$f); //addslashes($payload->$f);
        //     }
        //     //var_dump($extra_arr);
        //     $map_fields['__meta__'] = $extra_arr;
        //     // no need to encode it, will be serialized by add_post_meta
        //     //$map_fields['__meta__'] = json_encode($extra_arr);
        // }


        return $map_fields;
    }

    
}
}

?>