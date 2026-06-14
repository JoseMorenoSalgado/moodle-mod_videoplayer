<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Spanish language strings for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Recurso de Google Drive';
$string['modulename'] = 'Recurso de Google Drive';
$string['modulenameplural'] = 'Recursos de Google Drive';
$string['modulename_help'] = 'Use esta actividad para insertar videos, PDF, imágenes, documentos, hojas de cálculo y presentaciones desde Google Drive.';
$string['pluginadministration'] = 'Administración del recurso de Google Drive';

$string['resourcename'] = 'Nombre del recurso';
$string['videoname'] = 'Nombre del recurso';
$string['driveurl'] = 'URL de Google Drive';
$string['driveurl_help'] = 'Pegue una URL compartible de Google Drive o Google Docs. Se admiten videos, PDF, imágenes, documentos, hojas de cálculo y presentaciones.';
$string['videourl'] = 'URL de Google Drive';
$string['videourl_help'] = 'Pegue una URL compartible de Google Drive o Google Docs.';
$string['resourcetype'] = 'Tipo de recurso';
$string['completionpercentage'] = 'Porcentaje requerido de finalización';
$string['completionpercentage_help'] = 'Porcentaje necesario para considerar este recurso como completado cuando el seguimiento de progreso esté disponible. Para recursos que no son video puede usarse la finalización por vista de Moodle.';
$string['openindrive'] = 'Abrir en Google Drive';

$string['typeauto'] = 'Automático';
$string['typevideo'] = 'Video';
$string['typepdf'] = 'PDF';
$string['typeimage'] = 'Imagen';
$string['typedocument'] = 'Documento';
$string['typespreadsheet'] = 'Hoja de cálculo';
$string['typepresentation'] = 'Presentación';
$string['typefile'] = 'Archivo';

$string['invalidurl'] = 'La URL proporcionada no es válida. Use un enlace compartible correcto de Google Drive.';
$string['invaliddriveurl'] = 'Ingrese una URL compartible válida de Google Drive o Google Docs.';
$string['invalidcompletionpercentage'] = 'El porcentaje de finalización debe ser un número entre 0 y 100.';

$string['mod_videoplayer:addinstance'] = 'Agregar un nuevo recurso de Google Drive';
$string['mod_videoplayer:addinstance_help'] = 'Permite agregar una nueva actividad de recurso de Google Drive a un curso.';
$string['mod_videoplayer:view'] = 'Ver recurso de Google Drive';
$string['mod_videoplayer:view_help'] = 'Permite ver el contenido de la actividad de recurso de Google Drive.';
$string['mod_videoplayer:edit'] = 'Editar recurso de Google Drive';
$string['mod_videoplayer:edit_help'] = 'Permite editar la configuración del recurso de Google Drive.';
$string['mod_videoplayer:manage'] = 'Gestionar recurso de Google Drive';
$string['mod_videoplayer:manage_help'] = 'Permite gestionar la configuración del recurso de Google Drive.';
$string['mod_videoplayer:viewreport'] = 'Ver reportes del recurso de Google Drive';
$string['mod_videoplayer:viewreport_help'] = 'Permite ver reportes relacionados con los recursos de Google Drive.';
$string['mod_videoplayer:editreport'] = 'Editar reportes del recurso de Google Drive';
$string['mod_videoplayer:editreport_help'] = 'Permite editar reportes y progreso de usuarios en recursos de Google Drive.';

$string['privacy:metadata:videoplayer_views'] = 'Almacena datos de progreso y finalización de usuarios para recursos de Google Drive.';
$string['privacy:metadata:videoplayer_views:videoplayerid'] = 'ID de la instancia de actividad del recurso de Google Drive.';
$string['privacy:metadata:videoplayer_views:userid'] = 'ID del usuario que vio el recurso.';
$string['privacy:metadata:videoplayer_views:progress'] = 'Último valor de progreso guardado.';
$string['privacy:metadata:videoplayer_views:completed'] = 'Indica si el recurso fue marcado como completado.';
$string['privacy:metadata:videoplayer_views:completionpercentage'] = 'Porcentaje de finalización guardado.';
$string['privacy:metadata:videoplayer_views:timecreated'] = 'Fecha en que se creó el primer registro de progreso.';
$string['privacy:metadata:videoplayer_views:timemodified'] = 'Fecha de la última actualización del registro de progreso.';
