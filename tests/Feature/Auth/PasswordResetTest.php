<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200)
            ->assertSee(route('login'), false)
            ->assertSee('Volver a iniciar sesión');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_password_email_is_in_spanish_and_embeds_the_official_logo(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $html = (string) $mail->render();
            $resetUrl = $mail->viewData['resetUrl'];

            $this->assertSame('Restablecer contraseña', $mail->subject);
            $this->assertSame(60, $mail->viewData['expirationMinutes']);
            $this->assertStringContainsString('/reset-password/'.$notification->token, $resetUrl);
            $this->assertStringContainsString('email='.urlencode($user->email), $resetUrl);
            $this->assertStringContainsString('Recibimos una solicitud para restablecer la contraseña de tu cuenta.', $html);
            $this->assertStringContainsString('expirará en 60 minutos', $html);
            $this->assertStringContainsString('Restablecer contraseña', $html);
            $this->assertMatchesRegularExpression('/src="(?:cid:|data:image\/png;base64,)/', $html);
            $this->assertStringNotContainsString('src="http://localhost', $html);
            $this->assertStringContainsString('alt="QuiQue Micromarket"', $html);
            $this->assertStringNotContainsString('Reset Password', $html);

            return true;
        });
    }

    public function test_reset_password_email_is_delivered_with_the_logo_inline(): void
    {
        config(['mail.default' => 'array']);
        app('mail.manager')->forgetMailers();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());

        $email = $transport->messages()->first()->getOriginalMessage();
        $rawMessage = $email->toString();

        $this->assertSame('Restablecer contraseña', $email->getSubject());
        $this->assertSame($user->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('Content-Disposition: inline', $rawMessage);
        $this->assertStringContainsString('quique-logo.png', $rawMessage);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_an_expired_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-08-19 10:00:00'));

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasErrors('email');

            $this->assertTrue(Hash::check('password', $user->fresh()->password));

            return true;
        });

        $this->travelBack();
    }
}
