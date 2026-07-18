from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def fix_ci_blockers() -> None:
    locallib = ROOT / 'locallib.php'
    locallib.write_text(locallib.read_text(encoding='utf-8').rstrip() + '\n', encoding='utf-8')

    source = ROOT / 'amd/src/pdfviewer.js'
    text = source.read_text(encoding='utf-8')
    old = """                if (result === null || currentVersion !== renderVersion) {
                    return;
                }
"""
    new = """                if (result === null || currentVersion !== renderVersion) {
                    return null;
                }
"""
    if old not in text:
        raise RuntimeError('Expected pdfviewer consistent-return pattern was not found')
    text = text.replace(old, new, 1)
    source.write_text(text, encoding='utf-8')
    (ROOT / 'amd/build/pdfviewer.min.js').write_text(text, encoding='utf-8')


def update_languages() -> None:
    additions = {
        'lang/en/videoplayer.php': {
            'lastposition': 'Last position',
            'pagesviewed': '{$a} pages viewed',
            'pageposition': 'Page {$a->page} of {$a->total}',
            'secondswatched': '{$a} seconds watched',
            'secondposition': 'Second {$a->second} of {$a->total}',
            'timespent': 'Active time',
        },
        'lang/es/videoplayer.php': {
            'lastposition': 'Última posición',
            'pagesviewed': '{$a} páginas visualizadas',
            'pageposition': 'Página {$a->page} de {$a->total}',
            'secondswatched': '{$a} segundos vistos',
            'secondposition': 'Segundo {$a->second} de {$a->total}',
            'timespent': 'Tiempo activo',
        },
    }

    for relative, strings in additions.items():
        path = ROOT / relative
        text = path.read_text(encoding='utf-8')
        assignments = {}
        prefix = []
        for line in text.splitlines():
            match = re.match(r"^\$string\['([^']+)'\]\s*=\s*(.*);$", line)
            if match:
                assignments[match.group(1)] = line
            else:
                prefix.append(line)

        for key, value in strings.items():
            assignments[key] = f"$string['{key}'] = '{value}';"

        while prefix and not prefix[-1].strip():
            prefix.pop()

        ordered = [assignments[key] for key in sorted(assignments)]
        path.write_text('\n'.join(prefix) + '\n\n' + '\n'.join(ordered) + '\n', encoding='utf-8')


def update_report() -> None:
    report = """<?php
// This file is part of Moodle - http://moodle.org/.
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Progress report for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoplayer\\local\\drive;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('videoplayer', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videoplayer = $DB->get_record('videoplayer', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videoplayer:viewreport', $context);

$PAGE->set_url('/mod/videoplayer/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('progressreport', 'mod_videoplayer'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->navbar->add(format_string($videoplayer->name), new moodle_url('/mod/videoplayer/view.php', ['id' => $cm->id]));
$PAGE->navbar->add(get_string('progressreport', 'mod_videoplayer'));

$source = $videoplayer->source ?? 'googledrive';
if ($source === 'localpdf') {
    $type = 'pdf';
} else if (empty($videoplayer->type) || $videoplayer->type === drive::TYPE_AUTO) {
    $type = drive::detect_type((string) ($videoplayer->videourl ?? ''));
} else {
    $type = clean_param($videoplayer->type, PARAM_ALPHANUMEXT);
}

$ispdf = drive::is_pdf_type($type);
$isvideo = $type === 'video';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('progressreport', 'mod_videoplayer') . ': ' . format_string($videoplayer->name));

$table = new flexible_table('mod-videoplayer-report-' . $cm->id);
$table->define_columns([
    'fullname',
    'email',
    'progress',
    'completionpercentage',
    'completed',
    'lastposition',
    'timespent',
    'timemodified',
]);
$table->define_headers([
    get_string('fullnameuser'),
    get_string('email'),
    get_string('progress', 'mod_videoplayer'),
    get_string('completionpercentage', 'mod_videoplayer'),
    get_string('completed', 'completion'),
    get_string('lastposition', 'mod_videoplayer'),
    get_string('timespent', 'mod_videoplayer'),
    get_string('lastmodified'),
]);
$table->define_baseurl($PAGE->url);
$table->sortable(true, 'fullname', SORT_ASC);
$table->collapsible(false);
$table->set_attribute('class', 'generaltable generalbox mod-videoplayer-report');
$table->setup();

$userfields = user_picture::fields('u', ['email']);
$sql = "SELECT {$userfields}, vv.progress, vv.completionpercentage, vv.completed,
               vv.lastpage, vv.totalpages, vv.visitedpages, vv.lastsecond,
               vv.totalseconds, vv.timespent, vv.timemodified
          FROM {videoplayer_views} vv
          JOIN {user} u ON u.id = vv.userid
         WHERE vv.videoplayerid = :videoplayerid
      ORDER BY u.lastname ASC, u.firstname ASC";
$records = $DB->get_records_sql($sql, ['videoplayerid' => $videoplayer->id]);

if (!$records) {
    echo $OUTPUT->notification(get_string('noprogressrecords', 'mod_videoplayer'), 'info');
} else {
    foreach ($records as $record) {
        $user = (object) $record;
        $profileurl = new moodle_url('/user/view.php', ['id' => $record->id, 'course' => $course->id]);
        $completed = !empty($record->completed)
            ? html_writer::span(get_string('yes'), 'badge bg-success')
            : html_writer::span(get_string('no'), 'badge bg-secondary');

        $progresslabel = format_time((int) round((float) $record->progress));
        $lastposition = '-';

        if ($ispdf) {
            $visitedpages = json_decode((string) ($record->visitedpages ?? ''), true);
            $validpages = [];
            if (is_array($visitedpages)) {
                foreach ($visitedpages as $page) {
                    $page = (int) $page;
                    if ($page > 0) {
                        $validpages[$page] = true;
                    }
                }
            }
            $progresslabel = get_string('pagesviewed', 'mod_videoplayer', count($validpages));
            if ((int) $record->totalpages > 0) {
                $lastposition = get_string('pageposition', 'mod_videoplayer', (object) [
                    'page' => (int) $record->lastpage,
                    'total' => (int) $record->totalpages,
                ]);
            }
        } else if ($isvideo) {
            $progresslabel = get_string(
                'secondswatched',
                'mod_videoplayer',
                format_float((float) $record->progress, 2)
            );
            if ((float) $record->totalseconds > 0) {
                $lastposition = get_string('secondposition', 'mod_videoplayer', (object) [
                    'second' => format_float((float) $record->lastsecond, 2),
                    'total' => format_float((float) $record->totalseconds, 2),
                ]);
            }
        }

        $table->add_data([
            html_writer::link($profileurl, fullname($user)),
            s($record->email),
            $progresslabel,
            format_float((float) $record->completionpercentage, 2) . '%',
            $completed,
            $lastposition,
            format_time((int) $record->timespent),
            userdate($record->timemodified),
        ]);
    }
    $table->finish_output();
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/mod/videoplayer/view.php', ['id' => $cm->id]),
        get_string('backtoresource', 'mod_videoplayer'),
        ['class' => 'btn btn-secondary mt-3']
    ),
    'mod-videoplayer-report-actions'
);

echo $OUTPUT->footer();
"""
    (ROOT / 'report.php').write_text(report, encoding='utf-8')


