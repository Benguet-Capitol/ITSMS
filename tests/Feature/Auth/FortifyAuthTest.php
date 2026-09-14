<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;

test('a user can log in with correct credentials', function () {
    $user = User::factory()->create([
        'password' => 'Correct-Horse-1',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Correct-Horse-1',
    ]);

    $response->assertSuccessful();
    $this->assertAuthenticatedAs($user);
});

test('a user cannot log in with an incorrect password', function () {
    $user = User::factory()->create([
        'password' => 'Correct-Horse-1',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $this->assertGuest();
});

test('an inactive user cannot log in even with the correct password', function () {
    $user = User::factory()->create([
        'password' => 'Correct-Horse-1',
        'status' => 'inactive',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Correct-Horse-1',
    ]);

    $response->assertStatus(422);
    $this->assertGuest();
});

test('an authenticated user can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/logout')
        ->assertSuccessful();

    $this->assertGuest();
});

test('forgot password sends a reset link and reset password changes the password', function () {
    Notification::fake();

    $user = User::factory()->create([
        'password' => 'Old-Password-1',
    ]);

    $this->postJson('/api/forgot-password', ['email' => $user->email])
        ->assertSuccessful();

    Notification::assertSentTo($user, ResetPassword::class);

    // Generate a real broker token the same way the notification would have
    // carried, rather than parsing it out of the faked notification.
    $token = Password::createToken($user);

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'New-Password-1',
        'password_confirmation' => 'New-Password-1',
    ])->assertSuccessful();

    $user->refresh();
    expect(Hash::check('New-Password-1', $user->password))->toBeTrue();
    expect(Hash::check('Old-Password-1', $user->password))->toBeFalse();
});

test('an authenticated user can change their password', function () {
    $user = User::factory()->create([
        'password' => 'Current-Password-1',
    ]);

    $this->actingAs($user)
        ->putJson('/api/user/password', [
            'current_password' => 'Current-Password-1',
            'password' => 'Brand-New-Password-1',
            'password_confirmation' => 'Brand-New-Password-1',
        ])->assertSuccessful();

    $user->refresh();
    expect(Hash::check('Brand-New-Password-1', $user->password))->toBeTrue();
});

test('two-factor authentication can be enabled, confirmed, and challenged on next login', function () {
    $user = User::factory()->create([
        'password' => 'Two-Factor-Pass-1',
    ]);

    $this->actingAs($user);

    // Fortify's 2FA setup routes require a recent password confirmation
    // (Features::twoFactorAuthentication(['confirmPassword' => true])).
    $this->postJson('/api/user/confirm-password', [
        'password' => 'Two-Factor-Pass-1',
    ])->assertSuccessful();

    $this->postJson('/api/user/two-factor-authentication')->assertSuccessful();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();

    $google2fa = new Google2FA;
    $secret = decrypt($user->two_factor_secret);
    $validCode = $google2fa->getCurrentOtp($secret);

    $this->postJson('/api/user/confirmed-two-factor-authentication', [
        'code' => $validCode,
    ])->assertSuccessful();

    $user->refresh();
    expect($user->two_factor_confirmed_at)->not->toBeNull();

    // Logging out and back in should now stop at the 2FA challenge instead
    // of completing the session immediately.
    $this->postJson('/api/logout')->assertSuccessful();
    $this->assertGuest();

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Two-Factor-Pass-1',
    ]);
    $loginResponse->assertSuccessful();
    $this->assertGuest(); // not fully authenticated until the challenge is passed

    // Try a small window around "now" in case the two calls to
    // getCurrentOtp() straddled a 30s TOTP step boundary.
    $accepted = false;
    foreach ([0, -1, 1, -2, 2] as $stepOffset) {
        $timestamp = $google2fa->getTimestamp() + $stepOffset;
        $candidate = $google2fa->oathTotp($secret, $timestamp);
        $response = $this->postJson('/api/two-factor-challenge', [
            'code' => $candidate,
        ]);
        if ($response->status() < 300) {
            $accepted = true;
            break;
        }
    }
    expect($accepted)->toBeTrue('No code within a 5-step window around now was accepted.');

    $this->assertAuthenticatedAs($user);
});

test('email verification stays disabled per the requirements decision pending product owner sign-off', function () {
    // UserFactory defaults email_verified_at to now(); force the
    // unverified case this test actually cares about.
    $user = User::factory()->create(['email_verified_at' => null]);

    // Features::emailVerification() is commented out in config/fortify.php,
    // so unverified users are never blocked from authenticated routes.
    expect($user->email_verified_at)->toBeNull();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertSuccessful();
});
