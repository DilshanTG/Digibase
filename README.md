# Digibase

## World's First Open Source Laravel BaaS (Backend as a Service)

**"The Supabase Alternative for Laravel Developers"**

Digibase is a GUI-first Laravel backend with auto APIs, designed to make backend development effortless for Laravel developers.

## Features

- **Visual Model Creator**: Create Laravel models visually without touching code
- **Auto API Generation**: Automatic REST API endpoints for all your models
- **Beautiful Admin Panel**: FilamentPHP-powered admin interface
- **Real-time Capabilities**: Built-in support for real-time features with Laravel Reverb
- **API Documentation**: Auto-generated API docs with Scramble
- **Role-Based Access Control**: Spatie Permission integration

## Tech Stack

### Backend
- Laravel 11
- Laravel Sanctum (API Authentication)
- Laravel Orion (Auto REST API)
- FilamentPHP v3 (Admin Panel)
- Scramble (API Documentation)
- Spatie Laravel Permission (Roles & Permissions)

### Frontend
- React 18
- Vite (Build Tool)
- TailwindCSS (Styling)
- React Router v6 (Routing)
- Axios (HTTP Client)
- TanStack Query (Data Fetching)
- Heroicons (Icons)

## Project Structure

```
digibase/
├── backend/     (Laravel API)
└── frontend/    (React Admin Panel)
```

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- pnpm (or npm/yarn)
- MySQL/SQLite

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/DilshanTG/Digibase.git
   cd Digibase
   ```

2. **Backend Setup**
   ```bash
   cd backend
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```

3. **Frontend Setup**
   ```bash
   cd frontend
   pnpm install
   ```

### Running the Application

1. **Start the Backend**
   ```bash
   cd backend
   php artisan serve
   ```
   Backend will be available at `http://localhost:8000`

2. **Start the Frontend**
   ```bash
   cd frontend
   pnpm dev
   ```
   Frontend will be available at `http://localhost:5173`

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | Register a new user |
| POST | `/api/login` | Login and get access token |
| POST | `/api/logout` | Logout (requires auth) |
| GET | `/api/user` | Get authenticated user (requires auth) |
| POST | `/api/forgot-password` | Send password reset link |
| POST | `/api/reset-password` | Reset password |

### API Documentation

Visit `/docs/api` on the backend for auto-generated API documentation.

## Admin Panel

Access the FilamentPHP admin panel at `/admin` on the backend.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the MIT license.

## Support

For support, please open an issue on GitHub or join our community.
