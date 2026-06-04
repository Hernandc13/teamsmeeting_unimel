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

// Clamp month.
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$baseurl = new moodle_url('/lib/editor/tiny/plugins/teamsmeeting/report.php');
$editurl = new moodle_url('/lib/editor/tiny/plugins/teamsmeeting/edit_session.php');

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$timeformat = get_string('strftimetime', 'langconfig');

// ── Excel download ────────────────────────────────────────────────────────────
if ($download === 'excel') {
    $records = $DB->get_records('tiny_teamsmeeting', null, 'timecreated DESC');
    $dash    = '—';

    $columns = [
        'title'       => get_string('report_col_title',       'tiny_teamsmeeting'),
        'starttime'   => get_string('report_col_starttime',   'tiny_teamsmeeting'),
        'endtime'     => get_string('report_col_endtime',     'tiny_teamsmeeting'),
        'timecreated' => get_string('report_col_timecreated', 'tiny_teamsmeeting'),
        'link'        => get_string('report_col_link',        'tiny_teamsmeeting'),
        'options'     => get_string('report_col_options',     'tiny_teamsmeeting'),
    ];

    $rows = array_map(function($r) use ($dateformat, $dash) {
        return [
            'title'       => $r->title ?? '',
            'starttime'   => !empty($r->starttime) ? userdate($r->starttime, $dateformat) : $dash,
            'endtime'     => !empty($r->endtime)   ? userdate($r->endtime,   $dateformat) : $dash,
            'timecreated' => userdate($r->timecreated, $dateformat),
            'link'        => $r->link ?? '',
            'options'     => $r->options ?? '',
        ];
    }, $records);

    \core\dataformat::download_data(
        'teams-meeting-sessions',
        'excel',
        $columns,
        new ArrayIterator($rows)
    );
    exit;
}

// ── Load meetings for current month ──────────────────────────────────────────
$monthstart = mktime(0, 0, 0, $month, 1, $year);
$monthend   = mktime(23, 59, 59, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year), $year);

// Meetings scheduled this month (have starttime inside the month range).
$scheduled = $DB->get_records_sql(
    'SELECT * FROM {tiny_teamsmeeting} WHERE starttime >= :ms AND starttime <= :me ORDER BY starttime ASC',
    ['ms' => $monthstart, 'me' => $monthend]
);

// Meetings created this month but without a starttime — shown on their creation day (grey).
$created = $DB->get_records_sql(
    'SELECT * FROM {tiny_teamsmeeting}
      WHERE (starttime IS NULL OR starttime = 0)
        AND timecreated >= :ms AND timecreated <= :me
      ORDER BY timecreated ASC',
    ['ms' => $monthstart, 'me' => $monthend]
);

// Index both sets by day-of-month.
// $byDay[$d] = [ ['rec'=>..., 'scheduled'=>bool], ... ]
$byDay = [];
foreach ($scheduled as $rec) {
    $day = (int)userdate($rec->starttime, '%d');
    $byDay[$day][] = ['rec' => $rec, 'scheduled' => true];
}
foreach ($created as $rec) {
    $day = (int)userdate($rec->timecreated, '%d');
    $byDay[$day][] = ['rec' => $rec, 'scheduled' => false];
}
// Sort each day by time.
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

