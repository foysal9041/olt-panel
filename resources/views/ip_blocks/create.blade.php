@extends('adminlte::page')

@section('title', 'Add IP Block')

@section('content_header')
<x-noc.header title="Add IP Block" subtitle="A range you own — subnets are allocated from its free space" :back="route('ip-pools.index')" />
@stop

@section('content')
@include('ip_blocks._form', ['action' => route('ip-blocks.store'), 'method' => 'POST'])
@stop
