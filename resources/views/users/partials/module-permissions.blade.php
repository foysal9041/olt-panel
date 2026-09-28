@php
    $permissionState = old('permissions', $permissionState ?? []);
@endphp

<div class="form-group">
    <label>Module Access</label>
    <div class="card card-body bg-light">

        @foreach($modules as $key => $module)

            @php
                $state = $permissionState[$key] ?? [];
                $isFull = !empty($state['full']);
                $subSelected = $state['sub'] ?? [];
            @endphp

            <div class="mb-3 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">

                <div class="form-check">
                    <input type="checkbox"
                           name="permissions[{{ $key }}][full]"
                           value="1"
                           id="module_{{ $key }}_full"
                           class="form-check-input js-full-module"
                           data-module="{{ $key }}"
                           {{ $isFull ? 'checked' : '' }}>
                    <label class="form-check-label font-weight-bold" for="module_{{ $key }}_full">
                        <i class="{{ $module['icon'] }}"></i>
                        {{ $module['label'] }}
                        <small class="text-muted font-weight-normal">(full access — every section)</small>
                    </label>
                </div>

                @if(!empty($module['submodules']))
                    <div class="row ml-4 mt-2 js-submodules-{{ $key }}">
                        @foreach($module['submodules'] as $subKey => $subLabel)
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox"
                                           name="permissions[{{ $key }}][sub][]"
                                           value="{{ $subKey }}"
                                           id="module_{{ $key }}_sub_{{ $subKey }}"
                                           class="form-check-input"
                                           {{ in_array($subKey, $subSelected) ? 'checked' : '' }}
                                           {{ $isFull ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="module_{{ $key }}_sub_{{ $subKey }}">
                                        {{ $subLabel }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>

        @endforeach

        <small class="text-muted mt-2 mb-0">
            Check the module's own box for full access, or leave it unchecked and pick individual sections instead.
            Admins always have full access regardless of these checkboxes. Dashboard and Profile are always available to everyone.
        </small>

    </div>
</div>

<script>
document.querySelectorAll('.js-full-module').forEach(function (box) {
    box.addEventListener('change', function () {
        var subBoxes = document.querySelectorAll('.js-submodules-' + box.dataset.module + ' input[type="checkbox"]');
        subBoxes.forEach(function (sub) {
            sub.disabled = box.checked;
        });
    });
});
</script>