// Inline styles for the calendar.
echo html_writer::tag('style', '
.tmcal-wrapper            { max-width: 1100px; }
.tmcal-nav                { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
.tmcal-nav h3             { margin:0; font-size:1.4rem; font-weight:600; }
.tmcal-grid               { display:grid; grid-template-columns:repeat(7,1fr);
                             border-left:1px solid #dee2e6; border-top:1px solid #dee2e6; }
.tmcal-dayname            { background:#f8f9fa; text-align:center; font-weight:600; font-size:.8rem;
                             padding:.4rem 0; border-right:1px solid #dee2e6; border-bottom:1px solid #dee2e6;
                             text-transform:uppercase; color:#6c757d; }
.tmcal-cell               { min-height:110px; padding:.4rem; border-right:1px solid #dee2e6;
                             border-bottom:1px solid #dee2e6; vertical-align:top; background:#fff; }
.tmcal-cell.empty         { background:#f8f9fa; }
.tmcal-cell.today         { background:#fffbf0; }
.tmcal-daynumber          { font-size:.85rem; font-weight:600; color:#495057; margin-bottom:.3rem; }
.tmcal-cell.today .tmcal-daynumber { background:#6264a7; color:#fff; border-radius:50%;
                             width:24px; height:24px; display:flex; align-items:center; justify-content:center; }
.tmcal-event              { border-radius:4px; padding:.2rem .4rem; margin-bottom:.25rem;
                             font-size:.75rem; line-height:1.3; word-break:break-word; }
.tmcal-event.is-scheduled { background:#6264a7; color:#fff; }
.tmcal-event.is-created   { background:#adb5bd; color:#fff; }
.tmcal-event a            { color:#fff; text-decoration:none; font-weight:600; }
.tmcal-event a:hover      { text-decoration:underline; }
.tmcal-event-time         { font-size:.7rem; opacity:.85; }
.tmcal-legend             { display:flex; gap:1rem; align-items:center; margin-bottom:.75rem; font-size:.8rem; color:#495057; }
.tmcal-legend-dot         { width:12px; height:12px; border-radius:3px; display:inline-block; }
');

echo html_writer::start_div('tmcal-wrapper');

// ── Legend ───────────────────────────────────────────────────────────────────
echo html_writer::start_div('tmcal-legend');
echo html_writer::span(
    html_writer::tag('span', '', ['class' => 'tmcal-legend-dot', 'style' => 'background:#6264a7']) .
    ' ' . get_string('report_legend_scheduled', 'tiny_teamsmeeting')
);
echo html_writer::span(
    html_writer::tag('span', '', ['class' => 'tmcal-legend-dot', 'style' => 'background:#adb5bd']) .
    ' ' . get_string('report_legend_created', 'tiny_teamsmeeting')
);
echo html_writer::end_div();

// ── Navigation bar ────────────────────────────────────────────────────────────
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$prevurl = new moodle_url($baseurl, ['year' => $prevYear,  'month' => $prevMonth]);
$nexturl = new moodle_url($baseurl, ['year' => $nextYear,  'month' => $nextMonth]);
$dlurl   = new moodle_url($baseurl, ['download' => 'excel']);

$monthLabel = userdate($monthstart, get_string('strftimemonthyear', 'langconfig'));

echo html_writer::start_div('tmcal-nav');

// Left: prev + month label + next.
echo html_writer::start_div('d-flex align-items-center gap-2');
echo html_writer::link($prevurl, '&#8249;', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Previous month']);
echo html_writer::tag('h3', s($monthLabel));
echo html_writer::link($nexturl, '&#8250;', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Next month']);
echo html_writer::end_div();

// Right: Excel download button.
echo html_writer::link($dlurl,
    '&#8595; ' . get_string('report_download_excel', 'tiny_teamsmeeting'),
    ['class' => 'btn btn-secondary btn-sm']
);

echo html_writer::end_div(); // tmcal-nav

// ── Calendar grid ─────────────────────────────────────────────────────────────
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstWeekday = (int)date('N', $monthstart); // 1=Mon … 7=Sun
$todayDay     = (int)userdate(time(), '%d');
$todayMonth   = (int)userdate(time(), '%m');
$todayYear    = (int)userdate(time(), '%Y');

$dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

echo html_writer::start_div('tmcal-grid');

// Day name headers.
foreach ($dayNames as $dn) {
    echo html_writer::div(s($dn), 'tmcal-dayname');
}

// Empty cells before day 1.
for ($i = 1; $i < $firstWeekday; $i++) {
    echo html_writer::div('', 'tmcal-cell empty');
}

// Day cells.
for ($d = 1; $d <= $daysInMonth; $d++) {
    $isToday = ($d === $todayDay && $month === $todayMonth && $year === $todayYear);
    $cellClass = 'tmcal-cell' . ($isToday ? ' today' : '');

    echo html_writer::start_div($cellClass);
    echo html_writer::div((string)$d, 'tmcal-daynumber');

    if (!empty($byDay[$d])) {
        foreach ($byDay[$d] as $item) {
            $rec       = $item['rec'];
            $isScheduled = $item['scheduled'];
            $eventClass  = 'tmcal-event ' . ($isScheduled ? 'is-scheduled' : 'is-created');

            $timeStr = $isScheduled && !empty($rec->starttime)
                ? userdate($rec->starttime, $timeformat)
                : '';

            $tooltip = $isScheduled
                ? ''
                : get_string('report_legend_created', 'tiny_teamsmeeting');

            $editlink = html_writer::link(
                new moodle_url($editurl, ['id' => $rec->id]),
                s($rec->title ?: '(sin título)')
            );

            $joinlink = !empty($rec->link)
                ? ' ' . html_writer::link($rec->link, '&#x1F4F9;',
                    ['target' => '_blank', 'rel' => 'noopener noreferrer',
                     'title' => get_string('report_join', 'tiny_teamsmeeting'),
                     'style' => 'font-size:.85rem;'])
                : '';

            $timeHtml = $timeStr ? html_writer::div($timeStr, 'tmcal-event-time') : '';

            $attr = ['class' => $eventClass];
            if ($tooltip) {
                $attr['title'] = $tooltip;
            }
            echo html_writer::div($editlink . $joinlink . $timeHtml, '', $attr);
        }
    }

    echo html_writer::end_div(); // tmcal-cell
}

// Trailing empty cells to complete last row.
$totalCells = $firstWeekday - 1 + $daysInMonth;
$trailing   = (7 - ($totalCells % 7)) % 7;
for ($i = 0; $i < $trailing; $i++) {
    echo html_writer::div('', 'tmcal-cell empty');
}

echo html_writer::end_div(); // tmcal-grid

// Meetings outside any month view (no starttime and created outside current month)
// are reachable by navigating to the month they were created in.
// No separate table needed — all meetings appear on the calendar in their respective month.

echo html_writer::end_div(); // tmcal-wrapper

echo $OUTPUT->footer();
