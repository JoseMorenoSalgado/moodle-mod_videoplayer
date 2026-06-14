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

$string['pluginname'] = 'Drive Resource';
$string['modulename'] = 'Drive Resource';
$string['modulenameplural'] = 'Drive Resources';
$string['modulename_help'] = 'Use esta actividad para insertar videos, PDF, imágenes, documentos, hojas de cálculo y presentaciones desde Google Drive.';
$string['pluginadministration'] = 'Administración de Drive Resource';

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
$string['protectedresource'] = 'Recurso protegido';
$string['backtoresource'] = 'Volver al recurso';
$string['progress'] = 'Progreso';
$string['progressreport'] = 'Reporte de progreso';
$string['noprogressrecords'] = 'Todavía no hay registros de progreso para este recurso.';
$string['noresources'] = 'No hay Drive Resources en este curso.';
$string['noresourcesavailable'] = 'No hay Drive Resources disponibles para usted en este curso.';

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

$string['setting_enabletracking'] = 'Activar seguimiento de progreso';
$string['setting_enabletracking_desc'] = 'Cuando está activado, Drive Resource registra la permanencia e interacción del usuario dentro de la actividad.';
$string['setting_defaultrequiredseconds'] = 'Tiempo requerido predeterminado';
$string['setting_defaultrequiredseconds_desc'] = 'Tiempo activo predeterminado, en segundos, requerido para considerar suficientemente visualizado un recurso cuando se usa seguimiento por permanencia.';
$string['setting_defaultcompletionpercentage'] = 'Porcentaje de finalización predeterminado';
$string['setting_defaultcompletionpercentage_desc'] = 'Porcentaje usado por defecto al crear nuevas actividades Drive Resource.';
$string['setting_protectedmode'] = 'Modo protegido';
$string['setting_protectedmode_desc'] = 'Cuando está activado, Drive Resource oculta enlaces directos hacia Google Drive y limita permisos de ventanas emergentes dentro del iframe. Algunos controles internos siguen dependiendo de Google Drive.';
$string['setting_showresourcetype'] = 'Mostrar tipo de recurso';
$string['setting_showresourcetype_desc'] = 'Muestra el tipo de recurso detectado o seleccionado encima del recurso incrustado.';

$string['mod_videoplayer:addinstance'] = 'Agregar un nuevo Drive Resource';
$string['mod_videoplayer:addinstance_help'] = 'Permite agregar una nueva actividad Drive Resource a un curso.';
$string['mod_videoplayer:view'] = 'Ver Drive Resource';
$string['mod_videoplayer:view_help'] = 'Permite ver el contenido de la actividad Drive Resource.';
$string['mod_videoplayer:edit'] = 'Editar Drive Resource';
$string['mod_videoplayer:edit_help'] = 'Permite editar la configuración de Drive Resource.';
$string['mod_videoplayer:manage'] = 'Gestionar Drive Resource';
$string['mod_videoplayer:manage_help'] = 'Permite gestionar la configuración de Drive Resource.';
$string['mod_videoplayer:viewreport'] = 'Ver reportes de Drive Resource';
$string['mod_videoplayer:viewreport_help'] = 'Permite ver reportes relacionados con Drive Resource.';
$string['mod_videoplayer:editreport'] = 'Editar reportes de Drive Resource';
$string['mod_videoplayer:editreport_help'] = 'Permite editar reportes y progreso de usuarios en Drive Resource.';

$string['privacy:metadata:videoplayer_views'] = 'Almacena datos de progreso y finalización de usuarios para Drive Resource.';
$string['privacy:metadata:videoplayer_views:videoplayerid'] = 'ID de la instancia de actividad Drive Resource.';
$string['privacy:metadata:videoplayer_views:userid'] = 'ID del usuario que vio el recurso.';
$string['privacy:metadata:videoplayer_views:progress'] = 'Último valor de progreso guardado.';
$string['privacy:metadata:videoplayer_views:completed'] = 'Indica si el recurso fue marcado como completado.';
$string['privacy:metadata:videoplayer_views:completionpercentage'] = 'Porcentaje de finalización guardado.';
$string['privacy:metadata:videoplayer_views:timecreated'] = 'Fecha en que se creó el primer registro de progreso.';
$string['privacy:metadata:videoplayer_views:timemodified'] = 'Fecha de la última actualización del registro de progreso.';
