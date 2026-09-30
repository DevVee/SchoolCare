@extends('layouts.app')

@section('title', 'My profile')

@php
    $role = $user->roles->first();
    $photo = $user->avatar ? $user->avatarUrl().'?v='.$user->updated_at->timestamp : null;
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="My profile" description="Your name, email, photo and password."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'My profile' => null]" />

    @if ($user->must_change_password)
        <x-ui.alert variant="warning" title="Password change required">
            An administrator reset your password. Choose a new password below to keep using {{ settings('app_name') }}.
        </x-ui.alert>
    @endif

    @error('avatar')
        <x-ui.alert variant="danger" title="The photo was not uploaded">{{ $message }}</x-ui.alert>
    @enderror

    <div class="row g-3">
        {{-- Left: who you are --}}
        <div class="col-lg-4">
            <x-ui.card>
                <div class="profile-summary">
                    <x-ui.avatar :name="$user->name" :src="$photo" size="xl" />
                    <div class="min-w-0">
                        <p class="fw-semibold text-ink mb-1 text-truncate">{{ $user->name }}</p>
                        @if ($role)
                            <x-ui.badge color="neutral" :dot="false">{{ \Illuminate\Support\Str::headline($role->name) }}</x-ui.badge>
                        @endif
                    </div>
                </div>
                <x-ui.description-list layout="stacked" class="mt-3">
                    <x-ui.description-item label="Email">{{ $user->email }}</x-ui.description-item>
                    <x-ui.description-item label="Member since">{{ $user->created_at->format('M j, Y') }}</x-ui.description-item>
                    <x-ui.description-item label="Last sign in" empty="Not recorded">@if ($user->last_login_at)<time datetime="{{ $user->last_login_at->toIso8601String() }}" title="{{ $user->last_login_at->format('M j, Y, g:i A') }}">{{ $user->last_login_at->diffForHumans() }}</time>@endif</x-ui.description-item>
                </x-ui.description-list>
                <x-slot:footer>
                    @if ($user->avatar)
                        <form method="POST" action="{{ route('profile.avatar.remove') }}" data-confirm="Your initials will be shown instead."
                              data-confirm-title="Remove your photo?" data-confirm-variant="danger" data-confirm-button="Remove photo">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="ghost" size="sm">Remove photo</x-ui.button>
                        </form>
                    @endif
                    <x-ui.button variant="secondary" size="sm" icon="camera" data-bs-toggle="modal" data-bs-target="#avatarModal">
                        {{ $user->avatar ? 'Change photo' : 'Add photo' }}
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </div>

        {{-- Right: forms --}}
        <div class="col-lg-8 vstack gap-3">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <x-ui.card title="Account" subtitle="Your name as staff see it, and the email you sign in with.">
                    <div class="row g-3">
                        <x-ui.input wrapper-class="col-12 col-md-6" name="name" label="Full name" :value="$user->name" required autocomplete="name" />
                        <x-ui.input wrapper-class="col-12 col-md-6" name="email" type="email" label="Email" :value="$user->email" required autocomplete="username" />
                    </div>
                    <x-slot:footer>
                        <x-ui.button type="submit" icon="check-lg">Save changes</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" id="passwordForm">
                @csrf
                @method('PUT')
                <x-ui.card title="Password" subtitle="Use at least 10 characters with upper and lowercase letters, a number and a symbol.">
                    <div class="row g-3">
                        <x-ui.input wrapper-class="col-12 col-md-6" name="current_password" type="password" label="Current password" bag="updatePassword" required autocomplete="current-password" />
                        <div class="w-100 d-none d-md-block"></div>
                        <div class="col-12 col-md-6">
                            <x-ui.input name="password" id="newPassword" type="password" label="New password" bag="updatePassword" required autocomplete="new-password" />
                            <div class="strength" aria-hidden="true"><span class="strength-fill" id="strengthBar"></span></div>
                            <p class="text-muted fs-xs mt-1 mb-0" id="strengthText" aria-live="polite"></p>
                        </div>
                        <x-ui.input wrapper-class="col-12 col-md-6" name="password_confirmation" type="password" label="Confirm new password" bag="updatePassword" required autocomplete="new-password" />
                    </div>
                    <x-slot:footer>
                        <x-ui.button type="submit" icon="lock">Change password</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>

            <x-ui.card title="Delete account" subtitle="Permanently removes your account. This cannot be undone.">
                <p class="text-ink-2 mb-0">
                    Accounts linked to clinic records cannot be deleted. Ask an administrator to deactivate your account instead.
                </p>
                <x-slot:footer>
                    <x-ui.button variant="danger" size="sm" icon="trash" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">Delete my account</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </div>
    </div>
