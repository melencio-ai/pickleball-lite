<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PasswordRecovery;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryController extends Controller
{
    private function view(string $view, array $data = [])
    {
        abort_unless(PasswordRecovery::enabled(), 404);

        return response()->view($view, $data)
            ->withHeaders(['Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow']);
    }

    public function request()
    {
        return $this->view('auth.forgot-password');
    }

    public function send(Request $request)
    {
        abort_unless(PasswordRecovery::enabled(), 404);
        $data = $request->validate(['email'=>['required','email','max:255']]);

        try {
            Password::sendResetLink($data);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface) {
            // Avoid disclosing account existence or putting a reset link into a log.
            // Infrastructure mail delivery should be verified before enabling this route.
            logger()->warning('Password recovery email transport failed.');
        }

        return redirect()->route('app.password.request')
            ->with('status','If that email has an account, we will send a password reset link.');
    }

    public function reset(Request $request, string $token)
    {
        $email = $request->query('email');
        return $this->view('auth.reset-password', ['token'=>$token,'email'=>is_string($email) ? $email : '']);
    }

    public function update(Request $request)
    {
        abort_unless(PasswordRecovery::enabled(), 404);
        $data = $request->validate([
            'token'=>['required','string','size:64'],
            'email'=>['required','email','max:255'],
            'password'=>['required','string','min:12','max:72','confirmed',function ($attribute,$value,$fail) {
                if (strlen($value) > 72) $fail('The password must be at most 72 bytes.');
            }],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            DB::transaction(function () use ($user,$password) {
                $user->forceFill(['password'=>$password,'remember_token'=>Str::random(60)])->save();
                // Production management sessions use the shared database sessions table.
                DB::table('sessions')->where('user_id',$user->id)->delete();
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email'=>'This reset link is invalid or expired. Request a new one.']);
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('app.login')->with('status','Password updated. Sign in with your new password.');
    }
}
