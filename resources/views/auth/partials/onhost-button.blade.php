@if (app(\App\Auth\OnhostIdClient::class)->enabled())
    <div class="space-y-2">
        <a href="{{ route('onhost.redirect') }}" class="btn-outline w-full justify-center">Přihlásit přes ONhost ID</a>
        <p class="text-center text-xs text-muted-foreground">Účet přes ONhost ID slouží ke správě inzerátů a oblíbených; kontaktní údaje inzerátu vyplňujete samostatně.</p>
        @error('onhost')<p class="err">{{ $message }}</p>@enderror
    </div>
@endif
