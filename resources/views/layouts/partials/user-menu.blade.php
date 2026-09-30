{{--
    Account menu body, shared by the topbar user menu and the sidebar profile card.
    Expects $user, $avatarSrc, $roleLabel.
--}}
<div class="panel-header user-menu-head">
    <x-ui.avatar :src="$avatarSrc" :name="$user->name" size="md" />
    <div class="min-w-0">
        <p class="panel-title text-truncate">{{ $user->name }}</p>
        @if ($user->email)<p class="panel-sub text-truncate">{{ $user->email }}</p>@endif
        <p class="panel-sub">{{ $roleLabel }}</p>
    </div>
</div>
<div class="user-menu-body">
    <x-ui.dropdown-item :href="route('profile.edit')" icon="person-circle" :active="request()->routeIs('profile.*')">My profile</x-ui.dropdown-item>
    <x-ui.dropdown-divider />
    <x-ui.dropdown-item :action="route('logout')" icon="box-arrow-right">Sign out</x-ui.dropdown-item>
</div>
