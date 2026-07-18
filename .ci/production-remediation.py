from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

BOILERPLATE = """// This file is part of Moodle - http://moodle.org/.
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
"""


def normalize_php_headers() -> None:
    phpfiles = [p for p in ROOT.rglob('*.php') if '.git' not in p.parts and '.ci' not in p.parts]
    for path in phpfiles:
        text = path.read_text(encoding='utf-8').replace('\r\n', '\n')
        if not text.startswith('<?php'):
            continue
        rest = text[len('<?php'):].lstrip('\n')
        lines = rest.splitlines(True)
        index = 0
        if lines and lines[0].lstrip().startswith('//'):
            while index < len(lines) and (lines[index].lstrip().startswith('//') or not lines[index].strip()):
                index += 1
            rest = ''.join(lines[index:]).lstrip('\n')
        text = '<?php\n' + BOILERPLATE + '\n' + rest
        text = text.replace('{\n\n    /**', '{\n    /**')
        path.write_text(text, encoding='utf-8')

    descriptions = {
        'version.php': 'Plugin version metadata for Drive Resource.',
        'view.php': 'Display the Drive Resource activity.',
        'protected.php': 'Authorised protected resource delivery endpoint.',
        'lib.php': 'Core library callbacks for Drive Resource.',
        'locallib.php': 'Local library for Drive Resource.',
        'settings.php': 'Administrative settings for Drive Resource.',
        'db/tasks.php': 'Scheduled task definitions for Drive Resource.',
        'lang/en/videoplayer.php': 'English language strings for Drive Resource.',
        'lang/es/videoplayer.php': 'Spanish language strings for Drive Resource.',
    }
    marker = BOILERPLATE + '\n'
    for relative, description in descriptions.items():
        path = ROOT / relative
        text = path.read_text(encoding='utf-8')
        before, after = text.split(marker, 1)
        if not after.lstrip().startswith('/**'):
            docblock = (
                '/**\n'
                f' * {description}\n'
                ' *\n'
                ' * @package    mod_videoplayer\n'
                ' * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud\n'
                ' * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later\n'
                ' */\n\n'
            )
            path.write_text(before + marker + docblock + after, encoding='utf-8')

    for relative in [
        'db/upgrade.php',
        'classes/local/http_range_proxy.php',
        'classes/task/precache_pdf.php',
        'classes/task/cleanup_pdf_cache.php',
        'backup/moodle2/restore_videoplayer_stepslib.php',
        'backup/moodle2/backup_videoplayer_stepslib.php',
        'lib.php',
    ]:
        path = ROOT / relative
        text = path.read_text(encoding='utf-8')
        text = re.sub(r"\ndefined\('MOODLE_INTERNAL'\) \|\| die\(\);\n", '\n', text, count=1)
        path.write_text(text, encoding='utf-8')


def normalize_languages() -> None:
    values = {
        'lang/en/videoplayer.php': {
            'videoplayer:addinstance': 'Add a new Drive Resource',
            'trackingdisabled': 'Progress tracking is disabled by the site administrator.',
        },
        'lang/es/videoplayer.php': {
            'videoplayer:addinstance': 'Agregar un nuevo Drive Resource',
            'trackingdisabled': 'El seguimiento de progreso está desactivado por el administrador del sitio.',
        },
    }
    for relative, additions in values.items():
        path = ROOT / relative
        text = path.read_text(encoding='utf-8')
        for key, value in additions.items():
            if f"$string['{key}']" not in text:
                text += f"\n$string['{key}'] = '{value}';\n"
        assignments = []
        preserved = []
        for line in text.splitlines():
            match = re.match(r"^\$string\['([^']+)'\]\s*=\s*(.*);$", line)
            if match:
                assignments.append((match.group(1), line))
            else:
                preserved.append(line)
        while preserved and not preserved[-1].strip():
            preserved.pop()
        assignments.sort(key=lambda item: item[0])
        path.write_text(
            '\n'.join(preserved) + '\n\n' + '\n'.join(line for _, line in assignments) + '\n',
            encoding='utf-8',
        )


