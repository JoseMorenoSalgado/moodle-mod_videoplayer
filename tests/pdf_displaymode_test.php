<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_videoplayer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PDF display-mode stability tests.
 *
 * @package    mod_videoplayer
 * @category   test
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class pdf_displaymode_test extends \advanced_testcase {
    /**
     * Legacy and unsupported modes must resolve to the stable PDF.js viewer.
     *
     * @param string|null $requestedmode Requested mode.
     */
    #[DataProvider('legacy_mode_provider')]
    public function test_legacy_modes_resolve_to_pdfjs(?string $requestedmode): void {
        require_once(__DIR__ . '/../locallib.php');

        $this->assertSame('pdfjs', videoplayer_get_safe_pdf_displaymode($requestedmode));
    }

    /**
     * Supply current, legacy and malformed display-mode values.
     *
     * @return array<string, array{0: string|null}>
     */
    public static function legacy_mode_provider(): array {
        return [
            'current PDF.js mode' => ['pdfjs'],
            'legacy standard mode' => ['standard'],
            'PageFlip ebook mode' => ['ebook'],
            'legacy book mode' => ['book'],
            'empty mode' => [''],
            'missing mode' => [null],
            'invalid mode' => ['../../pageflip'],
        ];
    }

    /**
     * The learner entry point must not load alternative PDF renderers.
     */
    public function test_view_uses_only_pdfjs_renderer(): void {
        $viewsource = file_get_contents(__DIR__ . '/../view.php');

        $this->assertIsString($viewsource);
        $this->assertStringContainsString("mod_videoplayer/pdfviewer", $viewsource);
        $this->assertStringNotContainsString("mod_videoplayer/ebookviewer", $viewsource);
        $this->assertStringNotContainsString("mod_videoplayer/bookviewer", $viewsource);
        $this->assertStringNotContainsString("thirdpartylibs/pageflip", $viewsource);
    }
}
