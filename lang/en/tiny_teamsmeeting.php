<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings for component 'tiny_teamsmeeting', language 'en'.
 *
 * @package     tiny_teamsmeeting
 * @copyright   2023 Enovation Solutions
 * @author      Oliwer Banach <oliwer.banach@enovation.ie>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Reunión de Teams';

// Configuración.
$string['settings_meetings_app_link'] = 'URL de la aplicación de reuniones';
$string['settings_meetings_app_link_desc'] = 'URL donde está alojada la aplicación de reuniones de Teams';

// Capacidad.
$string['teamsmeeting:add'] = 'Agregar reunión de Teams';

// iFrame.
$string['iframe_meeting_options'] = 'Opciones de la reunión';
$string['iframe_meeting_created'] = '¡La reunión "{$a}" fue creada exitosamente!';
$string['iframe_go_to_meeting'] = 'Ir a la reunión';
$string['iframe_not_found'] = 'Reunión no encontrada';

// TinyMCE.
$string['tiny_modal_title'] = 'Crear reunión de Teams';
$string['tiny_button_primary_label'] = 'Agregar enlace';
$string['tiny_button_secondary_label'] = 'Cancelar';
$string['tiny_input_url_label'] = 'URL de tu reunión:';
$string['tiny_input_url_placeholder'] = 'El enlace se generará después de crear la reunión.';
$string['tiny_checkbox_new_window_label'] = 'Abrir reunión en nueva ventana';

// Reporte.
$string['report'] = 'Reporte de sesiones';
$string['report_heading'] = 'Sesiones de reuniones de Teams';
$string['report_col_title'] = 'Título';
$string['report_col_link'] = 'Enlace de la reunión';
$string['report_col_starttime'] = 'Inicio';
$string['report_col_endtime'] = 'Fin';
$string['report_col_timecreated'] = 'Creado';
$string['report_col_options'] = 'Opciones';
$string['report_col_actions'] = 'Acciones';
$string['report_notscheduled'] = '—';
$string['report_noresults'] = 'No se han registrado reuniones todavía.';
$string['report_unscheduled'] = 'Reuniones sin fecha programada';
$string['report_legend_scheduled'] = 'Reunión programada';
$string['report_legend_created'] = 'Creada (sin horario asignado)';
$string['report_join'] = 'Unirse';
$string['report_meetingoptions'] = 'Opciones';
$string['report_edit'] = 'Editar';
$string['report_download_excel'] = 'Descargar Excel';

// Editar sesión.
$string['edit_session'] = 'Editar sesión';
$string['edit_session_saved'] = 'Sesión actualizada correctamente.';
$string['edit_session_title'] = 'Título';
$string['edit_session_starttime'] = 'Fecha y hora de inicio';
$string['edit_session_endtime'] = 'Fecha y hora de fin';
$string['edit_session_link'] = 'Enlace de la reunión (solo lectura)';
$string['edit_session_notfound'] = 'Sesión no encontrada.';

// Subsistema de privacidad.
$string['privacy:metadata'] = 'El plugin Tiny Teams Meeting no almacena datos personales';
$string['privacy:metadata:msteamsapp'] = 'El plugin Tiny Teams Meeting no almacena ningún dato. Sin embargo, envía el código de idioma del usuario a la aplicación Microsoft Teams para adaptar la interfaz al idioma del usuario.';
$string['privacy:metadata:msteamsapp:userlang'] = 'Código de idioma del usuario enviado a la aplicación Microsoft Teams.';
