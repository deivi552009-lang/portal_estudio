@props([
    'type' => 'button',
    'action' => null,
    'title' => null,
    'color' => 'slate',
])

@php
    $colores = [
        'edit' => 'border-indigo-200 bg-indigo-50 text-indigo-600 hover:bg-indigo-100',
        'delete' => 'border-red-200 bg-red-50 text-red-500 hover:bg-red-100',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100',
        'slate' => 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100',
    ];

    $claseColor = $colores[$color] ?? $colores['slate'];
@endphp

<button
    type="{{ $type }}"
    @if ($action)
        wire:click="{{ $action }}"
    @endif
    @if ($title)
        title="{{ $title }}"
    @endif
    {{ $attributes->merge([
        'class' => "flex h-9 w-9 items-center justify-center rounded-lg border transition {$claseColor}"
    ]) }}
>
    {{ $slot }}
</button>
