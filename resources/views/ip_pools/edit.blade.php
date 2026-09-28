@extends('adminlte::page')

@section('title', 'Edit Subnet')

@section('content_header')
<x-noc.header title="Edit Subnet" :back="$ipPool->ip_block_id ? route('ip-blocks.show', $ipPool->ip_block_id) : route('ip-pools.index')"
    subtitle="{{ $ipPool->subnet }}{{ $ipPool->device ? ' · ' . $ipPool->device : '' }}" />
@stop

@section('content')
@include('ip_pools._form', ['action' => route('ip-pools.update', $ipPool), 'method' => 'PUT'])
@stop
