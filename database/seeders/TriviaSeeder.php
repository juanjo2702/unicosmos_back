<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TriviaSeeder extends Seeder
{
    public function run(): void
    {
        $this->archiveLegacyDemoCatalog();

        $admin = $this->upsertUser([
            'name' => 'Admin User',
            'email' => 'admin@trivia.com',
            'role' => 'admin',
        ]);

        $presenter = $this->upsertUser([
            'name' => 'Presenter User',
            'email' => 'presenter@trivia.com',
            'role' => 'presenter',
        ]);

        $this->upsertUser([
            'name' => 'Player Demo',
            'email' => 'player@trivia.com',
            'role' => 'player',
        ]);

        $categoriesCreated = 0;
        $questionsCreated = 0;

        foreach ($this->careerCatalog() as $career) {
            $category = $this->persistCategory($career, $presenter->id);
            $categoriesCreated++;

            foreach ($this->buildQuestionPack($career) as $questionData) {
                $this->persistQuestion($category->id, $questionData, $presenter->id);
                $questionsCreated++;
            }
        }

        $this->command->info('Academic trivia seeding completed!');
        $this->command->info("Admin: {$admin->email} / password");
        $this->command->info("Presenter: {$presenter->email} / password");
        $this->command->info('Player: player@trivia.com / password');
        $this->command->info("Categorias preparadas: {$categoriesCreated}");
        $this->command->info("Preguntas preparadas: {$questionsCreated}");
    }

    private function archiveLegacyDemoCatalog(): void
    {
        $legacyCategoryNames = ['Science', 'History', 'Sports', 'Entertainment', 'Geography'];

        $legacyCategories = Category::query()
            ->whereIn('name', $legacyCategoryNames)
            ->get();

        if ($legacyCategories->isEmpty()) {
            return;
        }

        Question::query()
            ->whereIn('category_id', $legacyCategories->pluck('id'))
            ->delete();

        foreach ($legacyCategories as $legacyCategory) {
            $legacyCategory->delete();
        }
    }

    private function upsertUser(array $data): User
    {
        return User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make('password'),
                'role' => $data['role'],
                'is_active' => true,
            ]
        );
    }

    private function persistCategory(array $career, int $presenterId): Category
    {
        $category = Category::withTrashed()->firstOrNew([
            'name' => $career['name'],
            'description' => $career['description'],
        ]);

        $category->fill([
            'color' => $career['color'],
            'icon' => $career['icon'],
            'created_by' => $presenterId,
            'is_active' => true,
        ]);

        $category->deleted_at = null;
        $category->save();

        return $category;
    }

    private function persistQuestion(int $categoryId, array $questionData, int $presenterId): Question
    {
        $question = Question::withTrashed()->firstOrNew([
            'category_id' => $categoryId,
            'question_text' => $questionData['question_text'],
        ]);

        $question->fill([
            'type' => 'multiple_choice',
            'options' => $questionData['options'],
            'correct_answer' => null,
            'points' => $questionData['points'],
            'time_limit' => $questionData['time_limit'],
            'difficulty' => $questionData['difficulty'],
            'created_by' => $presenterId,
            'is_active' => true,
        ]);

        $question->deleted_at = null;
        $question->save();

        return $question;
    }

    private function careerCatalog(): array
    {
        return [
            ['name' => 'Odontología', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#0ea5e9', 'icon' => 'sparkles', 'pack' => 'health'],
            ['name' => 'Enfermería', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#14b8a6', 'icon' => 'heart', 'pack' => 'health'],
            ['name' => 'Medicina', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#ef4444', 'icon' => 'stethoscope', 'pack' => 'health'],
            ['name' => 'Veterinaria y Zootecnia', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#22c55e', 'icon' => 'paw-print', 'pack' => 'health'],
            ['name' => 'Fisioterapia y Kinesiología', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#06b6d4', 'icon' => 'activity', 'pack' => 'health'],
            ['name' => 'Bioquímica y Farmacia', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#6366f1', 'icon' => 'flask-conical', 'pack' => 'health'],
            ['name' => 'Fonoaudiología', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#f97316', 'icon' => 'audio-lines', 'pack' => 'health'],
            ['name' => 'Nutrición y dietética', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#84cc16', 'icon' => 'apple', 'pack' => 'health'],
            ['name' => 'Téc. Sup. Prótesis Dental', 'description' => 'Facultad de Ciencias de la Salud', 'color' => '#f59e0b', 'icon' => 'smile-plus', 'pack' => 'health'],

            ['name' => 'Ingeniería de Sonido', 'description' => 'Facultad de Ciencias de la Ingeniería', 'color' => '#8b5cf6', 'icon' => 'music-4', 'pack' => 'engineering'],
            ['name' => 'Ingeniería de Sistemas', 'description' => 'Facultad de Ciencias de la Ingeniería', 'color' => '#3b82f6', 'icon' => 'cpu', 'pack' => 'engineering'],
            ['name' => 'Ingeniería Electrónica', 'description' => 'Facultad de Ciencias de la Ingeniería', 'color' => '#f43f5e', 'icon' => 'circuit-board', 'pack' => 'engineering'],
            ['name' => 'Ingeniería Biomédica', 'description' => 'Facultad de Ciencias de la Ingeniería', 'color' => '#10b981', 'icon' => 'heart-pulse', 'pack' => 'engineering'],

            ['name' => 'Contaduría Pública', 'description' => 'Facultad de Ciencias Económicas, Financieras, Empresariales y Administrativas', 'color' => '#f59e0b', 'icon' => 'calculator', 'pack' => 'economics'],
            ['name' => 'Administración de Empresas', 'description' => 'Facultad de Ciencias Económicas, Financieras, Empresariales y Administrativas', 'color' => '#0f766e', 'icon' => 'briefcase-business', 'pack' => 'economics'],
            ['name' => 'Ingeniería Comercial', 'description' => 'Facultad de Ciencias Económicas, Financieras, Empresariales y Administrativas', 'color' => '#2563eb', 'icon' => 'line-chart', 'pack' => 'economics'],

            ['name' => 'Comunicación Social', 'description' => 'Facultad de Ciencias Sociales y Jurídicas', 'color' => '#ec4899', 'icon' => 'megaphone', 'pack' => 'social'],
            ['name' => 'Cinematografía', 'description' => 'Facultad de Ciencias Sociales y Jurídicas', 'color' => '#7c3aed', 'icon' => 'film', 'pack' => 'social'],
            ['name' => 'Artes y Escultura', 'description' => 'Facultad de Ciencias Sociales y Jurídicas', 'color' => '#ea580c', 'icon' => 'palette', 'pack' => 'social'],
            ['name' => 'Derecho', 'description' => 'Facultad de Ciencias Sociales y Jurídicas', 'color' => '#475569', 'icon' => 'scale', 'pack' => 'social'],

            ['name' => 'Contaduría Pública', 'description' => 'Licenciatura para Técnicos Superiores', 'color' => '#ca8a04', 'icon' => 'receipt-text', 'pack' => 'bridge'],
            ['name' => 'Ingeniería Comercial', 'description' => 'Licenciatura para Técnicos Superiores', 'color' => '#0284c7', 'icon' => 'badge-dollar-sign', 'pack' => 'bridge'],
            ['name' => 'Administración de Empresas', 'description' => 'Licenciatura para Técnicos Superiores', 'color' => '#15803d', 'icon' => 'building-2', 'pack' => 'bridge'],
        ];
    }

    private function buildQuestionPack(array $career): array
    {
        return match ($career['pack']) {
            'health' => $this->healthQuestions($career['name']),
            'engineering' => $this->engineeringQuestions($career['name']),
            'economics' => $this->economicsQuestions($career['name']),
            'social' => $this->socialQuestions($career['name']),
            'bridge' => $this->bridgeQuestions($career['name']),
            default => [],
        };
    }

    private function healthQuestions(string $career): array
    {
        return [
            $this->question("En una trivia de {$career}, ¿que vitamina se asocia con la exposicion solar?", 'Vitamina D', ['Vitamina C', 'Vitamina B12', 'Vitamina K'], 10, 'easy'),
            $this->question("¿Que organo bombea la sangre por todo el cuerpo?", 'Corazon', ['Pulmon', 'Higado', 'Rinon'], 10, 'easy'),
            $this->question("¿Que accion reduce mejor el riesgo de infecciones en practicas de {$career}?", 'Lavarse correctamente las manos', ['Hablar mas fuerte', 'Usar solo lapiz', 'Tomar cafe antes'], 10, 'easy'),
            $this->question("¿Cual es la unidad basica de la vida?", 'Celula', ['Molecula', 'Neuron', 'Tejido'], 10, 'easy'),
            $this->question("¿Que sistema corporal permite el intercambio de oxigeno y dioxido de carbono?", 'Sistema respiratorio', ['Sistema digestivo', 'Sistema nervioso', 'Sistema oseo'], 10, 'easy'),
            $this->question("¿Que mineral se relaciona mas con huesos y dientes fuertes?", 'Calcio', ['Sodio', 'Potasio', 'Yodo'], 10, 'easy'),
            $this->question("¿Cual es la temperatura corporal promedio de una persona sana?", '37 °C', ['32 °C', '40 °C', '28 °C'], 10, 'easy'),
            $this->question("¿Que instrumento se usa comunmente para escuchar sonidos internos del cuerpo?", 'Estetoscopio', ['Termometro', 'Otoscopio', 'Microscopio'], 10, 'easy'),
            $this->question("En {$career}, ¿que alimento destaca por su aporte de proteinas?", 'Huevo', ['Azucar', 'Gaseosa', 'Caramelo'], 10, 'easy'),
            $this->question("¿Que significa una buena hidratacion en el contexto de salud?", 'Consumir suficiente agua durante el dia', ['Dormir solo 3 horas', 'Evitar toda fruta', 'Comer solo frituras'], 10, 'easy'),
            $this->question("¿Que articulacion conecta el muslo con la pierna?", 'Rodilla', ['Codo', 'Muneca', 'Tobillo'], 10, 'easy'),
            $this->question("¿Que medida de bioseguridad es basica en laboratorio o clinica?", 'Usar guantes y bata cuando corresponde', ['Compartir agujas', 'Entrar sin lavarse', 'No rotular muestras'], 15, 'medium'),
            $this->question("¿Que examen suele medir la glucosa en sangre?", 'Analisis de glucosa', ['Radiografia dental', 'Electrocardiograma', 'Audiometria'], 15, 'medium'),
            $this->question("¿Que organo filtra la sangre y ayuda a formar la orina?", 'Rinon', ['Bazo', 'Pancreas', 'Piel'], 15, 'medium'),
            $this->question("Para rendir mejor en {$career}, ¿que habito favorece la recuperacion fisica y mental?", 'Dormir y descansar bien', ['Saltarse comidas', 'Estudiar sin pausas siempre', 'Tomar solo bebidas energeticas'], 20, 'medium'),
        ];
    }

    private function engineeringQuestions(string $career): array
    {
        return [
            $this->question("En una introduccion a {$career}, ¿que unidad mide la frecuencia?", 'Hercio', ['Voltio', 'Metro', 'Newton'], 10, 'easy'),
            $this->question("¿Que sistema numerico usan de forma basica las computadoras?", 'Binario', ['Decimal romano', 'Hexagesimal', 'Fraccional'], 10, 'easy'),
            $this->question("¿Que componente electronico almacena carga electrica?", 'Capacitor', ['Tornillo', 'Motor diesel', 'Rodamiento'], 10, 'easy'),
            $this->question("¿Que practica ayuda mas a reducir fallas en proyectos de {$career}?", 'Probar y documentar', ['Improvisar siempre', 'Ignorar resultados', 'Cambiar todo al final'], 10, 'easy'),
            $this->question("¿Que sigla identifica al procesador principal de un equipo?", 'CPU', ['USB', 'RAMA', 'PDF'], 10, 'easy'),
            $this->question("¿Que unidad mide la resistencia electrica?", 'Ohmio', ['Tesla', 'Pascal', 'Lumen'], 10, 'easy'),
            $this->question("¿Que representa un prototipo en ingenieria?", 'Una primera version para probar una idea', ['El producto final obligatorio', 'Una multa tecnica', 'Un error de fabrica'], 10, 'easy'),
            $this->question("En seguridad digital, ¿que accion protege mejor una cuenta?", 'Usar contraseñas robustas', ['Compartir la clave', 'Escribirla en la pantalla', 'Usar siempre 123456'], 10, 'easy'),
            $this->question("¿Que dispositivo convierte energia electrica en luz en muchos equipos modernos?", 'LED', ['Engrane', 'Resorte', 'Polea'], 10, 'easy'),
            $this->question("¿Que archivo suele contener instrucciones editables de un programa?", 'Codigo fuente', ['Factura impresa', 'Plano firmado', 'Certificado medico'], 10, 'easy'),
            $this->question("¿Que unidad mide la potencia electrica?", 'Watt', ['Segundo', 'Litro', 'Gramo'], 15, 'medium'),
            $this->question("¿Que concepto describe una serie ordenada de pasos para resolver un problema?", 'Algoritmo', ['Balance', 'Contrato', 'Eslogan'], 15, 'medium'),
            $this->question("En {$career}, ¿que tipo de onda suele relacionarse con transmision de audio o datos sin cables?", 'Onda de radio', ['Onda marina', 'Onda tectonica', 'Onda termica'], 15, 'medium'),
            $this->question("¿Que sensor se usa para detectar temperatura en muchos dispositivos?", 'Termistor', ['Altavoz', 'Piston', 'Bisagra'], 15, 'medium'),
            $this->question("¿Que area une tecnologia y salud para crear equipos y soluciones clinicas?", 'Ingenieria biomedica', ['Astronomia', 'Botanica', 'Arqueologia'], 20, 'medium'),
        ];
    }

    private function economicsQuestions(string $career): array
    {
        return [
            $this->question("En una trivia de {$career}, ¿que es la inflacion?", 'El aumento general de precios', ['La baja del volumen de ventas solamente', 'Un impuesto unico', 'Un tipo de contrato laboral'], 10, 'easy'),
            $this->question("¿Que describe mejor un presupuesto?", 'Un plan de ingresos y gastos', ['Un comercial de television', 'Un examen oral', 'Una constancia medica'], 10, 'easy'),
            $this->question("¿Que es un activo en terminos financieros?", 'Un recurso que aporta valor', ['Un gasto innecesario', 'Una deuda vencida', 'Una multa administrativa'], 10, 'easy'),
            $this->question("¿Que documento respalda formalmente una venta?", 'Factura', ['Croquis', 'Boleta de cine', 'Receta medica'], 10, 'easy'),
            $this->question("Si el precio sube mucho, ¿que suele pasar con la demanda?", 'Tiende a disminuir', ['Siempre se duplica', 'Nunca cambia', 'Desaparece la oferta'], 10, 'easy'),
            $this->question("¿Que resultado se obtiene al restar costos a los ingresos?", 'Utilidad o ganancia', ['Frecuencia cardiaca', 'Velocidad angular', 'Indice de masa corporal'], 10, 'easy'),
            $this->question("¿Que habilidad es clave en {$career} para coordinar personas y objetivos?", 'Liderazgo', ['Improvisacion sin datos', 'Aislamiento total', 'Desorden permanente'], 10, 'easy'),
            $this->question("¿Que busca el marketing?", 'Conectar productos o servicios con su publico', ['Curar fracturas', 'Medir ondas cerebrales', 'Reemplazar leyes'], 10, 'easy'),
            $this->question("¿Que significa ahorrar?", 'Reservar parte del ingreso para el futuro', ['Gastar todo hoy', 'Eliminar impuestos', 'Duplicar billetes'], 10, 'easy'),
            $this->question("¿Que indicador muestra si un negocio vende mas de lo que gasta?", 'Rentabilidad', ['Pulso', 'Voltaje', 'Tension arterial'], 10, 'easy'),
            $this->question("¿Que practica mejora la toma de decisiones en {$career}?", 'Analizar datos antes de actuar', ['Confiar solo en rumores', 'Cambiar metas cada hora', 'Ignorar registros'], 15, 'medium'),
            $this->question("¿Que hace una conciliacion bancaria?", 'Compara registros internos con movimientos del banco', ['Diseña un logotipo', 'Mide la temperatura', 'Edita una pelicula'], 15, 'medium'),
            $this->question("¿Que describe mejor al capital de trabajo?", 'Recursos para operar en el corto plazo', ['El uniforme del equipo', 'La decoracion de oficina', 'El nombre comercial'], 15, 'medium'),
            $this->question("¿Que estrategia ayuda a que una empresa sea mas competitiva?", 'Conocer a su cliente y mejorar continuamente', ['Copiar sin analizar', 'Vender sin control', 'No medir resultados'], 15, 'medium'),
            $this->question("En {$career}, ¿que valor fortalece la confianza de clientes y equipos?", 'Transparencia', ['Ocultar errores siempre', 'Prometer sin cumplir', 'Modificar cifras sin respaldo'], 20, 'medium'),
        ];
    }

    private function socialQuestions(string $career): array
    {
        return [
            $this->question("En una introduccion a {$career}, ¿que practica hace mas creible una noticia o contenido?", 'Verificar datos y fuentes', ['Inventar detalles', 'Copiar sin citar', 'Publicar sin revisar'], 10, 'easy'),
            $this->question("¿Que es la Constitucion en un Estado de derecho?", 'La norma juridica fundamental', ['Una campaña de marketing', 'Un genero musical', 'Un tipo de escultura'], 10, 'easy'),
            $this->question("¿Que habilidad mejora mas una exposicion oral?", 'Hablar con claridad y orden', ['Leer de espaldas', 'Evitar todo contacto visual', 'Cambiar de tema sin aviso'], 10, 'easy'),
            $this->question("¿Que protege el derecho de autor?", 'Las obras y creaciones intelectuales', ['Solo los edificios publicos', 'Solo los billetes', 'Solo los uniformes'], 10, 'easy'),
            $this->question("¿Que documento organiza visualmente escenas antes de filmar?", 'Storyboard', ['Balance general', 'Historia clinica', 'Manual de soldadura'], 10, 'easy'),
            $this->question("¿Que elemento caracteriza a la escultura?", 'El trabajo artistico en volumen', ['El calculo contable', 'La cirugia menor', 'La programacion binaria'], 10, 'easy'),
            $this->question("¿Que poder del Estado crea y aprueba leyes?", 'Poder legislativo', ['Poder ejecutivo solamente', 'Poder familiar', 'Poder deportivo'], 10, 'easy'),
            $this->question("¿Que significa citar una fuente?", 'Reconocer de donde proviene la informacion', ['Cambiar el autor', 'Borrar referencias', 'Inventar estadisticas'], 10, 'easy'),
            $this->question("¿Que ayuda primero a resolver un conflicto de forma responsable?", 'Escuchar a las partes', ['Interrumpir siempre', 'Difundir rumores', 'Ignorar el contexto'], 10, 'easy'),
            $this->question("¿Que es un guion cinematografico?", 'La guia escrita de una historia audiovisual', ['Una cuenta bancaria', 'Una radiografia', 'Una receta de laboratorio'], 10, 'easy'),
            $this->question("¿Que significa audiencia en comunicacion?", 'El publico al que va dirigido el mensaje', ['Solo los camarografos', 'Solo los jueces', 'Solo los escultores'], 15, 'medium'),
            $this->question("¿Que plano muestra a una persona de pies a cabeza?", 'Plano entero', ['Plano detalle', 'Plano cenital siempre', 'Plano financiero'], 15, 'medium'),
            $this->question("¿Que rol cumple la evidencia en derecho o periodismo?", 'Sustentar hechos y argumentos', ['Decorar una presentacion', 'Aumentar el volumen de voz', 'Definir el vestuario'], 15, 'medium'),
            $this->question("¿Que valor fortalece la etica profesional en {$career}?", 'Responsabilidad con la verdad y el impacto social', ['Ocultar datos utiles', 'Confundir al publico', 'Difundir sin contexto'], 15, 'medium'),
            $this->question("¿Que recurso mejora la narrativa visual en cine, arte o comunicacion?", 'Composicion intencional de imagen y mensaje', ['Ruido sin proposito', 'Texto ilegible', 'Color al azar siempre'], 20, 'medium'),
        ];
    }

    private function bridgeQuestions(string $career): array
    {
        return [
            $this->question("En la continuidad academica de {$career}, ¿que aporta una licenciatura?", 'Mayor profundidad teorica y estrategica', ['Menos practica siempre', 'Cero analisis', 'Solo materias deportivas'], 10, 'easy'),
            $this->question("¿Que habilidad sigue siendo clave al pasar de tecnico superior a licenciatura?", 'Aplicar criterio profesional a casos reales', ['Memorizar sin entender', 'Evitar trabajar en equipo', 'No usar datos'], 10, 'easy'),
            $this->question("¿Que documento ordena metas, tiempos y recursos de un proyecto?", 'Plan de trabajo', ['Historia clinica', 'Mapa turistico', 'Cedula artistica'], 10, 'easy'),
            $this->question("¿Que practica mejora la gestion profesional en {$career}?", 'Tomar decisiones con informacion confiable', ['Improvisar sin medir', 'Cambiar cifras sin respaldo', 'Ignorar resultados'], 10, 'easy'),
            $this->question("¿Que significa escalar un negocio o servicio?", 'Hacerlo crecer de forma sostenible', ['Cambiarle solo el nombre', 'Quitarle clientes', 'Eliminar procesos'], 10, 'easy'),
            $this->question("¿Que valor fortalece la relacion con clientes, proveedores o equipos?", 'Confianza', ['Ambiguedad constante', 'Desorganizacion', 'Opacidad'], 10, 'easy'),
            $this->question("¿Que uso tienen los indicadores en {$career}?", 'Medir resultados para mejorar', ['Decoracion de informes', 'Solo llenar espacio', 'Evitar decisiones'], 10, 'easy'),
            $this->question("¿Que describe mejor una estrategia?", 'Un camino pensado para alcanzar objetivos', ['Una improvisacion permanente', 'Una actividad sin meta', 'Un gasto obligatorio'], 10, 'easy'),
            $this->question("¿Que accion impulsa la innovacion profesional?", 'Detectar problemas y proponer mejoras', ['Repetir errores', 'Evitar preguntas', 'Copiar todo sin adaptar'], 10, 'easy'),
            $this->question("¿Que recurso ayuda a presentar hallazgos con claridad?", 'Un informe bien estructurado', ['Un mensaje sin datos', 'Un audio sin contexto', 'Un titulo ambiguo'], 10, 'easy'),
            $this->question("En {$career}, ¿que combina mejor formacion tecnica y gestion?", 'Analisis, ejecucion y vision de crecimiento', ['Solo intuicion', 'Solo suerte', 'Solo decoracion'], 15, 'medium'),
            $this->question("¿Que beneficio tiene entender costos, mercado y operaciones al mismo tiempo?", 'Tomar decisiones mas completas', ['Reducir toda planificacion', 'Eliminar controles', 'Trabajar sin objetivos'], 15, 'medium'),
            $this->question("¿Que hace mas valioso a un profesional de continuidad universitaria?", 'Conectar practica previa con pensamiento estrategico', ['Olvidar su experiencia', 'Trabajar sin metodo', 'Ignorar al usuario'], 15, 'medium'),
            $this->question("¿Que herramienta apoya mejor la mejora continua?", 'Seguimiento de resultados y retroalimentacion', ['Rumores de pasillo', 'Cambios sin evaluar', 'Silencio total'], 15, 'medium'),
            $this->question("¿Que enfoque vuelve mas atractiva la propuesta de valor de {$career}?", 'Resolver problemas reales con criterio profesional', ['Prometer sin sustento', 'Vender humo', 'Ocultar informacion clave'], 20, 'medium'),
        ];
    }

    private function question(
        string $questionText,
        string $correctOption,
        array $incorrectOptions,
        int $points,
        string $difficulty,
        int $timeLimit = 30
    ): array {
        return [
            'question_text' => $questionText,
            'points' => $points,
            'difficulty' => $difficulty,
            'time_limit' => $timeLimit,
            'options' => [
                $this->option($correctOption, true),
                $this->option($incorrectOptions[0], false),
                $this->option($incorrectOptions[1], false),
                $this->option($incorrectOptions[2], false),
            ],
        ];
    }

    private function option(string $text, bool $isCorrect): array
    {
        return [
            'text' => $text,
            'is_correct' => $isCorrect,
        ];
    }
}
