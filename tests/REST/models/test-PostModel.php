<?php
$plugin_root = realpath(__DIR__.'/../../..');
// require "$plugin_root/tests/tests-common.php";
// if(!class_exists('Vidya\REST\TermModel')) {
//     require "$plugin_root/rest/models/Vidya\REST\TermModel.php";    
// }

// require "$plugin_root/rest/models/Vidya\REST\PostModel.php";

class Vidya_REST_PostModel_Test extends WP_UnitTestCase
{
    public function setUp()
    {
        global $wpdb;
        $wpdb->query('DROP TABLE IF EXISTS wptests_plugincommons_taxmeta');
        $wpdb->query("DELETE FROM wptests_posts where post_type='plugincommons_type'");
        $wpdb->query("DELETE FROM wptests_terms");
        $wpdb->query("DELETE FROM wptests_term_taxonomy");
        $wpdb->query("DELETE FROM wptests_term_relationships");
        $plugin = Vidya\PluginCommonsTest::get_instance();
        $plugin->create_term_meta_table();

    }

    public function testCreateWithCat()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $cat_data = array('name' => 'New Pricing Cat');
        $expected_slug = sanitize_title_with_dashes($cat_data['name']);
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);

        $plan_data = array(
            'slug' => 'new-pricing-plan', 'name' => 'New Pricing Plan', 'price' => '200', 'currency' => '€', 'description' => 'An awesome offer', 'cat' => $pricing_plan_cat['term_id']
        );
        $json = json_decode(json_encode($plan_data));
        $pricing_plan_model = Vidya\REST\PostModel::create('plugincommons_type', $json);
        $pricing_plan_model2 = Vidya\REST\PostModel::create('plugincommons_type', $json);
        $this->assertNotEquals($pricing_plan_model instanceof WP_Error, true);
        $this->assertNotEquals($pricing_plan_model['id'], null);
        $this->assertEquals($pricing_plan_model['name'], $plan_data['name']);
        $this->assertEquals($pricing_plan_model['slug'], $plan_data['slug']);
        $this->assertEquals($pricing_plan_model2['slug'], $plan_data['slug'] . '-2');
        $this->assertEquals($pricing_plan_model['price'], $plan_data['price']);
        $this->assertEquals($pricing_plan_model['currency'], htmlentities($plan_data['currency']));
        $this->assertEquals($pricing_plan_model['cat'], $plan_data['cat']);
    }

    public function testUpdate()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $cat_data = array('name' => 'New Pricing Cat');
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);

        $plan_data = array(
            'slug' => 'new-pricing-plan', 'name' => 'New Pricing Plan', 'price' => '200', 'currency' => '€', 'description' => 'An awesome offer', 'cat' => $pricing_plan_cat['term_id']
        );
        $json = json_decode(json_encode($plan_data));
        $pricing_plan_model = Vidya\REST\PostModel::create('plugincommons_type', $json);
        $pricing_plan_model2 = Vidya\REST\PostModel::create('plugincommons_type', $json);

        // Set request method=PUT for object update
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $plan_data = array(
            'slug' => 'updated-pricing-plan', 'name' => 'Updated Pricing Plan', 'price' => '500', 'currency' => '$', 'description' => 'A better offer', 'cat' => $pricing_plan_cat['term_id']
        );

        $json = json_decode(json_encode($plan_data));
        $pricing_plan_model = Vidya\REST\PostModel::update('plugincommons_type',$pricing_plan_model['id'],$json);
        $pricing_plan_model2 = Vidya\REST\PostModel::update('plugincommons_type',$pricing_plan_model2['id'],$json);

        $this->assertNotEquals($pricing_plan_model instanceof WP_Error, true);
        $this->assertNotEquals($pricing_plan_model['id'], null);
        $this->assertEquals($pricing_plan_model['name'], $plan_data['name']);
        $this->assertEquals($pricing_plan_model['slug'], $plan_data['slug']);
        $this->assertEquals($pricing_plan_model2['slug'], $plan_data['slug'] . '-2');
        $this->assertEquals($pricing_plan_model['price'], $plan_data['price']);
        $this->assertEquals($pricing_plan_model['currency'], '$');
        $this->assertEquals($pricing_plan_model['cat'], $plan_data['cat']);
    }


    public function testRead()
    {
        // First set method=POST for object creation
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $cat_data = array('name' => 'New Pricing Cat');
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);

        $plan_data = array(
            'slug' => 'new-pricing-plan', 'name' => 'New Pricing Plan', 'price' => '200', 'currency' => '€', 'description' => 'An awesome offer', 'cat' => $pricing_plan_cat['term_id'], 'status' => 'publish', 'content' => '', 'order' => 0
        );
        $json = json_decode(json_encode($plan_data));
        $pricing_plan_model = Vidya\REST\PostModel::create('plugincommons_type', $json);

        // Set method=GET for object retrieval
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $a_post = get_post($pricing_plan_model['id']);
        $plan_data = array_merge( array('id' => $a_post->ID), $plan_data );
        $plan_data['currency'] = '&euro;';
        $plan_data_json = json_encode( $plan_data );

        $pricing_plan_model = Vidya\REST\PostModel::read('plugincommons_type',$a_post->ID);

        $this->assertEquals($pricing_plan_model['id'], $a_post->ID);
        $this->assertEquals($pricing_plan_model['name'], $plan_data['name']);
        $this->assertEquals($plan_data['slug'], $pricing_plan_model['slug'], 'Slugs should be equal');
        $this->assertEquals($pricing_plan_model['cat'], $pricing_plan_cat['term_id']);
        $this->assertEquals($pricing_plan_model, $plan_data);
    }

    public function testDelete()
    {
        // First set method=POST for object creation
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $cat_data = array('name' => 'New Pricing Cat');
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);

        $plan_data = array(
            'slug' => 'new-pricing-plan', 'name' => 'New Pricing Plan', 'price' => '200', 'currency' => '€', 'description' => 'An awesome offer', 'cat' => $pricing_plan_cat['term_id']
        );
        $json = json_decode(json_encode($plan_data));
        $pricing_plan_model = Vidya\REST\PostModel::create('plugincommons_type', $json);

        // Set method=GET for object retrieval
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $a_post = get_post($pricing_plan_model['id']);
        $result1 = Vidya\REST\PostModel::delete('plugincommons_type',$a_post->ID);
        $has_exception = false;
        try {
            $result2 = Vidya\REST\TermModel::delete('plugincommons_type',999999);
        } catch( Exception $e ) {
            $has_exception = true;
        }
        $this->assertEquals(is_object($result1), true);
        $this->assertEquals($result1->post_title, $plan_data['name']);
        $this->assertEquals($has_exception, true);
    }

}
?>