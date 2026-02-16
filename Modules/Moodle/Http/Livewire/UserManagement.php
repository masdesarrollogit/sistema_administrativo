<?php

namespace Modules\Moodle\Http\Livewire;

use App\Models\User;
use Livewire\Component;
use Modules\Moodle\Services\MoodleService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagement extends Component
{
    public $name;
    public $email;
    public $password;
    public $username;

    protected $rules = [
        'name' => 'required|min:3',
        'email' => 'required|email|unique:users,email',
        'username' => 'required|alpha_dash|unique:users,username|min:3',
        'password' => 'required|min:8',
    ];

    public function createUser(MoodleService $moodleService)
    {
        $this->validate();

        // 1. Crear usuario localmente
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        // 2. Intentar crear en Moodle
        $names = explode(' ', $this->name, 2);
        $firstname = $names[0];
        $lastname = $names[1] ?? '.';

        $moodleUser = $moodleService->createUser([
            'username' => $this->username,
            'password' => $this->password,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $this->email,
        ]);

        if (!$moodleUser) {
            session()->flash('warning', 'Usuario creado localmente, pero falló la sincronización con Moodle. Revisa los logs.');
        } else {
            session()->flash('message', 'Usuario creado exitosamente en Laravel y Moodle.');
        }

        $this->reset(['name', 'email', 'password', 'username']);
    }

    public function render()
    {
        return view('moodle::livewire.user-management', [
            'users' => User::latest()->paginate(10)
        ]);
    }
}
