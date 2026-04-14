<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Game;
use App\Models\Question;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TriviaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@trivia.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create presenter user
        $presenter = User::create([
            'name' => 'Presenter User',
            'email' => 'presenter@trivia.com',
            'password' => Hash::make('password'),
            'role' => 'presenter',
            'is_active' => true,
        ]);

        // Create a game
        $game = Game::create([
            'name' => 'Demo Trivia Game',
            'code' => strtoupper(substr(md5(uniqid()), 0, 6)),
            'status' => 'pending',
            'current_round' => 1,
            'created_by' => $presenter->id,
            'settings' => json_encode(['total_rounds' => 3, 'time_per_question' => 30]),
            'is_accepting_buzzers' => false,
        ]);

        // Create categories with colors and icons
        $categories = [
            ['name' => 'Science', 'color' => '#3b82f6', 'icon' => 'flask'],
            ['name' => 'History', 'color' => '#ef4444', 'icon' => 'landmark'],
            ['name' => 'Sports', 'color' => '#10b981', 'icon' => 'trophy'],
            ['name' => 'Entertainment', 'color' => '#8b5cf6', 'icon' => 'film'],
            ['name' => 'Geography', 'color' => '#f59e0b', 'icon' => 'globe'],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[] = Category::create([
                'name' => $cat['name'],
                'description' => "Questions about {$cat['name']}",
                'color' => $cat['color'],
                'icon' => $cat['icon'],
                'created_by' => $presenter->id,
                'is_active' => true,
            ]);
        }

        // Create questions for each category
        $questionsData = [
            'Science' => [
                ['question_text' => 'What is the chemical symbol for water?', 'correct_answer' => 'H2O', 'points' => 100],
                ['question_text' => 'What planet is known as the Red Planet?', 'correct_answer' => 'Mars', 'points' => 200],
            ],
            'History' => [
                ['question_text' => 'In which year did World War II end?', 'correct_answer' => '1945', 'points' => 100],
                ['question_text' => 'Who was the first president of the United States?', 'correct_answer' => 'George Washington', 'points' => 200],
            ],
            'Sports' => [
                ['question_text' => 'Which country won the FIFA World Cup in 2018?', 'correct_answer' => 'France', 'points' => 100],
                ['question_text' => 'How many players are on a basketball team?', 'correct_answer' => '5', 'points' => 200],
            ],
            'Entertainment' => [
                ['question_text' => 'Who played Iron Man in the Marvel Cinematic Universe?', 'correct_answer' => 'Robert Downey Jr.', 'points' => 100],
                ['question_text' => 'What is the highest-grossing film of all time?', 'correct_answer' => 'Avatar', 'points' => 200],
            ],
            'Geography' => [
                ['question_text' => 'What is the capital of Japan?', 'correct_answer' => 'Tokyo', 'points' => 100],
                ['question_text' => 'Which river is the longest in the world?', 'correct_answer' => 'Nile', 'points' => 200],
            ],
        ];

        foreach ($questionsData as $categoryName => $questions) {
            $category = Category::where('name', $categoryName)->first();
            foreach ($questions as $q) {
                Question::create([
                    'category_id' => $category->id,
                    'question_text' => $q['question_text'],
                    'type' => 'open',
                    'correct_answer' => $q['correct_answer'],
                    'points' => $q['points'],
                    'time_limit' => 30,
                    'difficulty' => 'easy',
                    'created_by' => $presenter->id,
                    'is_active' => true,
                ]);
            }
        }

        // Create teams
        $teams = ['Team Alpha', 'Team Beta', 'Team Gamma'];
        $teamModels = [];
        foreach ($teams as $teamName) {
            $team = Team::create([
                'game_id' => $game->id,
                'name' => $teamName,
                'color' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)),
                'score' => 0,
            ]);
            $teamModels[] = $team;
        }

        // Create player users and assign to teams
        $playerNames = ['Player One', 'Player Two', 'Player Three', 'Player Four', 'Player Five', 'Player Six'];
        $playerIndex = 0;
        foreach ($teamModels as $team) {
            for ($i = 0; $i < 2; $i++) { // 2 players per team
                if (isset($playerNames[$playerIndex])) {
                    User::create([
                        'name' => $playerNames[$playerIndex],
                        'email' => strtolower(str_replace(' ', '', $playerNames[$playerIndex])).'@trivia.com',
                        'password' => Hash::make('password'),
                        'role' => 'player',
                        'is_active' => true,
                        'team_id' => $team->id,
                    ]);
                    $playerIndex++;
                }
            }
        }

        // Assign some questions to the game (game_question pivot)
        $questions = Question::inRandomOrder()->limit(5)->get();
        foreach ($questions as $question) {
            $game->questions()->attach($question->id, [
                'order' => rand(1, 10),
                'status' => 'pending',
            ]);
        }

        // Output credentials
        $this->command->info('Trivia seeding completed!');
        $this->command->info('Admin: admin@trivia.com / password');
        $this->command->info('Presenter: presenter@trivia.com / password');
        $this->command->info('Players: playerX@trivia.com / password');
        $this->command->info('Game Code: '.$game->code);
    }
}
