<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer;

/**
 * Moodle 5.0 compatibility contract tests.
 *
 * @package    mod_videoplayer
 * @category   test
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class moodle50_compatibility_test extends \advanced_testcase {

    /**
     * The plugin metadata must explicitly include Moodle 5.0.
     */
    public function test_plugin_metadata_supports_moodle_50(): void {
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('mod_videoplayer');

        $this->assertNotNull($plugininfo);
        $this->assertSame('mod_videoplayer', $plugininfo->component);
        $this->assertSame(2025041400, (int) $plugininfo->versionrequires);
        $this->assertContains(500, $plugininfo->supported);
    }

    /**
     * Core APIs used by Drive Resource must exist on the target branch.
     */
    public function test_required_moodle_apis_exist(): void {
        $this->assertTrue(class_exists(\core_external\external_api::class));
        $this->assertTrue(class_exists(\core\task\adhoc_task::class));
        $this->assertTrue(class_exists(\core\task\scheduled_task::class));
        $this->assertTrue(class_exists(\core_privacy\local\request\writer::class));
        $this->assertTrue(method_exists(\core\task\manager::class, 'queue_adhoc_task'));
        $this->assertTrue(method_exists(\core\session\manager::class, 'write_close'));
    }

    /**
     * Moodle 5.0 requires PHP 8.2 and the plugin may safely use modern types.
     */
    public function test_runtime_meets_php_requirement(): void {
        $this->assertGreaterThanOrEqual(80200, PHP_VERSION_ID);
    }
}
