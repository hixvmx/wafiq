<x-mail::message>
{{-- The team's message, as typed (plain text: line breaks kept, no HTML). --}}
{!! nl2br(e($body)) !!}

<x-mail::button :url="$url">
{{ $button }}
</x-mail::button>
</x-mail::message>
