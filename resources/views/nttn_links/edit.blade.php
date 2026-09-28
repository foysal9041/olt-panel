@extends('adminlte::page')

@section('title', 'Edit NTTN Link')

@section('content_header')
<x-noc.header title="Edit NTTN Link" subtitle="{{ $nttnLink->link_id }}{{ $nttnLink->provider ? ' · ' . $nttnLink->provider : '' }}" :back="route('nttn-links.show', $nttnLink)" />
@stop

@section('content')

@include('nttn_links._form', [
    'action' => route('nttn-links.update', $nttnLink),
    'method' => 'PUT',
    'submitLabel' => 'Save Changes',
])

@stop
