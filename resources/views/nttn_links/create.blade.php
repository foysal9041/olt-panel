@extends('adminlte::page')

@section('title', 'Add NTTN Link')

@section('content_header')
<x-noc.header title="Add NTTN Link" subtitle="Register a transmission link and start monitoring it" :back="route('nttn-links.index')" />
@stop

@section('content')

@include('nttn_links._form', [
    'action' => route('nttn-links.store'),
    'method' => 'POST',
    'submitLabel' => 'Save Link',
])

@stop