def fix_php_style() -> None:
    path = ROOT / 'classes/privacy/provider.php'
    text = path.read_text(encoding='utf-8').replace(
        "class provider implements\n"
        "    \\core_privacy\\local\\metadata\\provider,\n"
        "    \\core_privacy\\local\\request\\plugin\\provider,\n"
        "    \\core_privacy\\local\\request\\core_userlist_provider {",
        "class provider implements\n"
        "    \\core_privacy\\local\\metadata\\provider,\n"
        "    \\core_privacy\\local\\request\\core_userlist_provider,\n"
        "    \\core_privacy\\local\\request\\plugin\\provider {",
    )
    path.write_text(text, encoding='utf-8')

    path = ROOT / 'classes/local/http_range_proxy.php'
    text = path.read_text(encoding='utf-8')
    text = text.replace(
        'static function($curl, string $header) use (&$responseheaders, &$discardbody): int {',
        'static function ($curl, string $header) use (&$responseheaders, &$discardbody): int {',
    )
    text = text.replace(
        "CURLOPT_WRITEFUNCTION => static function($curl, string $data) use (\n"
        "                &$headerssent,",
        "CURLOPT_WRITEFUNCTION => static function (\n"
        "                $curl,\n"
        "                string $data\n"
        "            ) use (\n"
        "                &$headerssent,",
    )
    path.write_text(text, encoding='utf-8')

    path = ROOT / 'classes/local/progress/progress_service.php'
    text = path.read_text(encoding='utf-8')
    text = text.replace(
        "     * @param \\cm_info|object $cm\n"
        "     * @param object $course\n"
        "     * @param object $videoplayer\n"
        "     * @param \\context_module $context\n"
        "     * @param int $userid\n"
        "     * @param array $input\n"
        "     * @return array",
        "     * @param object $cm Course module information.\n"
        "     * @param object $course Course record.\n"
        "     * @param object $videoplayer Drive Resource activity record.\n"
        "     * @param \\context_module $context Module context.\n"
        "     * @param int $userid User ID.\n"
        "     * @param array $input Sanitised progress input.\n"
        "     * @return array Persisted progress state.",
    )
    text = text.replace(
        'usort($ranges, static function(array $a, array $b): int {',
        'usort($ranges, static function (array $a, array $b): int {',
    )
    path.write_text(text, encoding='utf-8')

    path = ROOT / 'db/upgrade.php'
    text = path.read_text(encoding='utf-8').replace(
        "            $sourcefield = new xmldb_field('source', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'googledrive', 'introformat');",
        "            $sourcefield = new xmldb_field(\n"
        "                'source',\n"
        "                XMLDB_TYPE_CHAR,\n"
        "                '32',\n"
        "                null,\n"
        "                XMLDB_NOTNULL,\n"
        "                null,\n"
        "                'googledrive',\n"
        "                'introformat'\n"
        "            );",
    )
    path.write_text(text, encoding='utf-8')

    path = ROOT / 'classes/event/reward_awarded.php'
    text = path.read_text(encoding='utf-8').replace(
        "        return \"The user with id '{$this->userid}' earned reward '{$this->other['rewardkey']}' in Drive Resource with id '{$this->other['videoplayerid']}'.\";",
        "        return \"The user with id '{$this->userid}' earned reward '{$this->other['rewardkey']}' \"\n"
        "            . \"in Drive Resource with id '{$this->other['videoplayerid']}'.\";",
    )
    path.write_text(text, encoding='utf-8')

    path = ROOT / 'report.php'
    text = path.read_text(encoding='utf-8').replace(
        "    html_writer::link(new moodle_url('/mod/videoplayer/view.php', ['id' => $cm->id]), get_string('backtoresource', 'mod_videoplayer'), ['class' => 'btn btn-secondary mt-3']),",
        "    html_writer::link(\n"
        "        new moodle_url('/mod/videoplayer/view.php', ['id' => $cm->id]),\n"
        "        get_string('backtoresource', 'mod_videoplayer'),\n"
        "        ['class' => 'btn btn-secondary mt-3']\n"
        "    ),",
    )
    path.write_text(text, encoding='utf-8')

    path = ROOT / 'lib.php'
    text = path.read_text(encoding='utf-8')
    if '/** File area used for protected local PDF files. */' not in text:
        text = text.replace(
            "const VIDEOPLAYER_LOCALPDF_FILEAREA = 'localpdf';",
            "/** File area used for protected local PDF files. */\n"
            "const VIDEOPLAYER_LOCALPDF_FILEAREA = 'localpdf';",
        )
    for function_name in re.findall(r'function\s+(videoplayer_[A-Za-z0-9_]+)\s*\(', text):
        position = text.index('function ' + function_name)
        start = text.rfind('/**', 0, position)
        end = text.find('*/', start, position) + 2
        docblock = text[start:end]
        if '@package' not in docblock:
            replacement = docblock[:-2].rstrip() + '\n * @package    mod_videoplayer\n */'
            text = text[:start] + replacement + text[end:]
    path.write_text(text, encoding='utf-8')


