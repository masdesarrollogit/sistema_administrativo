<?php

namespace Database\Seeders;

use App\Models\MoodleCategoria;
use App\Models\MoodleCurso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MoodleCursosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('data/CURSOS_MOODLE.csv');

        if (!file_exists($path)) {
            Log::error("MoodleCursosSeeder: No se encontró el archivo en {$path}");
            $this->command->error("No se encontró el archivo de datos: {$path}");
            return;
        }

        $this->command->info('Importando cursos de Moodle desde CSV...');

        $file = fopen($path, 'r');
        
        // Manejar el BOM de UTF-8 si existe
        $bom = fread($file, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($file);
        }

        // Leer cabeceras (para saltarlas)
        $headers = fgetcsv($file, 0, ';');
        
        $countCategorias = 0;
        $countCursos = 0;

        // Limpiar tablas para evitar duplicados si se vuelve a correr
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        MoodleCurso::query()->delete();
        MoodleCategoria::query()->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        DB::beginTransaction();

        try {
            // Limpiar tablas para evitar duplicados si se vuelve a correr
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            MoodleCurso::truncate();
            MoodleCategoria::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            while (($data = fgetcsv($file, 0, ';')) !== FALSE) {
                // Estructura esperada: CATEGORIA; TÍTULO; PRECIO; HORAS
                if (count($data) < 2) continue;

                $nombreCategoria = trim($data[0] ?? '');
                $tituloCurso = trim($data[1] ?? '');
                
                if (empty($nombreCategoria) || empty($tituloCurso)) continue;

                $precio = isset($data[2]) ? (float) str_replace(',', '.', trim($data[2])) : 0;
                $horas = isset($data[3]) ? (int) trim($data[3]) : 0;

                // 1. Obtener o crear la categoría
                $categoria = MoodleCategoria::firstOrCreate(
                    ['nombre' => $nombreCategoria]
                );
                
                if ($categoria->wasRecentlyCreated) {
                    $countCategorias++;
                }

                // 2. Crear el curso
                MoodleCurso::create([
                    'moodle_categoria_id' => $categoria->id,
                    'titulo' => $tituloCurso,
                    'precio' => $precio,
                    'horas' => $horas,
                ]);

                $countCursos++;
            }

            DB::commit();
            fclose($file);

            $this->command->info("✅ Importación completada: {$countCategorias} categorías y {$countCursos} cursos.");

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            Log::error("Error en MoodleCursosSeeder: " . $e->getMessage());
            $this->command->error("Error durante la importación: " . $e->getMessage());

            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            if (is_resource($file)) {
                fclose($file);
            }   
        }
    }
}
