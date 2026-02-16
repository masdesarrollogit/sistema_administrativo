<?php

namespace App\Console\Commands;

use App\Models\MoodleCategoria;
use App\Models\MoodleCurso;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarCursosMoodle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:importar-cursos {archivo=legacy/CURSOS_MOODLE.csv}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa categorías y cursos de Moodle desde un archivo CSV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rutaArchivo = $this->argument('archivo');

        if (!file_exists($rutaArchivo)) {
            $this->error("No se encontró el archivo en: {$rutaArchivo}");
            return Command::FAILURE;
        }

        $this->info("Iniciando importación desde: {$rutaArchivo}");

        $file = fopen($rutaArchivo, 'r');
        
        // Manejar el BOM de UTF-8 si existe
        $bom = fread($file, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($file);
        }

        // Leer cabeceras (para saltarlas)
        $headers = fgetcsv($file, 0, ';');
        
        $countCategorias = 0;
        $countCursos = 0;

        DB::beginTransaction();

        try {
            while (($data = fgetcsv($file, 0, ';')) !== FALSE) {
                // Estructura: CATEGORIA; TÍTULO; PRECIO; HORAS
                if (count($data) < 4) continue;

                $nombreCategoria = trim($data[0]);
                $tituloCurso = trim($data[1]);
                $precio = (float) str_replace(',', '.', trim($data[2]));
                $horas = (int) trim($data[3]);

                if (empty($nombreCategoria) || empty($tituloCurso)) continue;

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

            $this->newLine();
            $this->info("🚀 Importación completada con éxito:");
            $this->line("- Categorías creadas: {$countCategorias}");
            $this->line("- Cursos importados: {$countCursos}");

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            $this->error("❌ Error durante la importación: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