def rewrite_backup_tasks() -> None:
    backup = """<?php
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
 * Backup task for Drive Resource.
 *
 * @package    mod_videoplayer
 * @category   backup
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoplayer/backup/moodle2/backup_videoplayer_stepslib.php');

/**
 * Defines the backup task for Drive Resource.
 *
 * @package    mod_videoplayer
 * @category   backup
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_videoplayer_activity_task extends backup_activity_task {
    /**
     * Define activity-specific backup settings.
     */
    protected function define_my_settings(): void {
        // No activity-specific backup settings are required.
    }

    /**
     * Define backup execution steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_videoplayer_activity_structure_step('videoplayer_structure', 'videoplayer.xml'));
    }

    /**
     * Encode links to Drive Resource activities.
     *
     * @param string $content Content to encode.
     * @return string Encoded content.
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $search = '/(' . $base . '\\/mod\\/videoplayer\\/view\\.php\\?id\\=)([0-9]+)/';

        return preg_replace($search, '$@VIDEOPLAYERVIEWBYID*$2@$', $content);
    }
}
"""
    (ROOT / 'backup/moodle2/backup_videoplayer_activity_task.class.php').write_text(backup, encoding='utf-8')

    restore = """<?php
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
 * Restore task for Drive Resource.
 *
 * @package    mod_videoplayer
 * @category   backup
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoplayer/backup/moodle2/restore_videoplayer_stepslib.php');

/**
 * Defines the restore task for Drive Resource.
 *
 * @package    mod_videoplayer
 * @category   backup
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_videoplayer_activity_task extends restore_activity_task {
    /**
     * Define activity-specific restore settings.
     */
    protected function define_my_settings(): void {
        // No activity-specific restore settings are required.
    }

    /**
     * Define restore execution steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_videoplayer_activity_structure_step('videoplayer_structure', 'videoplayer.xml'));
    }

    /**
     * Define fields whose embedded links Moodle should decode.
     *
     * @return restore_decode_content[] Decode content definitions.
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('videoplayer', ['intro'], 'videoplayer'),
        ];
    }

    /**
     * Define Drive Resource URL decode rules.
     *
     * @return restore_decode_rule[] Decode rules.
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('VIDEOPLAYERVIEWBYID', '/mod/videoplayer/view.php?id=$1', 'course_module'),
        ];
    }
}
"""
    (ROOT / 'backup/moodle2/restore_videoplayer_activity_task.class.php').write_text(restore, encoding='utf-8')


