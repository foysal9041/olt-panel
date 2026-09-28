@extends('adminlte::page')

@section('title', 'Allocate Subnet')

@section('content_header')
<x-noc.header title="Allocate Subnet" :back="$ipPool->ip_block_id ? route('ip-blocks.show', $ipPool->ip_block_id) : route('ip-pools.index')"
    subtitle="{{ $ipPool->subnet ? 'Free space ' . $ipPool->subnet . ' — give it a device and purpose' : 'Carve a subnet out of a block' }}" />
@stop

@section('content')
@include('ip_pools._form', ['action' => route('ip-pools.store'), 'method' => 'POST'])
@stop
