<?php
echo "TermModel test\n";
class Vidya_REST_TermModel_Test extends WP_UnitTestCase
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

    public function testCreateWithoutSlug()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $cat_data = array('name' => 'New Pricing Cat', 'toto' => 'Toto', 'tata' => 'Tata');
        $expected_slug = sanitize_title_with_dashes($cat_data['name']);
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);
        $pricing_plan_cat2 = Vidya\REST\TermModel::create('plugincommons_tax', $json);
        $this->assertNotEquals($pricing_plan_cat instanceof WP_Error, true);
        $this->assertNotEquals($pricing_plan_cat['term_id'], null);
        $this->assertEquals($pricing_plan_cat['name'], $cat_data['name']);
        $this->assertEquals($pricing_plan_cat['slug'], $expected_slug);
        $this->assertEquals($pricing_plan_cat2['slug'], $expected_slug . '-2');
        $this->assertEquals($pricing_plan_cat['toto'], $cat_data['toto']);
        $this->assertEquals($pricing_plan_cat['tata'], $cat_data['tata']);
    }

    public function testCreateWithSlug()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $cat_data = array('slug' => 'new-pricing-cat', 'name' => 'New Pricing Cat');
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);
        $pricing_plan_cat2 = Vidya\REST\TermModel::create('plugincommons_tax', $json);
        $this->assertNotEquals($pricing_plan_cat instanceof WP_Error, true);
        $this->assertNotEquals($pricing_plan_cat['term_id'], null);
        $this->assertEquals($pricing_plan_cat['name'], $cat_data['name']);
        $this->assertEquals($cat_data['slug'], $pricing_plan_cat['slug'], 'slugs should be equal');
        $this->assertEquals($pricing_plan_cat2['slug'], $cat_data['slug'] . '-2');
    }

    public function testUpdate()
    {
        // First set method=POST for object creation
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Initial data for object creation
        $cat_data = array('slug' => 'new-pricing-cat', 'name' => 'New Pricing Cat', 'toto' => 'Toto');
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);
        $pricing_plan_cat2 = Vidya\REST\TermModel::create('plugincommons_tax', $json);

        // Set request method=PUT for object update
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        // Data for object update
        // Parameter term_id is required to mimick the json payload sent by Backbone for an update (model.term_id is populated)
        $cat_data = array('slug' => 'updated-pricing-cat', 'name' => 'Updated Pricing Cat', 'term_id' => $pricing_plan_cat['term_id']);
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::update('plugincommons_tax', $pricing_plan_cat['term_id'], $json);

        $cat_data['term_id'] = $pricing_plan_cat2['term_id'];
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat2 = Vidya\REST\TermModel::update('plugincommons_tax', $pricing_plan_cat2['term_id'], $json);

        $this->assertNotEquals($pricing_plan_cat instanceof WP_Error, true);
        $this->assertNotEquals($pricing_plan_cat['term_id'], null);
        $this->assertEquals($pricing_plan_cat['name'], $cat_data['name']);
        $this->assertEquals($pricing_plan_cat['slug'], $cat_data['slug']);
        $this->assertEquals($pricing_plan_cat2['slug'], $cat_data['slug'] . '-2');
    }

    public function testRead()
    {
        // First set method=POST for object creation
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Initial data for object creation
        $cat_data = array('slug' => 'new-pricing-cat', 'name' => 'New Pricing Cat');
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);

        // Set method=GET for object retrieval
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $a_term = get_term_by('slug', 'new-pricing-cat', 'plugincommons_tax');
        $cat_data = array_merge( array('term_id' => $a_term->term_id), $cat_data );
        $cat_data_json = json_encode( $cat_data );

        $pricing_plan_cat = Vidya\REST\TermModel::read('plugincommons_tax', $a_term->term_id);
        $this->assertEquals($pricing_plan_cat['term_id'], $a_term->term_id);
        $this->assertEquals($pricing_plan_cat['name'], $cat_data['name']);
        $this->assertEquals($pricing_plan_cat['slug'], $cat_data['slug']);
        $this->assertEquals($pricing_plan_cat, $cat_data);
    }

    public function testDelete()
    {
        // First set method=POST for object creation
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Initial data for object creation
        $cat_data = array('slug' => 'new-pricing-cat', 'name' => 'New Pricing Cat');
        $json = json_decode(json_encode($cat_data));
        $pricing_plan_cat = Vidya\REST\TermModel::create('plugincommons_tax', $json);

        // Set method=GET for object retrieval
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $a_term = get_term_by('slug', 'new-pricing-cat', 'plugincommons_tax');
        $result1 = Vidya\REST\TermModel::delete('plugincommons_tax', $a_term->term_id);
        $has_exception = false;
        try {
            $result2 = Vidya\REST\TermModel::delete(999999);
        } catch( Exception $e ) {
            $has_exception = true;
        }
        $this->assertEquals($result1, true);
        $this->assertEquals($has_exception, true);
    }
}
?>