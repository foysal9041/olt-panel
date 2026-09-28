<p class="text-muted">
    Once your account is deleted, all of its resources and data will be permanently deleted.
    Before deleting your account, please download any data or information that you wish to retain.
</p>

<button type="button" class="btn btn-danger" data-toggle="modal" data-target="#confirmUserDeletion">
    Delete Account
</button>

<div class="modal fade" id="confirmUserDeletion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="modal-header">
                    <h5 class="modal-title">Are you sure you want to delete your account?</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <p class="text-muted">
                        Once your account is deleted, all of its resources and data will be permanently deleted.
                        Please enter your password to confirm you would like to permanently delete your account.
                    </p>

                    <input id="password" name="password" type="password" class="form-control"
                           placeholder="Password">
                    @error('password', 'userDeletion')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </div>

            </form>

        </div>
    </div>
</div>

@if ($errors->userDeletion->isNotEmpty())
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            jQuery('#confirmUserDeletion').modal('show');
        });
    </script>
@endif
