@extends('layouts.accounts-print')

{{-- An HR letter on the company pad (one A4 page). ?plain=1 = pre-printed pad paper. --}}

@php
    $plain = request()->boolean('plain');
    $pdfName = trim(preg_replace('/[^\w\s.()-]+/u', ' ', $letter->typeLabel() . ' ' . $letter->name() . ' ' . str_replace('/', '-', $letter->ref_no))) . '.pdf';
@endphp

@section('title', $letter->typeLabel() . ' — ' . $letter->name())
@section('back', route('attendance.letters.index'))
@section('bare', '1')

@section('actions')
    <a href="{{ route('attendance.letters.edit', $letter) }}">✎ Edit</a>
    <button type="button" id="pdf-btn" style="background:#15803d; border-color:#15803d">⬇ Download PDF</button>
    <a href="{{ request()->fullUrlWithQuery(['plain' => $plain ? null : 1, 'download' => null]) }}">{{ $plain ? 'Show pad design' : 'Pre-printed pad (blank)' }}</a>
@endsection

@section('styles')
    body { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; }
    .sheet { max-width: 210mm; padding: 0; background: transparent; box-shadow: none; }
    .ltr { flex: 1; display: flex; flex-direction: column; padding: 0 1mm; font-size: 11pt; line-height: 1.45; color: #000; }
    .ltr-top { display: flex; justify-content: space-between; margin-bottom: 6mm; }
    .ltr-to { margin-bottom: 5mm; }
    .ltr-to b { font-size: 11.5pt; }
    .ltr-subject { margin: 0 0 4mm; font-weight: 700; }
    .ltr-subject span { text-decoration: underline; text-underline-offset: 2px; }
    .ltr p { margin: 0 0 2.6mm; text-align: justify; }
    .ltr ol { margin: 0 0 2.6mm; padding-left: 6mm; }
    .ltr ol li { margin-bottom: 1.6mm; text-align: justify; padding-left: 1mm; }
    .ltr-sign { margin-top: 5mm; }
    .ltr-sign img { display: block; height: 13mm; margin: 1mm 0 -1mm; }
    .ltr-sign .ln { width: 55mm; border-top: 1px solid #000; margin-top: 12mm; padding-top: 1mm; }
    .ltr-sign img + .ln { margin-top: 0; }
    .ltr-accept { margin-top: auto; padding-top: 3mm; border-top: 1px dashed #64748b; font-size: 10pt; }
    .ltr-accept .row { display: flex; justify-content: space-between; gap: 6mm; margin-top: 9mm; }
    .ltr-accept .row div { flex: 1; border-top: 1px solid #000; padding-top: 1mm; text-align: center; }
    .ltr.compact { font-size: 10.5pt; line-height: 1.38; }
    .ltr.compact ol li { margin-bottom: 1.1mm; }
@endsection

@section('content')
    <x-print.pad :plain="$plain">
        @include('components.print.letters.' . $letter->type, ['letter' => $letter, 'd' => $d, 'signature' => $signature])
    </x-print.pad>
@endsection

@section('scripts')
    @include('accounts.partials.pdf-download')
@endsection
