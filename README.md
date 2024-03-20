# symfony-event-driven-microservice

## Overview

> This application consists of two microservices: users and notifications, which communicate through RabbitMQ, a message broker. The users service exposes a POST /users endpoint to accept user data. Upon receiving data, it stores this data in a database. Then, it publishes an event to RabbitMQ. The notifications service listens for these events on RabbitMQ and upon consuming an event, it stores the received data in a log file.


## System Requirements

+ **Docker:** You need to have Docker installed to run the microservices in containers

## Configuration Steps

+ **Install Docker:** Ensure Docker is installed and running on your system. You can download it from Docker's official website.
+ **Environment Variables:** Set the necessary environment variables for both services. This includes the message broker connection details and, if using a database, the database connection details.
+ **Build Docker Images:** Build the Docker images for both microservices using the Dockerfiles provided in their respective directories.
+ **Run Containers:** Use docker-compose to run the containers.

## Running the Application

## Running the Application

1. Start the application using Docker Compose:

```
docker-compose up

```