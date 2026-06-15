<?php
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

    $settings->add(new admin_setting_configcheckbox(
        'mod_videoplayer/pdfcacheenabled',
        get_string('setting_pdfcacheenabled', 'mod_videoplayer'),
        get_string('setting_pdfcacheenabled_desc', 'mod_videoplayer'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'mod_videoplayer/pdfcachettl',
        get_string('setting_pdfcachettl', 'mod_videoplayer'),
        get_string('setting_pdfcachettl_desc', 'mod_videoplayer'),
        2592000,
        PARAM_INT
    ));
}
