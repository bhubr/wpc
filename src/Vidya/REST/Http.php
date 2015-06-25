<?php
namespace Vidya\REST;
class Http {

    /**
     * Check if the request is an AJAX request
     */
    static function is_ajax() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }


    /**
     * Verify WordPress nonce
     */
    static function verify_nonce() {
        $request_headers = getallheaders();
        if( !array_key_exists( 'X-WP-Nonce', $request_headers ) ) {
            self::forbidden( 'fatal: missing headers' );
        }
        // check wp nonce
        // See https://codex.wordpress.org/Function_Reference/wp_verify_nonce
        $nonce = $request_headers['X-WP-Nonce'];
        if ( ! wp_verify_nonce( $nonce, 'my-nonce' ) ) {
            self::forbidden( 'fatal: wrong value' );
        }
    }


    /**
     * Die with error 400 (Bad Request)
     *
     * See http://www.bennadel.com/blog/2400-handling-forbidden-restful-requests-401-vs-403-vs-404.htm
     * Here we don't want to be visible to non-logged-in users
     */
    static function bad_request( $message ) {
        header("HTTP/1.0 400 Bad Request");
        wp_die( $message );
    }


    /**
     * Die with error 403 (Forbidden)
     *
     * See http://www.bennadel.com/blog/2400-handling-forbidden-restful-requests-401-vs-403-vs-404.htm
     * Here we don't want to be visible to non-logged-in users
     */
    static function forbidden( $message ) {
        header("HTTP/1.0 403 Forbidden");
        wp_die( $message );
    }


    /**
     * Die with error 404 (Not Found)
     *
     * See http://www.bennadel.com/blog/2400-handling-forbidden-restful-requests-401-vs-403-vs-404.htm
     * Here we don't want to be visible to non-logged-in users
     */
    static function not_found() {
        header("HTTP/1.0 404 Not Found");
        wp_die('Not Found');
    }


    /**
     * Send JSON data
     */
    static function json( $data )
    {
        header('Content-Type: application/json');
        echo json_encode( $data );
        wp_die();
    }
}