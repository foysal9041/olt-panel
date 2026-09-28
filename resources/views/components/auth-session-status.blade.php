@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-2.5']) }}>
        {{ $status }}
    </div>
@endif