def fix_templates() -> None:
    for relative in ['templates/pdf.mustache', 'amd/src/ebookviewer.js', 'amd/build/ebookviewer.min.js']:
        path = ROOT / relative
        if path.exists():
            path.unlink()

    examples = {
        'templates/image.mustache': '''
    Example context (json):
    {
        "type": "image",
        "cmid": 42,
        "resourcetype": "Resource type: Image",
        "imageurl": "https://example.invalid/protected.php?id=42",
        "title": "Protected image",
        "disablecontextmenu": true,
        "enablewatermark": true,
        "watermark": "Example User"
    }
''',
        'templates/video.mustache': '''
    Example context (json):
    {
        "type": "video",
        "cmid": 42,
        "trackingcmid": 42,
        "resourcetype": "Resource type: Video",
        "videourl": "https://example.invalid/protected.php?id=42",
        "title": "Protected video",
        "disablecontextmenu": true,
        "lastsecond": 15.5,
        "totalseconds": 120,
        "initialtimespent": 20,
        "watchedranges": "[[0,15.5]]",
        "completionpercent": 12.92,
        "completed": false
    }
''',
        'templates/pdfjs.mustache': '''
    Example context (json):
    {
        "type": "pdf",
        "cmid": 42,
        "trackingcmid": 42,
        "resourcetype": "Resource type: PDF",
        "pdfurl": "https://example.invalid/protected.php?id=42",
        "title": "Protected PDF",
        "initialpage": 3,
        "initialprogress": 30,
        "initialtimespent": 30,
        "completionpercent": 10,
        "completed": false,
        "visitedpages": "[1,2,3]",
        "disablecontextmenu": true,
        "enablewatermark": true,
        "watermark": "Example User"
    }
''',
        'templates/book.mustache': '''
    Example context (json):
    {
        "type": "pdf",
        "cmid": 42,
        "trackingcmid": 42,
        "resourcetype": "Resource type: PDF",
        "pdfurl": "https://example.invalid/protected.php?id=42",
        "title": "Protected book",
        "initialpage": 3,
        "initialprogress": 30,
        "initialtimespent": 30,
        "completionpercent": 10,
        "completed": false,
        "visitedpages": "[1,2,3]",
        "disablecontextmenu": true,
        "enablewatermark": true,
        "watermark": "Example User"
    }
''',
    }
    for relative, example in examples.items():
        path = ROOT / relative
        text = path.read_text(encoding='utf-8')
        if 'Example context (json):' not in text:
            text = text.replace('\n}}', example + '\n}}', 1)
        path.write_text(text, encoding='utf-8')

    for relative, container_class in [
        ('templates/video.mustache', 'mod-videoplayer-container mod-videoplayer-native'),
        ('templates/pdfjs.mustache', 'mod-videoplayer-container mod-videoplayer-pdfjs'),
        ('templates/book.mustache', 'mod-videoplayer-container mod-videoplayer-book'),
    ]:
        path = ROOT / relative
        text = path.read_text(encoding='utf-8')
        text = text.replace(
            f'<div class="{container_class}" data-resource-type="{{{{type}}}}" data-cmid="{{{{trackingcmid}}}}"',
            f'<div class="{container_class}" data-resource-type="{{{{type}}}}" data-cmid="{{{{cmid}}}}"',
            1,
        )
        text = text.replace('data-cmid="{{cmid}}"', 'data-cmid="{{trackingcmid}}"', 1 if relative != 'templates/video.mustache' else 0)
        path.write_text(text, encoding='utf-8')

    path = ROOT / 'templates/video.mustache'
    text = path.read_text(encoding='utf-8')
    text = text.replace('            controlslist="nodownload"\n', '')
    text = text.replace('            webkit-playsinline\n', '')
    text = text.replace('            x-webkit-airplay="allow"\n', '')
    text = text.replace('            data-cmid="{{cmid}}"\n', '            data-cmid="{{trackingcmid}}"\n')
    path.write_text(text, encoding='utf-8')

    for relative in ['templates/pdfjs.mustache', 'templates/book.mustache']:
        path = ROOT / relative
        text = path.read_text(encoding='utf-8')
        first = text.find('data-cmid="{{cmid}}"')
        second = text.find('data-cmid="{{cmid}}"', first + 1)
        if second != -1:
            text = text[:second] + 'data-cmid="{{trackingcmid}}"' + text[second + len('data-cmid="{{cmid}}"'):]
        path.write_text(text, encoding='utf-8')


