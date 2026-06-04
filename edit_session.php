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
 * Edit a Teams meeting session record (title and schedule only — link is never modified).
 *
 * @package     tiny_teamsmeeting
 * @copyright   2023 Enovation Solutions
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tiny_teamsmeeting_report');

$id      = required_param('id', PARAM_INT);
$returnurl = new moodle_url('/lib/editor/tiny/plugins/teamsmeeting/report.php');

$record = $DB->get_record('tiny_teamsmeeting', ['id' => $id]);
if (!$record) {
    redirect($returnurl, get_string('edit_session_notfound', 'tiny_teamsmeeting'), null, \core\output\notification::NOTIFY_ERROR);
}

$form = new \tiny_teamsmeeting\form\edit_session_form(
    new moodle_url('/lib/editor/tiny/plugins/teamsmeeting/edit_session.php', ['id' => $id]),
    ['link' => $record->link]
);

// Pre-populate form with current values.
$form->set_data([
    'id'        => $record->id,
    'title'     => $record->title,
    'starttime' => $record->starttime ?? 0,
    'endtime'   => $record->endtime ?? 0,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $update = new stdClass();
    $update->id        = $record->id;
    $update->title     = $data->title;
    $update->starttime = !empty($data->starttime) ? (int)$data->starttime : null;
    $update->endtime   = !empty($data->endtime)   ? (int)$data->endtime   : null;
    // link, options, timecreated are intentionally NOT updated.

    $DB->update_record('tiny_teamsmeeting', $update);

    redirect($returnurl, get_string('edit_session_saved', 'tiny_teamsmeeting'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_title(get_string('edit_session', 'tiny_teamsmeeting'));
$PAGE->set_heading(get_string('edit_session', 'tiny_teamsmeeting'));
$PAGE->navbar->add(get_string('report', 'tiny_teamsmeeting'), $returnurl);
$PAGE->navbar->add(get_string('edit_session', 'tiny_teamsmeeting'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('edit_session', 'tiny_teamsmeeting'));

$form->display();

echo $OUTPUT->footer();
