<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_presenter_can_run_a_multiple_choice_game_flow_with_dual_timers(): void
    {
        $presenter = User::factory()->create([
            'role' => 'presenter',
            'is_active' => true,
        ]);

        $playerOne = User::factory()->create([
            'role' => 'player',
            'is_active' => true,
        ]);

        $playerTwo = User::factory()->create([
            'role' => 'player',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Ciencia',
            'description' => 'Preguntas de prueba',
            'color' => '#38bdf8',
            'icon' => 'book',
            'created_by' => $presenter->id,
            'is_active' => true,
        ]);

        Question::create([
            'category_id' => $category->id,
            'question_text' => 'Cual es el simbolo quimico del agua?',
            'type' => 'multiple_choice',
            'options' => [
                ['text' => 'H2O', 'is_correct' => true],
                ['text' => 'CO2', 'is_correct' => false],
                ['text' => 'O2', 'is_correct' => false],
                ['text' => 'NaCl', 'is_correct' => false],
            ],
            'correct_answer' => null,
            'points' => 10,
            'time_limit' => 30,
            'difficulty' => 'easy',
            'created_by' => $presenter->id,
            'is_active' => true,
        ]);

        Question::create([
            'category_id' => $category->id,
            'question_text' => 'Que planeta es conocido como el planeta rojo?',
            'type' => 'multiple_choice',
            'options' => [
                ['text' => 'Jupiter', 'is_correct' => false],
                ['text' => 'Saturno', 'is_correct' => false],
                ['text' => 'Marte', 'is_correct' => true],
                ['text' => 'Venus', 'is_correct' => false],
            ],
            'correct_answer' => null,
            'points' => 15,
            'time_limit' => 30,
            'difficulty' => 'easy',
            'created_by' => $presenter->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($presenter);

        $createResponse = $this->postJson('/api/games', [
            'title' => 'Juego de prueba',
            'description' => 'Flujo funcional',
            'max_teams' => 4,
            'rounds_count' => 3,
            'time_per_question' => 5,
            'response_time_limit' => 12,
            'category_ids' => [$category->id],
        ]);

        $createResponse->assertCreated();

        $gameId = $createResponse->json('data.id');
        $gameCode = $createResponse->json('data.code');

        Sanctum::actingAs($playerOne);

        $joinOneResponse = $this->postJson("/api/games/{$gameCode}/join", [
            'team_name' => 'Equipo Alpha',
            'team_color' => '#22c55e',
        ]);

        $joinOneResponse->assertOk();
        $alphaTeamId = $joinOneResponse->json('data.team.id');

        Sanctum::actingAs($playerTwo);

        $joinTwoResponse = $this->postJson("/api/games/{$gameCode}/join", [
            'team_name' => 'Equipo Beta',
            'team_color' => '#38bdf8',
        ]);

        $joinTwoResponse->assertOk();
        $betaTeamId = $joinTwoResponse->json('data.team.id');

        Sanctum::actingAs($presenter);

        $startResponse = $this->postJson("/api/games/{$gameId}/start");
        $startResponse->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.current_question_id', 1)
            ->assertJsonCount(4, 'data.current_question.options');

        $correctOptionKey = $startResponse->json('data.current_question.correct_option_key');

        $this->assertContains($correctOptionKey, ['A', 'B', 'C', 'D']);

        $lobbyBeforeUnlock = $this->getJson("/api/games/{$gameId}/lobby");
        $lobbyBeforeUnlock->assertOk()
            ->assertJsonPath('data.question.current.question_text', 'Cual es el simbolo quimico del agua?')
            ->assertJsonPath('data.buzzer.acceptingBuzzers', false)
            ->assertJsonPath('data.buzzer.responseTimeLimit', 12)
            ->assertJsonCount(4, 'data.question.current.options');

        Sanctum::actingAs($playerOne);

        $this->postJson('/api/buzzer/press', [
            'gameId' => $gameId,
            'teamId' => $alphaTeamId,
        ])->assertStatus(409);

        $this->travel(6)->seconds();

        $pressResponse = $this->postJson('/api/buzzer/press', [
            'gameId' => $gameId,
            'teamId' => $alphaTeamId,
        ]);

        $pressResponse->assertOk()
            ->assertJsonPath('teamId', (string) $alphaTeamId)
            ->assertJsonPath('responseTimeLimit', 12);

        Sanctum::actingAs($presenter);

        $lobbyAfterPress = $this->getJson("/api/games/{$gameId}/lobby");
        $lobbyAfterPress->assertOk()
            ->assertJsonPath('data.buzzer.pressed', true)
            ->assertJsonPath('data.buzzer.answeringTeamId', $alphaTeamId)
            ->assertJsonPath('data.buzzer.responseTimeLimit', 12);

        $this->patchJson("/api/teams/{$alphaTeamId}/score", [
            'delta' => 10,
        ])->assertOk();

        $this->postJson("/api/games/{$gameId}/next-question")
            ->assertOk()
            ->assertJsonPath('data.current_question.question_text', 'Que planeta es conocido como el planeta rojo?')
            ->assertJsonCount(4, 'data.current_question.options');

        $this->assertDatabaseHas('teams', [
            'id' => $alphaTeamId,
            'score' => 10,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $playerOne->id,
            'team_id' => $alphaTeamId,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $playerTwo->id,
            'team_id' => $betaTeamId,
        ]);
    }
}
