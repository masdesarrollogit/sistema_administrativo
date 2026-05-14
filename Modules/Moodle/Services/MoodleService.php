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
        $this->url = rtrim(config('moodle.url', ''), '/');
        $this->token = config('moodle.token', '');
    }

    /**
     * Llamada genérica a la API REST de Moodle (POST).
     */
    protected function call(string $function, array $params = []): array
    {
        $http = Http::asForm();

        if ($hostOverride = config('moodle.host_override')) {
            $http = $http->withHeaders(['Host' => $hostOverride]);
        }

        $response = $http->post($this->url . '/webservice/rest/server.php', array_merge([
            'wstoken' => $this->token,
            'wsfunction' => $function,
            'moodlewsrestformat' => 'json',
        ], $params));

        if ($response->failed()) {
            throw new \Exception("Moodle API HTTP error: {$response->status()} - {$response->body()}");
        }

        $data = $response->json();

        // Algunas funciones devuelven null al tener éxito (ej: enrol_manual_enrol_users)
        if ($data === null) {
            return [];
        }

        // Moodle devuelve errores dentro del JSON
        if (isset($data['exception'])) {
            throw new \Exception("Moodle API error: {$data['errorcode']} - {$data['message']}");
        }

        return $data;
    }

    /**
     * Crear un usuario en Moodle.
     *
     * @return array Con 'id' del usuario creado
     */
    public function createUser(array $userData): array
    {
        $result = $this->call('core_user_create_users', [
            'users[0][username]' => $userData['username'],
            'users[0][password]' => $userData['password'],
            'users[0][firstname]' => $userData['firstname'],
            'users[0][lastname]' => $userData['lastname'],
            'users[0][email]' => $userData['email'],
        ]);

        if (empty($result) || !isset($result[0]['id'])) {
            throw new \Exception('Moodle no devolvió el ID del usuario creado.');
        }

        return $result[0]; // ['id' => X, 'username' => '...']
    }

    /**
     * Buscar un usuario por username (para verificar si ya existe antes de reintentar).
     *
     * @return array|null Datos del usuario o null si no existe
     */
    public function findUserByUsername(string $username): ?array
    {
        $result = $this->call('core_user_get_users', [
            'criteria[0][key]'   => 'username',
            'criteria[0][value]' => $username,
        ]);

        if (!empty($result['users'])) {
            return $result['users'][0];
        }

        return null;
    }

    /**
     * Actualizar la contraseña de un usuario existente en Moodle.
     */
    public function updateUserPassword(int $userId, string $password): void
    {
        $this->call('core_user_update_users', [
            'users[0][id]'       => $userId,
            'users[0][password]' => $password,
        ]);
    }

    /**
     * Matricular (o reactivar) un usuario en un curso.
     *
     * @param int      $roleId   5=student, 3=editingteacher (por defecto 5)
     * @param int|null $timeend  Timestamp Unix de fin de matrícula (null = sin límite)
     * @param int      $suspend  0=activo, 1=suspendido
     */
    public function enrolInCourse(int $userId, int $courseId, int $roleId = 5, ?int $timestart = null, ?int $timeend = null, int $suspend = 0): array
    {
        $params = [
            'enrolments[0][roleid]'   => $roleId,
            'enrolments[0][userid]'   => $userId,
            'enrolments[0][courseid]' => $courseId,
            'enrolments[0][suspend]'  => $suspend,
        ];

        if ($timestart !== null) {
            $params['enrolments[0][timestart]'] = $timestart;
        }

        if ($timeend !== null) {
            $params['enrolments[0][timeend]'] = $timeend;
        }

        try {
            return $this->call('enrol_manual_enrol_users', $params);
        } catch (\Exception $e) {
            // Moodle matricula al alumno pero falla al enviar su notificación interna
            if (str_contains($e->getMessage(), 'Message was not sent')) {
                Log::warning("Moodle: matrícula ejecutada pero notificación interna falló (user={$userId}, course={$courseId}): {$e->getMessage()}");
                return [];
            }
            throw $e;
        }
    }

    /**
     * Obtener datos de varios usuarios por sus IDs en una sola llamada.
     * Devuelve para cada usuario, entre otros: id, username, email, lastaccess, firstaccess, suspended.
     *
     * @param int[] $userIds
     * @return array<int, array> indexado por moodle user id
     */
    public function getUsersByIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if (empty($userIds)) {
            return [];
        }

        $params = ['field' => 'id'];
        foreach ($userIds as $i => $id) {
            $params["values[{$i}]"] = $id;
        }

        $result = $this->call('core_user_get_users_by_field', $params);

        $byId = [];
        foreach ($result as $user) {
            if (isset($user['id'])) {
                $byId[(int) $user['id']] = $user;
            }
        }

        return $byId;
    }

    /**
     * Obtener las notas del usuario (formato HTML embebido en JSON, legado).
     */
    public function getUserGrades(int $userId): array
    {
        return $this->call('gradereport_user_get_grades_table', [
            'userid' => $userId,
        ]);
    }

    /**
     * Obtener los grade items de un usuario en un curso (JSON estructurado).
     * Devuelve cada test/actividad/total con grade, grademax, percentageformatted, etc.
     *
     * @return array Estructura de Moodle: { usergrades: [{ gradeitems: [...] }] }
     */
    public function getUserGradeItems(int $userId, int $courseId): array
    {
        return $this->call('gradereport_user_get_grade_items', [
            'courseid' => $courseId,
            'userid'   => $userId,
        ]);
    }

    /**
     * Obtener los cursos en los que un usuario está matriculado (como alumno o profesor).
     *
     * @return array Lista de cursos con 'id' y 'fullname'
     */
    public function getUserCourses(int $userId): array
    {
        $result = $this->call('core_enrol_get_users_courses', [
            'userid' => $userId,
        ]);

        return is_array($result) ? $result : [];
    }

    /**
     * Crear un grupo dentro de un curso de Moodle.
     * Devuelve el ID del grupo creado.
     */
    public function createGroup(int $courseId, string $name): int
    {
        $result = $this->call('core_group_create_groups', [
            'groups[0][courseid]'          => $courseId,
            'groups[0][name]'              => $name,
            'groups[0][description]'       => '',
            'groups[0][descriptionformat]' => 1,
        ]);

        if (empty($result) || !isset($result[0]['id'])) {
            throw new \Exception('Moodle no devolvió el ID del grupo creado.');
        }

        return (int) $result[0]['id'];
    }

    /**
     * Añadir usuarios a un grupo de Moodle.
     *
     * @param int $groupId ID del grupo en Moodle
     * @param int[] $userIds IDs de los usuarios en Moodle
     */
    public function addUsersToGroup(int $groupId, array $userIds): void
    {
        $params = [];
        foreach (array_values($userIds) as $i => $userId) {
            $params["members[{$i}][groupid]"] = $groupId;
            $params["members[{$i}][userid]"]  = $userId;
        }

        $this->call('core_group_add_group_members', $params);
    }

    /**
     * Listar todas las categorías Moodle.
     *
     * @return array Lista de categorías con 'id', 'name', 'parent', 'coursecount', ...
     */
    public function getCategories(): array
    {
        $result = $this->call('core_course_get_categories', []);
        return is_array($result) ? $result : [];
    }

    /**
     * Buscar cursos por nombre (texto libre).
     */
    public function searchCourses(string $query, int $perpage = 10): array
    {
        $result = $this->call('core_course_search_courses', [
            'criterianame'  => 'search',
            'criteriavalue' => $query,
            'page'          => 0,
            'perpage'       => $perpage,
        ]);

        return $result['courses'] ?? [];
    }

    /**
     * Verificar que la conexión con Moodle funciona.
     */
    public function testConnection(): bool
    {
        try {
            $result = $this->call('core_webservice_get_site_info');
            return isset($result['sitename']);
        } catch (\Exception $e) {
            Log::error('MoodleService: Error de conexión - ' . $e->getMessage());
            return false;
        }
    }
}
