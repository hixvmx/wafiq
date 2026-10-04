<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Invitation;
use App\Notifications\InvitationNotification;
use App\Notifications\LoginLinkNotification;
use App\Notifications\MentionedInComment;
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

    public function test_mention_email(): void
    {
        $author = $this->member(Role::Sales, ['name' => 'سارة']);
        $reader = $this->member(Role::Accountant, ['name' => 'خالد']);
        $document = Document::factory()->create(['number' => 'QT-2026-0042']);
        $comment = Comment::create(['document_id' => $document->id, 'user_id' => $author->id, 'body' => 'راجع الأسعار من فضلك']);

        $html = (string) (new MentionedInComment($comment))->toMail($reader)->render();

        $this->assertStringContainsString('أشار إليك سارة في تعليق على QT-2026-0042', $html);
        $this->assertStringContainsString('راجع الأسعار من فضلك', $html);
        $this->assertStringContainsString("#comment-{$comment->id}", $html);
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
