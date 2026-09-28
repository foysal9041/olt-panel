@extends('adminlte::page')

@section('title', 'Technical Support Contacts')

@section('content_header')
<x-noc.header title="Technical Support" icon="fas fa-headset" subtitle="Vendor and upstream support contacts" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card card-outline card-primary">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title">Vendor Support Numbers</h3>

        <div class="d-flex">

            <input type="text"
                   id="support-contacts-search"
                   class="form-control form-control-sm mr-2"
                   placeholder="Search category, vendor, phone...">

            <a href="{{ route('support-contacts.create') }}" class="btn btn-primary btn-sm text-nowrap">
                <i class="fas fa-plus"></i> Add Support Contact
            </a>

        </div>
    </div>

    <div class="card-body p-0">

        <table id="support-contacts-table" class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Category</th>
                    <th>Vendor</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Remarks</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($supportContacts as $contact)

                    <tr>
                        <td><span class="badge badge-info">{{ $contact->category }}</span></td>
                        <td class="font-weight-bold">{{ $contact->vendor_name }}</td>
                        <td>{{ $contact->contact_person ?? '—' }}</td>
                        <td>
                            @foreach(preg_split('/[,\/]+/', $contact->phone) as $number)
                                @php
                                    $number = trim($number);
                                    $digits = preg_replace('/\D+/', '', $number);
                                @endphp
                                <span class="text-nowrap">
                                    <a href="tel:{{ $number }}">{{ $number }}</a>
                                    @if($digits !== '')
                                        <a href="https://wa.me/{{ $digits }}" target="_blank" rel="noopener"
                                           title="Chat on WhatsApp" class="text-success ml-1">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    @endif
                                </span>@if(!$loop->last), @endif
                            @endforeach
                        </td>
                        <td>{{ $contact->email ?? '—' }}</td>
                        <td>{{ $contact->remarks ?? '—' }}</td>
                        <td>
                            <a href="{{ route('support-contacts.edit', $contact->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <form action="{{ route('support-contacts.destroy', $contact->id) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete support contact for {{ $contact->vendor_name }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="7" class="text-center">No support contacts recorded yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

<style>
/* The header already has a custom search box — hide DataTables' own
   default one so there aren't two search inputs doing the same thing. */
#support-contacts-table_filter {
    display: none;
}
</style>

<script>
$(function () {
    var table = $('#support-contacts-table').DataTable();

    $('#support-contacts-search').on('keyup', function () {
        table.search(this.value).draw();
    });
});
</script>

@stop
