<?php

it('accepts feed requests without a CSRF token', function () {
    $mw = new class(app(), app('encrypter')) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
        public function excluded(): array { return $this->getExcludedPaths(); }
    };
    expect($mw->excluded())->toContain('api/feed/*');
});