</div>

{{-- Photo upload --}}
<x-ui.modal id="avatarModal" title="Profile photo" subtitle="JPG, PNG, WebP or GIF, up to 15 MB." :action="route('profile.avatar')" files sheet>
    {{-- Visually hidden but not display:none, so browsers include it when submitting. --}}
    <input type="file" name="avatar" id="avatarInput" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif">
    <button type="button" class="avatar-drop" id="avatarDropZone">
        <img id="avatarPreview" src="{{ $photo ?? '' }}" alt="" width="112" height="112" @if (! $photo) hidden @endif>
        <span class="avatar-drop-empty" id="avatarEmpty" @if ($photo) hidden @endif aria-hidden="true"><x-ui.icon name="person" /></span>
        <span class="avatar-drop-text"><x-ui.icon name="upload" /> Choose a photo, or drop it here</span>
    </button>
    @error('avatar')<p class="text-danger fs-sm mt-2 mb-0">{{ $message }}</p>@enderror
    <x-slot:footer>
        <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="submit" icon="upload" id="avatarSubmitBtn" disabled>Upload photo</x-ui.button>
    </x-slot:footer>
</x-ui.modal>

{{-- Delete account --}}
<x-ui.modal id="deleteAccountModal" title="Delete your account?" subtitle="This cannot be undone." :action="route('profile.destroy')" method="DELETE" size="sm">
    <x-ui.input name="password" id="deletePassword" type="password" label="Enter your password to confirm" bag="userDeletion" required autocomplete="current-password" />
    <x-slot:footer>
        <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="submit" variant="danger" icon="trash">Delete account</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Password strength hint.
    var pwd = document.getElementById('newPassword');
    var bar = document.getElementById('strengthBar');
    var txt = document.getElementById('strengthText');
    if (pwd && bar) {
        pwd.addEventListener('input', function () {
            var v = pwd.value, score = 0;
            if (v.length >= 10) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
            bar.style.width = (score * 25) + '%';
            bar.dataset.score = String(score);
            txt.textContent = v ? 'Strength: ' + labels[score || 1] : '';
        });
    }

    // Photo picker: click or drag and drop, with a preview.
    var drop = document.getElementById('avatarDropZone');
    var file = document.getElementById('avatarInput');
    var preview = document.getElementById('avatarPreview');
    var empty = document.getElementById('avatarEmpty');
    var submit = document.getElementById('avatarSubmitBtn');
    if (drop && file) {
        drop.addEventListener('click', function () { file.click(); });
        drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('is-over'); });
        drop.addEventListener('dragleave', function () { drop.classList.remove('is-over'); });
        drop.addEventListener('drop', function (e) {
            e.preventDefault();
            drop.classList.remove('is-over');
            if (e.dataTransfer.files[0]) apply(e.dataTransfer.files[0]);
        });
        file.addEventListener('change', function () { if (file.files[0]) apply(file.files[0]); });
    }
    function apply(f) {
        var dt = new DataTransfer();
        dt.items.add(f);
        file.files = dt.files;
        var reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.alt = 'Selected photo';
            preview.hidden = false;
            if (empty) empty.hidden = true;
            submit.disabled = false;
        };
        reader.readAsDataURL(f);
    }

    // Reopen the delete dialog when the password was wrong.
    @if ($errors->userDeletion->any())
        if (window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteAccountModal')).show();
    @endif
});
</script>
@endpush
