# TURNEX

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![Livewire](https://img.shields.io/badge/Livewire-v4-4E56A6?style=for-the-badge&logo=livewire&logoColor=white)](https://livewire.laravel.com/)
[![Flux UI](https://img.shields.io/badge/Flux_UI-v2-6366F1?style=for-the-badge)](https://fluxui.dev/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-v3-8BC0D0?style=for-the-badge&logo=alpinedotjs&logoColor=white)](https://alpinejs.dev/)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Docker](https://img.shields.io/badge/Docker-Containerized-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://www.docker.com/)
[![Nginx](https://img.shields.io/badge/Nginx-Alpine-009639?style=for-the-badge&logo=nginx&logoColor=white)](https://nginx.org/)
[![Vite](https://img.shields.io/badge/Vite-v8-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev/)
[![pnpm](https://img.shields.io/badge/pnpm-v11-F69220?style=for-the-badge&logo=pnpm&logoColor=white)](https://pnpm.io/)
[![Pest](https://img.shields.io/badge/Pest_PHP-v5-F59E0B?style=for-the-badge&logo=pest&logoColor=white)](https://pestphp.com/)
[![PHPStan](https://img.shields.io/badge/Larastan-v3-2F4F4F?style=for-the-badge&logo=php&logoColor=white)](https://phpstan.org/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-336699?style=for-the-badge&logo=gnu&logoColor=white)](LICENSE)

**TURNEX** is a multi-business web platform designed for virtual queue management, self-service ticketing/orders, and real-time product catalogs. Built for speed, scalability, and ease of use, Turnex allows businesses to streamline customer flow and decrease wait times with an interactive, responsive interface.

---

## Prerequisites

Before starting, ensure you have installed on your host machine:

- [Git](https://git-scm.com/)
- [Docker Engine](https://docs.docker.com/engine/install/) (v24.0 or newer)
- [Docker Compose](https://docs.docker.com/compose/) (v2.20 or newer)

> [!NOTE]
> All development, Artisan, and test commands **must** be executed inside the Docker containers. You do not need PHP, Composer, or Node installed on your host machine.

---

## Step-by-Step Installation

### 1. Clone the Repository

```bash
git clone https://github.com/one-centavo/turnex.git
cd turnex
```

### 2. Configure Environment Variables

Duplicate the environment template file:

```bash
cp .env.example .env
```

Ensure your `.env` contains the proper database connection settings for Docker:

```dotenv
APP_NAME=Turnex
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=turnex-db
DB_PORT=3306
DB_DATABASE=turnex_db
DB_USERNAME=turnex_user
DB_PASSWORD=secret
DB_ROOT_PASSWORD=secret_root

MAIL_MAILER=smtp
MAIL_HOST=turnex-mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="no-reply@turnex.local"
MAIL_FROM_NAME="${APP_NAME}"
```

### 3. Configure Docker Development Override

Create the local Docker Compose override file:

```bash
cp docker-compose.override.example.yml docker-compose.override.yml
```

This activates local port mappings (`8080` for web, `5173` for Vite, and `8025` for Mailpit) along with real-time volume mounts.

### 4. Create External Docker Network

The production/base compose file connects to an external network named `proxy-network`. Create it if it doesn't already exist on your system:

```bash
docker network create proxy-network || true
```

### 5. Build and Launch Containers

Start the core application, web server, and database containers in detached mode:

```bash
docker compose up -d --build
```

Verify that the containers are healthy and running:

```bash
docker compose ps
```

### 6. Application Initialization

Run the initialization commands inside the `turnex-app` container:

```bash
# Generate the application encryption key
docker compose exec turnex-app php artisan key:generate

# Run database migrations and seed default data
docker compose exec turnex-app php artisan migrate --seed

# Create storage public symlink
docker compose exec turnex-app php artisan storage:link
```

## Service URLs

Once all containers are up and running, access the following services:

| Service                   | URL                                            | Description                                  |
| ------------------------- | ---------------------------------------------- | -------------------------------------------- |
| **Web Application**       | [http://localhost:8080](http://localhost:8080) | Turnex main web platform                     |
| **Vite Dev Server (HMR)** | [http://localhost:5173](http://localhost:5173) | Frontend hot module replacement server       |
| **Mailpit Web UI**        | [http://localhost:8025](http://localhost:8025) | Local email inbox to inspect outgoing emails |
| **Database**              | `turnex-db:3306`                               | MySQL internal service                       |

---

## License

This project is open-source software licensed under the [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE).
