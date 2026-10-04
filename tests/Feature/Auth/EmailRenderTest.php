<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Invitation;
use App\Notifications\InvitationNotification;
use App\Notifications\LoginLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Arabic RTL mail theme renders, with the company's name in the header. */
class EmailRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_link_email(): void
    {
        $user = $this->member(Role::Owner, ['name' => 'أحمد']);
        $this->company()->update(['name' => 'شركة الإتقان']);

        $html = (string) (new LoginLinkNotification('https://example.com/login/abc'))->toMail($user)->render();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('شركة الإتقان', $html);
        $this->assertStringContainsString('مرحباً أحمد', $html);
        $this->assertStringContainsString('https://example.com/login/abc', $html);
    }

    public function test_invitation_email(): void
    {
        $user = $this->member();
        $invitation = new Invitation(['email' => 'new@example.com', 'role' => 'sales']);

        $html = (string) (new InvitationNotification($invitation, 'https://example.com/invitations/abc', 'شركة الإتقان', 'أحمد'))
            ->toMail($user)
            ->render();

        $this->assertStringContainsString('دعاك أحمد للانضمام إلى فريق شركة الإتقان بدور «مبيعات»', $html);
    }
}