def fix_css() -> None:
    path = ROOT / 'styles.css'
    text = path.read_text(encoding='utf-8')
    replacements = {
        '#ffffff': '#fff',
        'overflow: hidden !important;': 'overflow: hidden;',
        'height: 100% !important;': 'height: 100%;',
        'max-width: min(520px, 100%);': 'width: 100%;\n    max-width: 520px;',
        'max-width: min(720px, 100%);': 'width: 100%;\n    max-width: 720px;',
        'min-height: 0 !important;': 'min-height: 0;',
        'display: none !important;': 'display: none;',
        'width: min(100%, 1080px);': 'width: 100%;\n    max-width: 1080px;',
        'font-size: clamp(1.5rem, 4vw, 4rem);': 'font-size: 2.5rem;',
        'width: min(22rem, calc(100% - 2rem));': 'width: calc(100% - 2rem);\n    max-width: 22rem;',
        'position: fixed !important;': 'position: fixed;',
        'inset: 0 !important;': 'inset: 0;',
        'z-index: 1055 !important;': 'z-index: 1055;',
        'max-width: none !important;': 'max-width: none;',
        'width: 100vw !important;': 'width: 100vw;',
        'height: 100vh !important;': 'height: 100vh;',
        'border-radius: 0 !important;': 'border-radius: 0;',
    }
    for old, new in replacements.items():
        text = text.replace(old, new)
    path.write_text(text, encoding='utf-8')


