<?php

namespace Tests\Unit;

use App\Services\PdfRenderer;
use PHPUnit\Framework\TestCase;

/** Left-to-right runs inside Arabic lines get LRE…PDF marks; nothing else changes. */
class PdfBidiTest extends TestCase
{
    private const LRE = "\u{202A}";

    private const PDF = "\u{202C}";

    public function test_runs_inside_arabic_lines_are_protected(): void
    {
        $this->assertSame(
            'الآيبان: '.self::LRE.'SA03 8000 0000 6080 1016 7519'.self::PDF,
            PdfRenderer::bidi('الآيبان: SA03 8000 0000 6080 1016 7519'),
        );
        $this->assertSame(
            'للتواصل: '.self::LRE.'+966 50 123 4567'.self::PDF.' أو '.self::LRE.'info@alitqan.sa'.self::PDF,
            PdfRenderer::bidi('للتواصل: +966 50 123 4567 أو info@alitqan.sa'),
        );
    }

    public function test_plain_words_and_numbers_and_ltr_only_lines_are_left_alone(): void
    {
        $this->assertSame('حي العليا 1234', PdfRenderer::bidi('حي العليا 1234'));
        $this->assertSame('SWIFT: RJHISARI', PdfRenderer::bidi('SWIFT: RJHISARI'));
        $this->assertSame("مصرف الراجحي\nSA03 8000", PdfRenderer::bidi("مصرف الراجحي\r\nSA03 8000"));
        $this->assertSame('', PdfRenderer::bidi(null));
    }
}
