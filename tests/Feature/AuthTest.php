<?php

use App\Models\User;

it('registers a user but never as admin', function () {
    $this->post('/registrace', ['name' => 'Test', 'email' => 't@example.test', 'role' => 'admin', 'password' => 'heslo-1234567', 'password_confirmation' => 'heslo-1234567', 'terms' => 1])
        ->assertSessionHasErrors('role');
    $this->post('/registrace', ['name' => 'Test', 'email' => 't@example.test', 'role' => 'owner', 'password' => 'heslo-1234567', 'password_confirmation' => 'heslo-1234567', 'terms' => 1])
        ->assertRedirect(route('dashboard'));
    expect(User::where('email', 't@example.test')->value('role'))->toBe('owner');
    $this->assertAuthenticated();
});

it('logs in and out', function () {
    $u = User::factory()->create();
    $this->post('/prihlaseni', ['email' => $u->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    $this->post('/odhlasit')->assertRedirect('/');
    $this->assertGuest();
});

it('rejects wrong password and banned users', function () {
    $u = User::factory()->create(['is_banned' => true]);
    $this->post('/prihlaseni', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rate limits login attempts', function () {
    $u = User::factory()->create();
    foreach (range(1, 6) as $_) {
        $this->post('/prihlaseni', ['email' => $u->email, 'password' => 'bad']);
    }
    $this->post('/prihlaseni', ['email' => $u->email, 'password' => 'bad'])->assertStatus(429);
});

it('requires password confirmation before deleting the account', function () {
    $u = User::factory()->create();
    $this->actingAs($u)->delete(route('account.destroy'))->assertRedirect(route('password.confirm'));
    $this->post(route('password.confirm'), ['password' => 'password']);
    $this->delete(route('account.destroy'))->assertRedirect('/');
    expect(User::find($u->id))->toBeNull();
});

it('protects account pages', function () {
    $this->get('/moje')->assertRedirect(route('login'));
});
