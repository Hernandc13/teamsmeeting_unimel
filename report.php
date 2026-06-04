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
 * Admin report: calendar view of all registered Teams meeting sessions.
 *
 * @package     tiny_teamsmeeting
 * @copyright   2023 Enovation Solutions
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tiny_teamsmeeting_report');

// ── Parameters ────────────────────────────────────────────────────────────────
$download = optional_param('download', '', PARAM_ALPHA);
$year     = optional_param('year',  (int)userdate(time(), '%Y'), PARAM_INT);
$month    = optional_param('month', (int)userdate(time(), '%m'), PARAM_INT);

if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$baseurl = new moodle_url('/lib/editor/tiny/plugins/teamsmeeting/report.php');
$editurl = new moodle_url('/lib/editor/tiny/plugins/teamsmeeting/edit_session.php');

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$timeformat = get_string('strftimetime', 'langconfig');

// ── Duplicate detection (global, all sessions) ────────────────────────────────
// Load every session ordered newest-first. Within sessions sharing the same
// title the first one encountered is "active"; the rest are "replaced".
$allSessions = $DB->get_records('tiny_teamsmeeting', null, 'timecreated DESC');

$seenTitles  = [];   // normalized title => id of the active session
$replacedIds = [];   // id => true

foreach ($allSessions as $rec) {
    $key = mb_strtolower(trim($rec->title ?? ''), 'UTF-8');
    if (!isset($seenTitles[$key])) {
        $seenTitles[$key] = $rec->id;   // most recent = active
    } else {
        $replacedIds[$rec->id] = true;  // older duplicate = replaced
    }
}

// ── Excel download ────────────────────────────────────────────────────────────
if ($download === 'excel') {
    $dash = '—';

    $columns = [
        'status'      => get_string('report_col_status',     'tiny_teamsmeeting'),
        'title'       => get_string('report_col_title',      'tiny_teamsmeeting'),
        'starttime'   => get_string('report_col_starttime',  'tiny_teamsmeeting'),
        'endtime'     => get_string('report_col_endtime',    'tiny_teamsmeeting'),
        'timecreated' => get_string('report_col_timecreated','tiny_teamsmeeting'),
        'link'        => get_string('report_col_link',       'tiny_teamsmeeting'),
        'options'     => get_string('report_col_options',    'tiny_teamsmeeting'),
    ];

    $rows = array_map(function($r) use ($dateformat, $dash, $replacedIds) {
        $status = isset($replacedIds[$r->id])
            ? get_string('report_status_replaced', 'tiny_teamsmeeting')
            : get_string('report_status_active',   'tiny_teamsmeeting');
        return [
            'status'      => $status,
            'title'       => $r->title ?? '',
            'starttime'   => !empty($r->starttime) ? userdate($r->starttime, $dateformat) : $dash,
            'endtime'     => !empty($r->endtime)   ? userdate($r->endtime,   $dateformat) : $dash,
            'timecreated' => userdate($r->timecreated, $dateformat),
            'link'        => $r->link ?? '',
            'options'     => $r->options ?? '',
        ];
    }, $allSessions);

    \core\dataformat::download_data(
        'teams-meeting-sessions',
        'excel',
        $columns,
        new ArrayIterator($rows)
    );
    exit;
}

// ── Load meetings for the visible month ───────────────────────────────────────
$monthstart = mktime(0, 0, 0, $month, 1, $year);
$monthend   = mktime(23, 59, 59, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year), $year);

// Scheduled meetings (have starttime in this month).
$scheduled = $DB->get_records_sql(
    'SELECT * FROM {tiny_teamsmeeting} WHERE starttime >= :ms AND starttime <= :me ORDER BY starttime ASC',
    ['ms' => $monthstart, 'me' => $monthend]
);

// Unscheduled meetings created in this month (shown on creation day).
$created = $DB->get_records_sql(
    'SELECT * FROM {tiny_teamsmeeting}
      WHERE (starttime IS NULL OR starttime = 0)
        AND timecreated >= :ms AND timecreated <= :me
      ORDER BY timecreated ASC',
    ['ms' => $monthstart, 'me' => $monthend]
);

