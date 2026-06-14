<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Admin settings for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'mod_videoplayer/enabletracking',
        get_string('setting_enabletracking', 'mod_videoplayer'),
        get_string('setting_enabletracking_desc', 'mod_videoplayer'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'mod_videoplayer/defaultrequiredseconds',
        get_string('setting_defaultrequiredseconds', 'mod_videoplayer'),
        get_string('setting_defaultrequiredseconds_desc', 'mod_videoplayer'),
        300,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_videoplayer/defaultcompletionpercentage',
        get_string('setting_defaultcompletionpercentage', 'mod_videoplayer'),
        get_string('setting_defaultcompletionpercentage_desc', 'mod_videoplayer'),
        80,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_videoplayer/protectedmode',
        get_string('setting_protectedmode', 'mod_videoplayer'),
        get_string('setting_protectedmode_desc', 'mod_videoplayer'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_videoplayer/showresourcetype',
        get_string('setting_showresourcetype', 'mod_videoplayer'),
        get_string('setting_showresourcetype_desc', 'mod_videoplayer'),
        1
    ));
}
