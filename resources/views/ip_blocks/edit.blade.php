@extends('adminlte::page')

@section('title', 'Edit IP Block')

@section('content_header')
<x-noc.header title="Edit IP Block" subtitle="{{ $block->cidr }} · {{ $block->name }}" :back="route('ip-blocks.show', $block)" />
@stop

@section('content')
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@include('ip_blocks._form', ['action' => route('ip-blocks.update', $block), 'method' => 'PUT'])
@stop