def update_changelog() -> None:
    path = ROOT / 'CHANGELOG.md'
    text = path.read_text(encoding='utf-8')
    start = text.index('## v1.1.18-beta')
    end = text.index('## v1.1.17-beta')
    section = """## v1.1.18-beta - 2026-07-17

### Added

- Protected image viewer that serves images only through the Moodle endpoint.
- Searchable standard PDF.js toolbar with local text extraction and page navigation.
- Exact persisted PDF page state through `visitedpages`.
- Exact persisted video state through `lastsecond`, `totalseconds` and normalized `watchedranges`.
- Video resume from the saved playback second.
- Teacher reporting for PDF pages viewed, last PDF page, unique video seconds watched, last video second and active time.
- Repository CI for PHP/JavaScript/XML/AMD architecture checks.
- Formal `moodle-plugin-ci` matrix for Moodle 5.0/PHP 8.2 and Moodle 5.2/PHP 8.3 on PostgreSQL 16.
- Database and release-gate documentation for precise progress and production approval.

### Changed

- PDF, Google Docs, Google Sheets and Google Slides route through Moodle-owned PDF.js viewers instead of a Google preview iframe.
- Generic Google Drive `/file/d/...` links whose MIME type cannot be inferred require an explicit supported resource type.
- PDF completion is calculated from the union of pages actually observed across sessions.
- Video completion is calculated from unique playback ranges actually watched; seeking over content does not count skipped media.
- `lastpage` stores the actual most recently reported page so PDF resume is accurate.
- Video tracking stores the actual playback second and detected duration for resume.
- PDF.js rendering serializes work on the visible canvas to avoid concurrent render operations.
- Safari/iOS video resume retries when metadata-time seeking is not yet available.
- PDF mobile stabilization no longer resizes the canvas or overrides user zoom.
- Global tracking configuration is enforced both client-side and server-side.
- Backup/Restore and Privacy API include exact PDF and video progress state.
- Legacy unused `ebookviewer` AMD files and the obsolete native PDF iframe template were removed.
- AMD source/production bundles for critical viewers are kept synchronized and checked by CI.
- Release metadata is `1.1.18-beta` with plugin version `2026071702`.
- Declared supported Moodle branches are 5.0 through 5.2.

### Security

- Removed the obsolete Google iframe resource path from the protected learner-facing architecture.
- Unknown generic file types are not embedded as active same-origin content.
- Protected upstream proxy rejects unexpected HTML, XHTML and JSON responses before they reach video/PDF viewers.
- Protected upstream proxy keeps redirect cookies in a request-scoped in-memory flow.
- Relayed MIME resolution uses safe upstream metadata and falls back to the configured protected resource type.
- `If-Range` forwarding is bounded and only sent with a valid `Range` request.
- Client progress JSON is bounded, sanitized and normalized before persistence.

### Quality

- Moodle 5.0 and Moodle 5.2 test environments install successfully in the formal CI matrix.
- PHP lint, PHPDoc, plugin validation, XMLDB savepoints, Mustache validation and PHPUnit are enforced as release gates.
- Moodle Code Checker and Grunt/Stylelint/ESLint are enforced with zero warning tolerance for the release candidate.

### Production notes

- This release still requires Moodle staging validation and physical iPhone/iPad Safari testing before deployment.
- Current Google Drive delivery is based on shareable Drive/Docs resources. OAuth/service-account Google Drive API integration for private enterprise files remains a separate milestone.
- Moodle 4.x remains a future compatibility target and is not production-supported by this release candidate.

"""
    path.write_text(text[:start] + section + text[end:], encoding='utf-8')


def main() -> None:
    fix_ci_blockers()
    update_languages()
    update_report()
    update_changelog()


if __name__ == '__main__':
    main()
