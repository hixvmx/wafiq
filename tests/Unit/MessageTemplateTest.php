<?php

namespace Tests\Unit;

use App\Support\MessageTemplate;
use PHPUnit\Framework\TestCase;

class MessageTemplateTest extends TestCase
{
    public function test_it_fills_variables_and_keeps_unknown_ones_visible(): void
    {
        $text = MessageTemplate::render('مرحباً {client_name}، العرض {number} {typo}', ['client_name' => 'أحمد', 'number' => 'QT-2026-0042']);

        $this->assertSame('مرحباً أحمد، العرض QT-2026-0042 {typo}', $text);
    }
}
