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
 * Plugin upgrade steps.
 *
 * @package     tiny_teamsmeeting
 * @copyright   2023 Enovation Solutions
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute tiny_teamsmeeting upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tiny_teamsmeeting_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025100205) {
        $table = new xmldb_table('tiny_teamsmeeting');

        $starttime = new xmldb_field('starttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timecreated');
        if (!$dbman->field_exists($table, $starttime)) {
            $dbman->add_field($table, $starttime);
        }

        $endtime = new xmldb_field('endtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'starttime');
        if (!$dbman->field_exists($table, $endtime)) {
            $dbman->add_field($table, $endtime);
        }

        upgrade_plugin_savepoint(true, 2025100205, 'tiny', 'teamsmeeting');
    }

    return true;
}
