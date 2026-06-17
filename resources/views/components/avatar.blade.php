@props([
    'initials' => null,
    'name' => null,
    'tone' => 'primary',
    'baseClass' => 'ticket-show__avatar',
    'tonePrefix' => 'ticket-show__avatar--',
    'toneClass' => null,
    'as' => 'span',
])

@if ($as === 'div')
    <div {{ $attributes->merge(['class' => trim($baseClass . ' ' . ($toneClass !== null ? $toneClass : $tonePrefix . $tone))]) }}>
        {{ $initials ?? \App\Support\Initials::from($name) }}
    </div>
@else
    <span {{ $attributes->merge(['class' => trim($baseClass . ' ' . ($toneClass !== null ? $toneClass : $tonePrefix . $tone))]) }}>
        {{ $initials ?? \App\Support\Initials::from($name) }}
    </span>
@endif