// Index by day-of-month: [ d => [ ['rec'=>…, 'scheduled'=>bool], … ] ]
$byDay = [];
foreach ($scheduled as $rec) {
    $byDay[(int)userdate($rec->starttime, '%d')][] = ['rec' => $rec, 'scheduled' => true];
}
foreach ($created as $rec) {
    $byDay[(int)userdate($rec->timecreated, '%d')][] = ['rec' => $rec, 'scheduled' => false];
}
foreach ($byDay as $d => &$items) {
    usort($items, fn($a, $b) =>
        (!empty($a['rec']->starttime) ? $a['rec']->starttime : $a['rec']->timecreated)
        <=>
        (!empty($b['rec']->starttime) ? $b['rec']->starttime : $b['rec']->timecreated)
    );
}
unset($items);

// ── Page output ───────────────────────────────────────────────────────────────
$PAGE->set_title(get_string('report', 'tiny_teamsmeeting'));
$PAGE->set_heading(get_string('report_heading', 'tiny_teamsmeeting'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_heading', 'tiny_teamsmeeting'));

echo html_writer::tag('style', '
.tmcal-wrapper              { max-width: 1100px; }
.tmcal-nav                  { display:flex; align-items:center; justify-content:space-between; margin-bottom:.75rem; }
.tmcal-nav h3               { margin:0; font-size:1.4rem; font-weight:600; }
.tmcal-grid                 { display:grid; grid-template-columns:repeat(7,1fr);
                               border-left:1px solid #dee2e6; border-top:1px solid #dee2e6; }
.tmcal-dayname              { background:#f8f9fa; text-align:center; font-weight:600; font-size:.8rem;
                               padding:.4rem 0; border-right:1px solid #dee2e6; border-bottom:1px solid #dee2e6;
                               text-transform:uppercase; color:#6c757d; }
.tmcal-cell                 { min-height:110px; padding:.4rem; border-right:1px solid #dee2e6;
                               border-bottom:1px solid #dee2e6; background:#fff; }
.tmcal-cell.empty           { background:#f8f9fa; }
.tmcal-cell.today           { background:#fffbf0; }
.tmcal-daynumber            { font-size:.85rem; font-weight:600; color:#495057; margin-bottom:.3rem; }
.tmcal-cell.today .tmcal-daynumber { background:#6264a7; color:#fff; border-radius:50%;
                               width:24px; height:24px; display:flex; align-items:center; justify-content:center; }
.tmcal-event                { border-radius:4px; padding:.25rem .4rem; margin-bottom:.3rem;
                               font-size:.75rem; line-height:1.35; word-break:break-word; }
/* Active + scheduled */
.tmcal-event.is-scheduled   { background:#6264a7; color:#fff; }
/* Active + no schedule */
.tmcal-event.is-created     { background:#adb5bd; color:#fff; }
/* Replaced (duplicate, older) */
.tmcal-event.is-replaced    { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
.tmcal-event.is-replaced a  { color:#856404; }
.tmcal-event a              { color:#fff; text-decoration:none; font-weight:600; }
.tmcal-event a:hover        { text-decoration:underline; }
.tmcal-event-time           { font-size:.7rem; opacity:.85; margin-top:.1rem; }
.tmcal-replaced-badge       { font-size:.65rem; background:#ffc107; color:#333; border-radius:3px;
                               padding:0 .3rem; margin-left:.25rem; font-weight:600; vertical-align:middle; }
.tmcal-legend               { display:flex; flex-wrap:wrap; gap:.75rem 1.25rem; align-items:center;
                               margin-bottom:.6rem; font-size:.8rem; color:#495057; }
.tmcal-legend-dot           { width:13px; height:13px; border-radius:3px; display:inline-block;
                               vertical-align:middle; margin-right:.25rem; }
');

echo html_writer::start_div('tmcal-wrapper');

// ── Legend ───────────────────────────────────────────────────────────────────
echo html_writer::start_div('tmcal-legend');
foreach ([
    ['#6264a7', get_string('report_legend_scheduled', 'tiny_teamsmeeting')],
    ['#adb5bd', get_string('report_legend_created',   'tiny_teamsmeeting')],
    ['#ffc107', get_string('report_legend_replaced',  'tiny_teamsmeeting')],
] as [$color, $label]) {
    echo html_writer::span(
        html_writer::tag('span', '', ['class' => 'tmcal-legend-dot', 'style' => "background:$color"]) . $label
    );
}
echo html_writer::end_div();

// ── Navigation + download ─────────────────────────────────────────────────────
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1)  { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1;  $nextYear++; }

$prevurl    = new moodle_url($baseurl, ['year' => $prevYear, 'month' => $prevMonth]);
$nexturl    = new moodle_url($baseurl, ['year' => $nextYear, 'month' => $nextMonth]);
$dlurl      = new moodle_url($baseurl, ['download' => 'excel']);
$monthLabel = userdate($monthstart, get_string('strftimemonthyear', 'langconfig'));

echo html_writer::start_div('tmcal-nav');
echo html_writer::start_div('d-flex align-items-center gap-2');
echo html_writer::link($prevurl, '&#8249;', ['class' => 'btn btn-outline-secondary btn-sm']);
echo html_writer::tag('h3', s($monthLabel));
echo html_writer::link($nexturl, '&#8250;', ['class' => 'btn btn-outline-secondary btn-sm']);
echo html_writer::end_div();
echo html_writer::link($dlurl, '&#8595; ' . get_string('report_download_excel', 'tiny_teamsmeeting'),
    ['class' => 'btn btn-secondary btn-sm']);
echo html_writer::end_div(); // tmcal-nav

// ── Calendar grid ─────────────────────────────────────────────────────────────
$daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstWeekday = (int)date('N', $monthstart);
$todayDay     = (int)userdate(time(), '%d');
$todayMonth   = (int)userdate(time(), '%m');
$todayYear    = (int)userdate(time(), '%Y');

$dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

echo html_writer::start_div('tmcal-grid');

foreach ($dayNames as $dn) {
    echo html_writer::div(s($dn), 'tmcal-dayname');
}

for ($i = 1; $i < $firstWeekday; $i++) {
    echo html_writer::div('', 'tmcal-cell empty');
}

for ($d = 1; $d <= $daysInMonth; $d++) {
    $isToday   = ($d === $todayDay && $month === $todayMonth && $year === $todayYear);
    $cellClass = 'tmcal-cell' . ($isToday ? ' today' : '');

    echo html_writer::start_div($cellClass);
    echo html_writer::div((string)$d, 'tmcal-daynumber');

    if (!empty($byDay[$d])) {
        foreach ($byDay[$d] as $item) {
            $rec         = $item['rec'];
            $isScheduled = $item['scheduled'];
            $isReplaced  = isset($replacedIds[$rec->id]);

            if ($isReplaced) {
                $eventClass = 'tmcal-event is-replaced';
            } elseif ($isScheduled) {
                $eventClass = 'tmcal-event is-scheduled';
            } else {
                $eventClass = 'tmcal-event is-created';
            }

            $title = s($rec->title ?: '(sin título)');

            // Replaced sessions get a warning badge instead of a join icon.
            if ($isReplaced) {
                $badge    = html_writer::tag('span', get_string('report_badge_replaced', 'tiny_teamsmeeting'),
                    ['class' => 'tmcal-replaced-badge',
                     'title' => get_string('report_badge_replaced_title', 'tiny_teamsmeeting')]);
                $content  = html_writer::link(new moodle_url($editurl, ['id' => $rec->id]), $title) . $badge;
                $timeHtml = '';
            } else {
                $timeStr  = $isScheduled && !empty($rec->starttime) ? userdate($rec->starttime, $timeformat) : '';
                $joinlink = !empty($rec->link)
                    ? ' ' . html_writer::link($rec->link, '&#x1F4F9;',
                        ['target' => '_blank', 'rel' => 'noopener noreferrer',
                         'title' => get_string('report_join', 'tiny_teamsmeeting'),
                         'style' => 'font-size:.85rem;'])
                    : '';
                $content  = html_writer::link(new moodle_url($editurl, ['id' => $rec->id]), $title) . $joinlink;
                $timeHtml = $timeStr ? html_writer::div($timeStr, 'tmcal-event-time') : '';
            }

            $tooltip = $isReplaced ? get_string('report_badge_replaced_title', 'tiny_teamsmeeting')
                     : (!$isScheduled ? get_string('report_legend_created', 'tiny_teamsmeeting') : '');

            $attr = ['class' => $eventClass];
            if ($tooltip) {
                $attr['title'] = $tooltip;
            }
            echo html_writer::div($content . $timeHtml, '', $attr);
        }
    }

    echo html_writer::end_div(); // tmcal-cell
}

$totalCells = $firstWeekday - 1 + $daysInMonth;
$trailing   = (7 - ($totalCells % 7)) % 7;
for ($i = 0; $i < $trailing; $i++) {
    echo html_writer::div('', 'tmcal-cell empty');
}

echo html_writer::end_div(); // tmcal-grid
echo html_writer::end_div(); // tmcal-wrapper

echo $OUTPUT->footer();
