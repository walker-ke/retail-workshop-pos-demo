<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Shop;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('access:users_security');
    }

    public function index()
    {
        $user = User::orderBy('id')->get();
        $shops = Shop::orderBy('name')->get();
        return view('users.user_index', compact('user', 'shops'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'nullable|alpha_dash|max:255|unique:users,username|required_without:email',
            'email' => 'nullable|email|max:255|unique:users,email|required_without:username',
            'password' => 'required|string|min:12|max:72',
            'access_level' => 'required|integer|in:0,1,9',
            'shop_id' => [\Illuminate\Validation\Rule::requiredIf((int) $request->access_level !== User::OWNER), 'nullable', 'exists:shops,id'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('user')->with('error', $validator->errors()->first());
        }

        User::create([
            'name' => $request->name,
            'username' => $request->username ?: null,
            'email' => $request->email ?: null,
            'password' => Hash::make($request->password),
            'access_level' => $request->access_level,
            'shop_id' => $request->shop_id ?: null,
            'active' => $request->has('active') ? 1 : 0,
        ]);

        return redirect()->route('user')->with('success', 'User Added Successfully');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => ['nullable', 'alpha_dash', 'max:255', 'required_without:email', \Illuminate\Validation\Rule::unique('users', 'username')->ignore($user)],
            'email' => ['nullable', 'email', 'max:255', 'required_without:username', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user)],
            'access_level' => 'required|integer|in:0,1,9',
            'shop_id' => [\Illuminate\Validation\Rule::requiredIf((int) $request->access_level !== User::OWNER), 'nullable', 'exists:shops,id'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('user')->with('error', $validator->errors()->first());
        }

        if ($user->isTopAdmin() && ((int) $request->access_level !== User::OWNER || !$request->has('active'))
            && User::where('access_level', User::OWNER)->where('active', true)->where('id', '!=', $user->id)->count() === 0) {
            return redirect()->route('user')->with('error', 'Keep at least one active Owner / Admin account.');
        }
        $user->name = $request->name;
        $user->username = $request->username ?: null;
        $user->email = $request->email ?: null;
        $user->access_level = $request->access_level;
        $user->shop_id = $request->shop_id ?: null;
        $user->active = $request->has('active') ? 1 : 0;
        $user->save();

        return redirect()->route('user')->with('success', 'User Updated Successfully');
    }

    public function resetPassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:12|max:72|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->route('user')->with('error', $validator->errors()->first());
        }

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->remember_token = null;
        $user->save();

        return redirect()->route('user')->with('success', 'Password Reset Successfully');
    }

    public function deleteUser(Request $request)
    {
        $user_id = $request->input('delete_user');

        if (User::count() <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the only remaining user account.');
        }

        $user = User::findOrFail($user_id);
        if ($user->isTopAdmin() && User::where('access_level', User::OWNER)->where('active', true)->where('id', '!=', $user->id)->count() === 0) {
            return redirect()->route('user')->with('error', 'Keep at least one active Owner / Admin account.');
        }
        $user->delete();
        return redirect()->back()->with('success', 'User Deleted Successfully');
    }

    public function security()
    {
        return view('users.security');
    }
}
