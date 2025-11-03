<?php
// models/StockModel.php

require_once '../AppState.php';
require_once '../DB_Utilities.php';

class StockModel
{
    private $pdo;

    public function __construct()
    {
        try {
            $this->pdo = new PDO("sqlite:" . AppState::$dbRuta);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error de conexión a la BD: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el stock actual de un producto por su ID.
     */
    public function getStockActual(int $id, string $columnaStock): ?int
    {
        $stmt = $this->pdo->prepare("SELECT `{$columnaStock}` FROM DynamicTable WHERE `ID` = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row[$columnaStock] : null;
    }

    /**
     * Actualiza el stock de un producto (suma, resta o reemplaza).
     */
    public function actualizarStock(int $id, int $nuevoValor, string $columnaStock): bool
    {
        $stmt = $this->pdo->prepare("UPDATE DynamicTable SET `{$columnaStock}` = :newValue WHERE `ID` = :id");
        return $stmt->execute([':newValue' => $nuevoValor, ':id' => $id]);
    }

    /**
     * Quita stock automáticamente (usado por otras partes del sistema).
     * Devuelve el nuevo valor de stock.
     */
    public function quitarStockAutomatico(int $id, int $cantidadAQuitar, string $columnaStock): int
    {
        $valorStockActual = $this->getStockActual($id, $columnaStock);

        if ($valorStockActual === null) {
            // El producto no existe
            return -1;
        }

        $nuevoStock = 0;
        if ($valorStockActual < $cantidadAQuitar) {
            $nuevoStock = 0; // Se actualiza a 0 si el stock es insuficiente
        } else {
            $nuevoStock = $valorStockActual - $cantidadAQuitar;
        }

        $this->actualizarStock($id, $nuevoStock, $columnaStock);
        return $nuevoStock;
    }

    /**
     * Busca un elemento en la base de datos y devuelve su ID.
     * Esta función reemplaza la necesidad de DB_Utilities::SeleccionarElemento en el controlador.
     */
    public function findElementoId(string $columnaBuscada, string $palabraBuscada): ?int
    {
        $stmt = $this->pdo->prepare("SELECT `ID` FROM DynamicTable WHERE `{$columnaBuscada}` LIKE :valor LIMIT 1");
        $stmt->execute([':valor' => "%" . $palabraBuscada . "%"]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['ID'] : null;
    }

    /**
     * Agrega la columna 'stock' a la tabla si no existe.
     */
    public function addStockColumn(): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info(DynamicTable)");
        $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columnas as $col) {
            if (strcasecmp($col['name'], 'stock') == 0) {
                return ['success' => false, 'message' => 'La columna Stock ya existe.'];
            }
        }

        $this->pdo->exec("ALTER TABLE DynamicTable ADD COLUMN `stock` INTEGER DEFAULT 0");

        if (method_exists('AppState', 'InicializarColumnas')) {
            AppState::InicializarColumnas();
        }

        return ['success' => true, 'message' => 'Columna Stock agregada exitosamente.'];
    }

    /**
     * Obtiene el nombre de un producto por su ID (para notificaciones).
     */
    public function getNombreProducto(int $id): ?string
    {
        if (empty(AppState::$ColumnaNombre)) {
            return "ID: $id";
        }

        try {
            $stmt = $this->pdo->prepare("SELECT `" . AppState::$ColumnaNombre . "` FROM DynamicTable WHERE `ID` = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row[AppState::$ColumnaNombre] : "ID: $id";
        } catch (PDOException $e) {
            return "ID: $id";
        }
    }
}