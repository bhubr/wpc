<?php
/*
Plugin Name: Vidya Plugin Commons Test
Plugin URI: http://benoithubert.github.io/vidya-portfolio
Description: Test plugin for the PluginCommons package
Version: 0.3.0
Author: Benoît Hubert
Author URI: http://www.developpeur-javascript.fr
*/
namespace Vidya;

define('DIRECTORIES_UP', '/../../..');
require_once __DIR__ . '/vendor/autoload.php'; // Autoload files using Composer autoload


$config = array(
    'plugin_name' => 'vidya-plugin-commons-test',
    'plugin_code' => 'vpctest',
    'use_rest'    => true,
    'version'     => '0.1',
    'admin_pages' => array(),

    'types' => array(
        'plugincommons_type' =>
            array(
                'labels' => array(
                    'name'               => 'PluginCommons Items',
                    'singular_name'      => 'PluginCommons Item',
                    'add_new'            => 'Add',
                    'add_new_item'       => 'Add PluginCommons Item',
                    'edit_item'          => 'Edit PluginCommons Item',
                    'new_item'           => 'New PluginCommons Item',
                    'all_items'          => 'All PluginCommons Items',
                    'view_item'          => 'View PluginCommons Item',
                    'search_items'       => 'Search PluginCommons Items',
                    'not_found'          => 'No item found',
                    'not_found_in_trash' => 'No item found in Trash',
                    'menu_name'          => 'PluginCommons Items', 'wp_plugincommons_items'
                ),
                'description'   => 'PluginCommons Items',
                'public'        => true,
                'menu_position' => 40,
                'supports'      => array( 'title', 'editor', 'thumbnail' ),
                'exclude_from_search' => true,
            )
    ),
    'taxonomies' => array(
        'plugincommons_tax' => array(
            'post_type' =>'plugincommons_type',
            'has_meta' => 'true',
            'args' => array(
                'labels' => array(
                    'name' => 'PluginCommons Taxonomy',
                    'add_new_item' => 'Add PluginCommons Taxonomy',
                    'new_item_name' => 'New PluginCommons Taxonomy',
                ),
                'show_ui' => true,
                'show_tagcloud' => false,
                'hierarchical' => true
            )
        )
    )
);

class PluginCommonsTest extends PluginCommons {

    /**
     * Instance reference (Singleton)
     */
    public static $instance = null;

    public function add_js_strings() {
        $this->strings_js = array();
    }
}

$instance = PluginCommonsTest::get_instance( $config );
?>