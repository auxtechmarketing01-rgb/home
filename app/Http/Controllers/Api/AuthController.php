<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\RegisterUserAction;
use App\Actions\Auth\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sanctum SPA cookie auth (FR-AUTH-02). How the session is established is
 * confined to establishSession()/terminateSession() so a future native client
 * can be given token auth from the same controller without touching the rest
 * of this logic (02 §1).
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $registerUser): JsonResponse
    {
        $user = $registerUser($request->validated());

        $this->establishSession($request, $user);

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('web')->validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->firstOrFail();

        /**
         * FR-ADM-01. Checked here as well as in the `active` middleware:
         * without it, a disabled account gets a valid session and only
         * discovers it is locked out on the *next* request. Correct
         * credentials for a disabled account are not a login.
         */
        if ($user->isDisabled()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been disabled.',
            ]);
        }

        $this->establishSession($request, $user, (bool) $request->boolean('remember'));

        return UserResource::make($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->terminateSession($request);

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    /**
     * FR-AUTH-04.
     */
    public function updateProfile(UpdateProfileRequest $request, UpdateProfileAction $updateProfile): UserResource
    {
        $user = $updateProfile(
            $request->user(),
            $request->safe()->except('avatar'),
            $request->file('avatar'),
        );

        return UserResource::make($user);
    }

    /**
     * FR-AUTH-03. The response is deliberately identical whether or not the
     * address exists, so this endpoint cannot be used to enumerate accounts.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __('passwords.sent'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => __($status)]);
    }

    /**
     * FR-AUTH-01. The link in the verification mail points at the API, not at
     * the SPA, so the signature Laravel put on it is verified by the same app
     * that issued it; the member is then handed off to the SPA.
     *
     * Authentication is deliberately not required: the member is clicking a
     * link in their mail client, which may have no session at all. The
     * `signed` middleware plus the email hash are what make this safe.
     */
    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return redirect()->to(
            rtrim((string) config('app.frontend_url'), '/').'/email-verified'
        );
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }

    /**
     * The one place that knows the session mechanism.
     *
     * A later native client will need token auth instead (Sanctum supports
     * both) and this is the seam it plugs into — nothing else in this
     * controller knows how the session is established (02 §1).
     */
    protected function establishSession(Request $request, User $user, bool $remember = false): void
    {
        $this->assertStatefulRequest($request);

        Auth::guard('web')->login($user, $remember);

        $request->session()->regenerate();
    }

    protected function terminateSession(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Cookie SPA auth needs a session, and Sanctum only starts one for a
     * request it recognises as first-party — that is, one whose `Origin` or
     * `Referer` matches `SANCTUM_STATEFUL_DOMAINS`.
     *
     * Without this guard, a request from an unconfigured origin (or any
     * non-browser client that sends no `Referer` at all) reaches
     * `$request->session()` and dies with an unhandled
     * "Session store not set on request" 500. That is the wrong answer twice
     * over: it is a configuration problem being reported as a server fault,
     * and the message tells the operator nothing about how to fix it.
     *
     * @throws HttpException
     */
    protected function assertStatefulRequest(Request $request): void
    {
        if ($request->hasSession()) {
            return;
        }

        abort(
            Response::HTTP_BAD_REQUEST,
            'This request was not recognised as coming from a first-party origin, so no session '
            .'could be started. Send an Origin or Referer header matching one of the configured '
            .'SANCTUM_STATEFUL_DOMAINS, and call /sanctum/csrf-cookie first.'
        );
    }
}
