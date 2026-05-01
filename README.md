# PHP Application

This project uses Docker for a consistent local development environment.

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) or Docker Engine with Docker Compose

## Setting up the development environment

1. Clone the repository and navigate to the project root.
2. Run the following command to build and start the containers in the background:
   ```bash
   docker-compose up -d
   ```
3. The web application will be accessible at [http://localhost:8080](http://localhost:8080).
4. The MySQL database is exposed on port `3306` with root password `secret` and database `app_db`.

To stop the environment and remove the containers, run:
```bash
docker-compose down
```
