@extends('adminlte::page')

@section('title', 'Edit Support Contact')

@section('content_header')
<h1>Edit Support Contact</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">{{ $supportContact->vendor_name }}</h3>
    </div>

    <form method="POST" action="{{ route('support-contacts.update', $supportContact->id) }}">
        @csrf
        @method('PUT')

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
                <input type="text" name="category" class="form-control" value="{{ old('category', $supportContact->category) }}"
                       list="category-list" required autofocus>
                <datalist id="category-list">
                    @foreach($categories as $category)
                        <option value="{{ $category }}">
                    @endforeach
                </datalist>
            </div>

            <div class="form-group">
                <label>Vendor Name</label>
                <input type="text" name="vendor_name" class="form-control" value="{{ old('vendor_name', $supportContact->vendor_name) }}" required>
            </div>

            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supportContact->contact_person) }}">
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $supportContact->phone) }}" required>
                <small class="text-muted">Multiple numbers can be separated with a comma. Include the country code (e.g. 8801xxxxxxxxx) so the WhatsApp link works.</small>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $supportContact->email) }}">
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $supportContact->remarks) }}</textarea>
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('support-contacts.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
