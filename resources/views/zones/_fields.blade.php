@php $v = fn ($f) => old($f, $zone->{$f} ?? null); @endphp

<div class="row">
    <div class="col-md-8 form-group">
        <label>Zone / POP Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ $v('name') }}"
               placeholder="e.g. Sunlit Khulna POP" required autofocus>
        @if ($zone->exists ?? false)
            <small class="form-text text-muted"><i class="fas fa-info-circle"></i>
                Renaming also renames it on every OLT, switch, IP subnet, NTTN link, VLAN, user, employee and customer.</small>
        @endif
    </div>
    <div class="col-md-4 form-group">
        <label>Code / ID</label>
        <input type="text" name="code" class="form-control" value="{{ $v('code') }}" placeholder="e.g. 1000">
    </div>
    <div class="col-md-6 form-group">
        <label>Username</label>
        <input type="text" name="username" class="form-control" value="{{ $v('username') }}" placeholder="e.g. strimran">
    </div>
    <div class="col-md-6 form-group">
        <label>Contact Person</label>
        <input type="text" name="contact_name" class="form-control" value="{{ $v('contact_name') }}" placeholder="optional">
    </div>
    <div class="col-md-6 form-group">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ $v('phone') }}" placeholder="01XXXXXXXXX, another">
        <small class="form-text text-muted">Several numbers: separate with a comma.</small>
    </div>
    <div class="col-md-6 form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="{{ $v('email') }}" placeholder="optional">
    </div>
    <div class="col-12 form-group">
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="own_vlans" name="own_vlans" value="1"
                   @checked(old('own_vlans', $zone->own_vlans ?? false))>
            <label class="custom-control-label" for="own_vlans">Own VLANs (POP with its own switch)</label>
        </div>
        <small class="form-text text-muted">
            VLANs here are only checked against this POP — e.g. 101-108 can be used at several POPs.
            Leave off for zones on the core network, where every VLAN must be unique.
        </small>
    </div>
    <div class="col-12 form-group mb-0">
        <label>Notes</label>
        <textarea name="notes" rows="2" class="form-control" placeholder="optional">{{ $v('notes') }}</textarea>
    </div>
</div>
