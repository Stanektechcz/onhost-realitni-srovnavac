@if (app(\App\Auth\OnhostIdClient::class)->enabled())
    <div class="space-y-2">
        <a href="{{ route('oidc.redirect') }}" class="btn-outline w-full justify-center">Přihlásit přes {{ config('oidc.brand_name') }}</a>
        <p class="text-center text-xs text-muted-foreground">Účet přes {{ config('oidc.brand_name') }} slouží ke správě inzerátů a oblíbených; kontaktní údaje inzerátu vyplňujete samostatně.</p>
        @error('onhost')<p class="err">{{ $message }}</p>@enderror
    </div>
@endif
