<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer\output;

/**
 * Output renderer for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the embedded Drive Resource view.
     *
     * @param array $data Template data.
     * @return string
     */
    public function render_resource(array $data): string {
        return $this->render_from_template('mod_videoplayer/resource', $data);
    }

    /**
     * Render an invalid resource URL message.
     *
     * @return string
     */
    public function render_invalid_resource(): string {
        return \html_writer::div(get_string('invaliddriveurl', 'mod_videoplayer'), 'alert alert-danger');
    }
}
