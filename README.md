# Trivia UNITEPC - Backend

Laravel backend for the Trivia UNITEPC interactive quiz system with real-time buzzer functionality, MySQL persistence, and Laravel Reverb WebSockets.

## Features

- Real-time buzzer system with ultra-low latency using Laravel Reverb
- RESTful API with Laravel Sanctum authentication
- Three user roles: Admin, Presenter, and Player/Teams
- MySQL database with comprehensive schema for games, teams, questions, and buzzer presses
- Redis caching and session management
- Event broadcasting for real-time updates
- Docker containerization with orchestration

## Prerequisites

- PHP 8.2+
- Composer
- MySQL 8.0+
- Redis 7+
- Docker and Docker Compose (for containerized deployment)

## Setup

### Local Development

1. Clone this repository
2. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
3. Update environment variables in `.env`:
   - Database credentials
   - Redis configuration
   - Reverb WebSocket settings

4. Install dependencies:
   ```bash
   composer install
   ```

5. Generate application key:
   ```bash
   php artisan key:generate
   ```

6. Run database migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

7. Start Laravel Reverb WebSocket server:
   ```bash
   php artisan reverb:start
   ```

8. Start the development server:
   ```bash
   php artisan serve
   ```

### Docker Deployment

The project includes a `docker-compose.yml` file that orchestrates all services:

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

Services included:
- **MySQL** (port 3307)
- **Redis** (port 6379)
- **Laravel Backend** (port 8000)
- **Laravel Reverb** (port 8080)
- **Frontend** (port 3000) - requires frontend repository
- **phpMyAdmin** (port 8081)

## Database Schema

The system includes 13 tables:
- `users` - User accounts with roles (admin, presenter, player)
- `games` - Game sessions with status and settings
- `teams` - Teams participating in games
- `categories` - Question categories
- `questions` - Trivia questions with points and difficulty
- `buzzer_presses` - Real-time buzzer press records
- `game_rounds` - Game round management
- `team_scores` - Team scoring tracking
- `game_question` - Pivot table for game questions

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login user
- `POST /api/auth/logout` - Logout user
- `GET /api/auth/user` - Get current user

### Games
- `GET /api/games` - List games
- `POST /api/games` - Create game (admin/presenter)
- `GET /api/games/{game}` - Get game details
- `POST /api/games/{game}/join` - Join game
- `POST /api/games/{game}/start` - Start game (admin/presenter)

### Buzzer
- `POST /api/buzzer/press` - Press buzzer (player)
- `POST /api/buzzer/reset` - Reset buzzer (presenter)
- `POST /api/buzzer/lock` - Lock/unlock buzzer (presenter)

### Categories & Questions
- Full CRUD for categories and questions with role-based access

## Real-time Events

The system broadcasts the following events via Laravel Reverb:

- `BuzzerPressed` - When a player presses the buzzer
- `BuzzerReset` - When the presenter resets the buzzer
- `BuzzerLocked` - When the buzzer is locked/unlocked
- `GameStarted` - When a game starts

## Seed Data

The database seeder creates:
- Admin user: `admin@trivia.com` / `password`
- Presenter user: `presenter@trivia.com` / `password`
- 6 player users across 3 teams
- Demo game with code
- 5 categories with 10 questions

## Testing

Run the test suite:

```bash
# Install dev dependencies
composer install --dev

# Run tests
./vendor/bin/pint --test  # Code style check
# PHPUnit tests can be added as needed
```

## Frontend Integration

This backend is designed to work with the [Trivia UNITEPC Frontend](https://github.com/juanjo2702/unicosmos_front.git). The frontend connects to the API and WebSocket endpoints for real-time functionality.

## Environment Variables

Key environment variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_*` | Database connection | - |
| `REDIS_*` | Redis connection | - |
| `REVERB_*` | WebSocket configuration | - |
| `APP_URL` | Application URL | http://localhost:8000 |
| `SANCTUM_STATEFUL_DOMAINS` | Frontend domains for authentication | localhost:3000 |

## License

Proprietary - Universidad Tecnológica de Pereira (UNITEPC)