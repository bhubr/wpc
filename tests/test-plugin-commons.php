<?php

class PluginCommonsTestTest extends WP_UnitTestCase {

    public function testInstance() {
        $plugin = Vidya\PluginCommonsTest::get_instance();
        $this->assertNotEquals( null, $plugin->m, 'Mustache engine should be instantiated (not null)' );
    }
}

