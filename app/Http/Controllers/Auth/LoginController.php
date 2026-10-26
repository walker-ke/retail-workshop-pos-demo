<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    protected function authenticated(\Illuminate\Http\Request $request, $user)
    {
        if (config('demo.enabled') && $user->isTopAdmin()) return redirect()->route('home');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * The login form posts a single "login" field that can be either a
     * username or an email - lets an admin spin up a temp-worker account
     * with just a username during peak sale times, no real email needed.
     */
    public function username()
    {
        return 'login';
    }

    protected function validateLogin(\Illuminate\Http\Request $request)
    {
        $request->validate(['login' => 'required|string|max:255', 'password' => 'required|string|max:4096']);
    }

    protected function credentials(\Illuminate\Http\Request $request)
    {
        $login = $request->input($this->username());
        $field = User::where('username', $login)->exists() ? 'username' : 'email';

        return [$field => $login, 'password' => $request->password, 'active' => 1];
    }
}
