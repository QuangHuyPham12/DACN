<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.users', ['pageTitle' => 'Tài khoản nhân viên', 'users' => User::orderBy('id')->paginate(15), 'edit' => $request->query('edit') ? User::findOrFail($request->integer('edit')) : null]);
    }

    public function save(Request $request, ?User $user = null)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)], 'role' => 'required|in:admin,staff', 'is_active' => 'required|boolean', 'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()]]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user?->id === $request->user()->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'Bạn không thể tự khóa hoặc bỏ quyền quản trị của mình.']);
        }
        if ($user) {
            $user->update($data);
        } else {
            User::create($data);
        }

        return redirect()->route('users.index')->with('success', 'Đã lưu tài khoản.');
    }
}
