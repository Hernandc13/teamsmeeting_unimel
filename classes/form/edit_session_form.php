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
 * Form for editing a Teams meeting session record.
 *
 * @package     tiny_teamsmeeting
 * @copyright   2023 Enovation Solutions
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_teamsmeeting\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Edit session form — allows changing title and schedule without touching the meeting link.
 */
class edit_session_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Read-only display of the meeting link (not a real form element, just informational).
        $link = $this->_customdata['link'] ?? '';
        if ($link) {
            $mform->addElement('static', 'link_display',
                get_string('edit_session_link', 'tiny_teamsmeeting'),
                \html_writer::link($link, $link, ['target' => '_blank', 'rel' => 'noopener noreferrer'])
            );
        }

        $mform->addElement('text', 'title',
            get_string('edit_session_title', 'tiny_teamsmeeting'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('date_time_selector', 'starttime',
            get_string('edit_session_starttime', 'tiny_teamsmeeting'), ['optional' => true]);

        $mform->addElement('date_time_selector', 'endtime',
            get_string('edit_session_endtime', 'tiny_teamsmeeting'), ['optional' => true]);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Extra validation: end time must be after start time when both are set.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['starttime']) && !empty($data['endtime'])
                && $data['endtime'] <= $data['starttime']) {
            $errors['endtime'] = get_string('endbeforestart', 'error');
        }

        return $errors;
    }
}
