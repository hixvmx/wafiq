<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Comment;
use App\Models\Document;
use App\Models\User;
use App\Notifications\MentionedInComment;
use App\Services\CurrentCompany;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Internal comments on a document, with @mentions. */
class CommentController extends Controller
{
    public function store(Request $request, Document $document, CurrentCompany $current): RedirectResponse
    {
        abort_if($document->type !== $request->route('type'), 404);
        $this->authorize('view', $document);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'mentions' => ['array', 'max:20'],
            'mentions.*' => ['integer'],
        ]);
        $author = $request->user();
        $body = trim($data['body']);

        // Someone counts as mentioned when they were picked from the list, their "@Name" is still in
        // the text, they are on the team, and they may open this document. The author is never notified.
        $mentioned = $current->get()->users()
            ->whereIn('users.id', $data['mentions'] ?? [])
            ->whereKeyNot($author->id)
            ->get()
            ->filter(fn (User $user) => str_contains($body, '@'.$user->name) && $user->can('view', $document))
            ->values();

        $comment = Comment::create([
            'document_id' => $document->id,
            'user_id' => $author->id,
            'body' => $body,
            'mentions' => $mentioned->pluck('id')->all() ?: null,
        ]);

        Notifier::send($mentioned, new MentionedInComment($comment));

        return back();
    }

    /** The author, an owner or an admin can delete a comment. */
    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($comment->user_id === $user->id || in_array($user->currentRole(), [Role::Owner, Role::Admin], true), 403);

        $comment->delete();

        return back();
    }
}
