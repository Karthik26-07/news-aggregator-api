# News Aggregator API - Docker and Manual Setup

## Overview

This project is a **news aggregator API** that collects and stores news data from multiple sources, including:

-   [The Guardian API](https://open-platform.theguardian.com)
-   [NewsData API](https://newsdata.io)
-   [NewsAPI](https://newsapi.org)

The API includes a **scheduler** that fetches news data **every hour**, allowing users to:

-   Set preferences for personalized news
-   Retrieve relevant news feeds
-   Search for articles using filters

## Prerequisites

Ensure your system has the following installed:

-   [Docker](https://www.docker.com/)
-   [Docker Compose](https://docs.docker.com/compose/install/)

## Installation and Setup

Follow these steps to set up and run the **News Aggregator API** inside a **Docker** container:

### 1. Clone the Repository

```sh
git clone https://github.com/Karthik26-07/news-aggregator-api.git
cd news-aggregator-api
```

### 2. Copy the Environment File

```sh
cp .env.example .env
```

**Note**: Update `.env` with your API keys for The Guardian, NewsData, and NewsAPI.

### 3. Configure Database and Credentials

The project uses a Docker test database image for testing purposes. Configure your database settings in the `.env` file or else you can use it for dev too , but it clears data on docker stops:

```
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=news_aggregator
DB_USERNAME=news_user
DB_PASSWORD=news_password

# News API Credentials
GUARDIAN_API_KEY=your_guardian_api_key
NEWSDATA_API_KEY=your_newsdata_api_key
NEWSAPI_API_KEY=your_newsapi_api_key

# Cache and Queue Settings
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

For testing purposes, you can use the included database image without additional configuration. The database will be automatically created when you start the containers.

### 4. Build and Start Docker Containers

```sh
docker-compose up -d --build
```

### 5. Run Database Migrations

```sh
docker exec -it laravel_app php artisan migrate
```

### 6. Access the API

Once running, access the API via:

-   Base URL: http://localhost
-   API Documentation: http://localhost/api/docs-json

### 7. Stop the Containers

To stop and remove the containers, run:

```sh
docker-compose down
```

## Running Tests

The application includes feature tests to verify the functionality of the API endpoints. To run the tests:

### With Docker:

```sh
# Run all feature tests
docker exec -it laravel_app php artisan test --testsuite=Feature

# Run a specific test file
docker exec -it laravel_app php artisan test --filter=NewsApiTest
```

### Without Docker:

```sh
# Run all feature tests
php artisan test --testsuite=Feature

# Run a specific test file
php artisan test --filter=NewsApiTest
```

The test environment uses an in-memory SQLite database by default. Make sure your `.env.testing` file is properly configured with test db credentials:

```
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=news_aggregator
DB_USERNAME=news_user
DB_PASSWORD=news_password

# Test API keys (can be mock values for testing)
GUARDIAN_API_KEY=test_key
NEWSDATA_API_KEY=test_key
NEWSAPI_API_KEY=test_key
```

## Manual Setup (Without Docker)

If you prefer to set up the project without Docker, follow these steps:

### 1. Prerequisites

Ensure you have the following installed on your system:

-   PHP 8.2 or higher
-   Composer
-   MySQL 8
-   Redis (for caching and queues)

### 2. Clone the Repository

```sh
git clone https://github.com/Karthik26-07/news-aggregator-api.git
cd news-aggregator-api
```

### 3. Install PHP Dependencies

```sh
composer install
```

### 4. Copy the Environment File

```sh
cp .env.example .env
```

### 5. Configure Environment Variables

Edit the `.env` file and update the following variables:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=news_aggregator
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password

# News API Credentials
GUARDIAN_API_KEY=your_guardian_api_key
NEWSDATA_API_KEY=your_newsdata_api_key
NEWSAPI_API_KEY=your_newsapi_api_key

# Cache and Queue Settings
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 6. Generate Application Key

```sh
php artisan key:generate
```

### 7. Run Migrations and Seed the Database

```sh
php artisan migrate
```

### 9. Start the Development Server

```sh
php artisan serve
```

### 10. Run the Scheduler for News Fetching

Open a new terminal and run:

```sh
php artisan schedule:work
```

### 11. Run Queue Worker (for background jobs)

Open a new terminal and run:

```sh
php artisan queue:work
```

### 12. Access the API

Once running, access the API via:

-   Base URL: http://localhost:8000
-   API Documentation: http://localhost:8000/api/docs-json

## Additional Commands

If you need to restart the containers after stopping:

```sh
docker-compose up -d
```

To check logs:

```sh
docker logs -f laravel_app
```

## Contributing

🚀 Your News Aggregator API is now up and running!
