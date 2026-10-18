<nav class="mb-6 flex flex-wrap gap-2 text-sm">
    @foreach (['admin.dashboard' => 'Přehled', 'admin.moderation' => 'Moderace', 'admin.reports' => 'Nahlášení', 'admin.stats' => 'Cenové statistiky'] as $r => $label)
        <a href="{{ route($r) }}" @class(['rounded-lg px-3 py-2', 'bg-plum text-white' => request()->routeIs($r), 'bg-plum-50 text-plum' => ! request()->routeIs($r)])>{{ $label }}</a>
    @endforeach
</nav>
