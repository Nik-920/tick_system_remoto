@props([
    'message' => null,
    'title' => null,
    'note' => null,
    'baseClass' => 'ticket-show__empty',
    'titleClass' => null,
    'noteClass' => null,
    'messageClass' => null,
])

<div {{ $attributes->merge(['class' => $baseClass]) }}>
    {{ $icon ?? '' }}

    @if ($title !== null)
        <p @if ($titleClass) class="{{ $titleClass }}" @endif>{{ $title }}</p>
    @endif

    @if ($note !== null)
        <p @if ($noteClass) class="{{ $noteClass }}" @endif>{{ $note }}</p>
    @elseif ($message !== null)
        <p @if ($messageClass) class="{{ $messageClass }}" @endif>{{ $message }}</p>
    @endif

    {{ $slot }}
</div>