def fix_javascript() -> None:
    path = ROOT / 'amd/src/bookviewer.js'
    text = path.read_text(encoding='utf-8')
    text = text.replace(
        '            return num <= 1 ? 1 : (num % 2 === 0 ? num : num - 1);',
        '            if (num <= 1) {\n                return 1;\n            }\n            return num % 2 === 0 ? num : num - 1;',
    )
    old = """            const promise = pdfDocument.getPage(pageIndex).then(function(page) {
                const base = page.getViewport({scale: 1});
                const targetWidth = getPageWidth();
                const scale = Math.min(Math.max(targetWidth / base.width, 0.5), 2.2);
                const viewport = page.getViewport({scale: scale});
                const outputScale = Math.min(window.devicePixelRatio || 1, 2);
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d', {alpha: false});

                canvas.width = Math.max(1, Math.floor(viewport.width * outputScale));
                canvas.height = Math.max(1, Math.floor(viewport.height * outputScale));
                canvas.style.width = Math.floor(viewport.width) + 'px';
                canvas.style.height = Math.floor(viewport.height) + 'px';
                canvas.setAttribute('draggable', 'false');

                return page.render({
                    canvasContext: context,
                    viewport: viewport,
                    transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null
                }).promise.then(function() {
                    pageCache.set(cacheKey, canvas);
                    pruneCache();
                    return canvas;
                });
            }).finally(function() {
                renderPromises.delete(cacheKey);
            });"""
    new = """            let renderedCanvas = null;
            const promise = pdfDocument.getPage(pageIndex).then(function(page) {
                const base = page.getViewport({scale: 1});
                const targetWidth = getPageWidth();
                const scale = Math.min(Math.max(targetWidth / base.width, 0.5), 2.2);
                const viewport = page.getViewport({scale: scale});
                const outputScale = Math.min(window.devicePixelRatio || 1, 2);
                renderedCanvas = document.createElement('canvas');
                const context = renderedCanvas.getContext('2d', {alpha: false});

                renderedCanvas.width = Math.max(1, Math.floor(viewport.width * outputScale));
                renderedCanvas.height = Math.max(1, Math.floor(viewport.height * outputScale));
                renderedCanvas.style.width = Math.floor(viewport.width) + 'px';
                renderedCanvas.style.height = Math.floor(viewport.height) + 'px';
                renderedCanvas.setAttribute('draggable', 'false');

                return page.render({
                    canvasContext: context,
                    viewport: viewport,
                    transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null
                }).promise;
            }).then(function() {
                pageCache.set(cacheKey, renderedCanvas);
                pruneCache();
                return renderedCanvas;
            }).finally(function() {
                renderPromises.delete(cacheKey);
            });"""
    text = text.replace(old, new)
    text = text.replace('                finishRender(visiblePages);\n            }).catch(function(err) {',
                        '                finishRender(visiblePages);\n                return null;\n            }).catch(function(err) {')
    text = text.replace('            updateStatus();\n            renderSpread();\n        }).catch(function(err) {',
                        '            updateStatus();\n            renderSpread();\n            return null;\n        }).catch(function(err) {')
    text = text.replace('                initViewer(root, pdfjsLib);\n            });\n        }).catch(function(err) {',
                        '                initViewer(root, pdfjsLib);\n            });\n            return null;\n        }).catch(function(err) {')
    path.write_text(text, encoding='utf-8')

    path = ROOT / 'amd/src/pdfviewer.js'
    text = path.read_text(encoding='utf-8')
    text = text.replace('                    renderPage(queued);\n                }\n            }).catch(function(error) {',
                        '                    renderPage(queued);\n                }\n                return null;\n            }).catch(function(error) {')
    text = text.replace("                } else if (searchStatus) {\n                    searchStatus.textContent = root.getAttribute('data-search-not-found') || 'No results found';\n                }\n            }).catch(Notification.exception).finally(function() {",
                        "                } else if (searchStatus) {\n                    searchStatus.textContent = root.getAttribute('data-search-not-found') || 'No results found';\n                }\n                return null;\n            }).catch(Notification.exception).finally(function() {")
    text = text.replace('            updateButtons();\n            renderPage(pageNumber);\n        }).catch(function(error) {',
                        '            updateButtons();\n            renderPage(pageNumber);\n            return null;\n        }).catch(function(error) {')
    text = text.replace('                initViewer(root, pdfjsLib);\n            });\n        }).catch(function(error) {',
                        '                initViewer(root, pdfjsLib);\n            });\n            return null;\n        }).catch(function(error) {')
    path.write_text(text, encoding='utf-8')

    path = ROOT / 'amd/src/plyr.js'
    text = path.read_text(encoding='utf-8').replace(
        '                markOrientation(node);\n            });\n        }).catch(function(error) {',
        '                markOrientation(node);\n            });\n            return null;\n        }).catch(function(error) {',
    )
    path.write_text(text, encoding='utf-8')

    for name in ['bookviewer', 'pdfviewer', 'plyr']:
        source = (ROOT / f'amd/src/{name}.js').read_text(encoding='utf-8')
        (ROOT / f'amd/build/{name}.min.js').write_text(source, encoding='utf-8')


def enforce_tracking_setting() -> None:
    path = ROOT / 'classes/external/save_progress.php'
    text = path.read_text(encoding='utf-8')
    needle = """        if (isguestuser() || empty($USER->id)) {
            throw new \\moodle_exception('guestsarenotallowed', 'error');
        }

        return (new progress_service())->save_progress($cm, $course, $videoplayer, $context, (int)$USER->id, $params);"""
    replacement = """        if (isguestuser() || empty($USER->id)) {
            throw new \\moodle_exception('guestsarenotallowed', 'error');
        }

        if ((string)get_config('mod_videoplayer', 'enabletracking') === '0') {
            throw new \\moodle_exception('trackingdisabled', 'mod_videoplayer');
        }

        return (new progress_service())->save_progress($cm, $course, $videoplayer, $context, (int)$USER->id, $params);"""
    if needle in text:
        text = text.replace(needle, replacement)
    path.write_text(text, encoding='utf-8')


def main() -> None:
    normalize_php_headers()
    normalize_languages()
    fix_php_style()
    rewrite_backup_tasks()
    fix_templates()
    fix_css()
    fix_javascript()
    enforce_tracking_setting()


if __name__ == '__main__':
    main()
