<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Router;
use App\Core\ExceptionHandler;

// Register global exception and error handler
ExceptionHandler::register();

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Initialize Router
$router = new Router();

// Define routes
$router->get('/', 'HomeController@index');

// Ingestion routes
$router->get('/ingest', 'IngestController@ingest');
$router->post('/ingest/sync', 'IngestController@ingestWeb');

// Auth routes
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// Dashboard routes
$router->get('/tickets', 'TicketController@index');
$router->get('/tickets/{id}', 'TicketController@show');
$router->post('/tickets/{id}/reply', 'TicketController@sendReply');
$router->post('/tickets/{id}/status', 'TicketController@updateStatus');
$router->post('/tickets/{id}/delete', 'TicketController@deleteTicket');
$router->post('/tickets/{id}/messages/{messageId}/delete', 'TicketController@deleteMessage');

// Settings routes
$router->get('/settings', 'SettingsController@index');
$router->get('/settings/connect', 'SettingsController@connect');
$router->get('/settings/callback', 'SettingsController@callback');

// Dispatch request
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
