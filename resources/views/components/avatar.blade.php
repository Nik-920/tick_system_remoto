@props([
    'src' => null,
    'initials' => null,
    'name' => null,
    'tone' => 'primary',
    'baseClass' => 'ticket-show__avatar',
    'tonePrefix' => 'ticket-show__avatar--',
    'toneClass' => null,
    'as' => 'span',
])

@php
    $resolvedSrc = is_string($src) && trim($src) !== '' ? $src : null;
    $resolvedClass = trim($baseClass . ' ' . ($toneClass !== null ? $toneClass : $tonePrefix . $tone));
@endphp

@if ($as === 'div')
    <div {{ $attributes->merge(['class' => $resolvedClass]) }}>
        @if ($resolvedSrc)
            <img src="{{ $resolvedSrc }}" alt="" class="avatar-img" aria-hidden="true">
        @else
            {{ $initials ?? \App\Support\Initials::from($name) }}
        @endif
    </div>
@else
    <span {{ $attributes->merge(['class' => $resolvedClass]) }}>
        @if ($resolvedSrc)
            <img src="{{ $resolvedSrc }}" alt="" class="avatar-img" aria-hidden="true">
        @else
            {{ $initials ?? \App\Support\Initials::from($name) }}
        @endif
    </span>
@endif
