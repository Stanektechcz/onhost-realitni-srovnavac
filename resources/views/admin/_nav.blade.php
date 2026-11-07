<nav class="mb-6 flex flex-wrap gap-2 text-sm">
    @foreach (['admin.dashboard' => 'Přehled', 'admin.moderation' => 'Moderace', 'admin.reports' => 'Nahlášení', 'admin.stats' => 'Cenové statistiky', 'admin.agency-reviews' => 'Recenze RK', 'admin.agency-claims' => 'Převzetí RK', 'admin.users.index' => 'Uživatelé', 'admin.listings.index' => 'Inzeráty', 'admin.billing.payments' => 'Platby a členství', 'admin.contact.index' => 'Kontaktní zprávy', 'admin.settings' => 'Nastavení', 'admin.audit' => 'Audit log'] as $r => $label)
        @php($active = request()->routeIs($r) || (str_ends_with($r, '.index') && request()->routeIs(substr($r, 0, -5).'*')) || ($r === 'admin.billing.payments' && request()->routeIs('admin.billing.*', 'admin.plans.*')))
        <a href="{{ route($r) }}" @class(['rounded-lg px-3 py-2', 'bg-primary text-primary-foreground' => $active, 'bg-plum-50 text-plum' => ! $active])>{{ $label }}</a>
    @endforeach
</nav>
