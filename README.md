# PHP Application

This project uses Docker for a consistent local development environment.

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) or Docker Engine with Docker Compose

## Setting up the development environment

1. Clone the repository and navigate to the project root.
2. If the `vendor/` directory is not included in the project, install PHP dependencies (Composer) executing the following command using Docker:
   ```bash
   docker-compose run --rm app composer install
3. Run the following command to build and start the containers in the background:
   ```bash
   docker-compose up -d
   ```
4. The web application will be accessible at [http://localhost:8080](http://localhost:8080).
5. The MySQL database is exposed on port `3306` with root password `secret` and database `app_db`.

To stop the environment and remove the containers, run:
```bash
docker-compose down
```
