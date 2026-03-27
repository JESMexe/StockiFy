<?php
namespace App\core;

use PDO;
use PDOException;
use Dotenv\Dotenv;

class Database
{
    private static $instance = null;
    private $conn;

    private function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $host = $_ENV['DB_HOST'];
        $db   = $_ENV['DB_DATABASE'];
        $user = $_ENV['DB_USERNAME'];
        $pass = $_ENV['DB_PASSWORD'];
        $charset = $_ENV['DB_CHARSET'];

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->conn = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Nunca mostrar errores detallados en produccion
            throw new PDOException("Error de conexión a la base de datos.", (int)$e->getCode());
        }
    }

    /**
     * falta implementar el patrón singleton para asegurar una única conexión.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }

    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
}