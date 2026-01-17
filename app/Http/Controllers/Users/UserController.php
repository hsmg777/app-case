<?php

namespace App\Http\Controllers\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    // Listado de usuarios (roles incluidos)
    public function index()
    {
        $usuarios = User::with('roles', 'bodega')->get();
        $roles = Role::pluck('name', 'id');
        $bodegas = \App\Models\Store\Bodega::orderBy('nombre')->pluck('nombre', 'id');

        return view('users.index', compact('usuarios', 'roles', 'bodegas'));
    }

    // Guardar usuario (modal crear)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role_id' => 'required|exists:roles,id',
            'bodega_id' => 'nullable|exists:bodegas,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'bodega_id' => $request->bodega_id,
        ]);

        $user->assignRole(Role::find($request->role_id)->name);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    // Actualizar usuario (modal editar)
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role_id' => 'required|exists:roles,id',
            'bodega_id' => 'nullable|exists:bodegas,id',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'bodega_id' => $request->bodega_id,
        ]);

        // Actualizar rol
        $user->syncRoles([Role::find($request->role_id)->name]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    // Eliminar usuario
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
