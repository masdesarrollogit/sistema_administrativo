<?php

namespace Modules\Moodle\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoodleService
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url = config('moodle.url', env('MOODLE_URL'));
        $this->token = config('moodle.token', env('MOODLE_TOKEN'));
    }

    /**
     * Crear un usuario en Moodle.
     */
    public function createUser(array $userData)
    {
        try {
            $response = Http::get($this->url . '/webservice/rest/server.php', [
                'wstoken' => $this->token,
                'wsfunction' => 'core_user_create_users',
                'moodlewsrestformat' => 'json',
                'users' => [
                    [
                        'username' => $userData['username'],
                        'password' => $userData['password'],
                        'firstname' => $userData['firstname'],
                        'lastname' => $userData['lastname'],
                        'email' => $userData['email'],
                    ]
                ]
            ]);

            if ($response->failed()) {
                throw new \Exception('Error al conectar con Moodle: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Error en MoodleService@createUser: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Matricular un usuario en un curso.
     */
    public function enrolInCourse(int $userId, int $courseId, int $roleId = 5) // 5 es el rol de estudiante por defecto
    {
        try {
            $response = Http::get($this->url . '/webservice/rest/server.php', [
                'wstoken' => $this->token,
                'wsfunction' => 'enrol_manual_enrol_users',
                'moodlewsrestformat' => 'json',
                'enrolments' => [
                    [
                        'roleid' => $roleId,
                        'userid' => $userId,
                        'courseid' => $courseId,
                    ]
                ]
            ]);

            if ($response->failed()) {
                throw new \Exception('Error al matricular en Moodle: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Error en MoodleService@enrolInCourse: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener las notas del usuario.
     */
    public function getUserGrades(int $userId)
    {
        try {
            $response = Http::get($this->url . '/webservice/rest/server.php', [
                'wstoken' => $this->token,
                'wsfunction' => 'gradereport_user_get_grades_table',
                'moodlewsrestformat' => 'json',
                'userid' => $userId,
            ]);

            if ($response->failed()) {
                throw new \Exception('Error al obtener notas de Moodle: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Error en MoodleService@getUserGrades: ' . $e->getMessage());
            return null;
        }
    }
}
