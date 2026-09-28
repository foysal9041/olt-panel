@extends('adminlte::page')

@section('title', 'Add Support Contact')

@section('content_header')
<h1>Add Support Contact</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">Support Contact Details</h3>
    </div>

    <form method="POST" action="{{ route('support-contacts.store') }}">
        @csrf

        <div class="card-body">

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" class="form-control" value="{{ old('category') }}"
                       list="category-list" placeholder="e.g. OLT Vendor, Router" required autofocus>
                <datalist id="category-list">
                    @foreach($categories as $category)
                        <option value="{{ $category }}">
                    @endforeach
                </datalist>
                <small class="text-muted">Type a new category or pick an existing one — it's created automatically.</small>
            </div>

            <div class="form-group">
                <label>Vendor Name</label>
                <input type="text" name="vendor_name" class="form-control" value="{{ old('vendor_name') }}"
                       placeholder="e.g. CDATA, Airnet, Vsol, Mikrotik, Cisco" required>
            </div>

            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}">
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}"
                       placeholder="e.g. 01700000000, 01800000000" required>
                <small class="text-muted">Multiple numbers can be separated with a comma. Include the country code (e.g. 8801xxxxxxxxx) so the WhatsApp link works.</small>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}">
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('support-contacts.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
