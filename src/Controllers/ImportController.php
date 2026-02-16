<?php
namespace App\Controllers;

use App\core\Database;
use Exception;

class ImportController
{
    // --- HELPERS INTELIGENTES ---

    public function detectDelimiter($filePath): string
    {
        $handle = fopen($filePath, "r");
        if (!$handle) return ',';
        $firstLine = fgets($handle);
        fclose($handle);
        if ($firstLine === false) return ',';
        return (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    }

    public function convertToUtf8($content)
    {
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            return mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        return $content;
    }

    /**
     * Ejecuta la importación final leyendo los datos preparados en sesión.
     */
    public function finalizeImport(): array
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // 1. Recuperar datos limpios de la sesión
        $data = $_SESSION['pending_import_data'] ?? [];
        if (empty($data)) {
            throw new Exception("No hay datos preparados para importar. Intenta subir el archivo nuevamente.");
        }

        // 2. Obtener tabla destino
        $inventoryId = $_SESSION['active_inventory_id'] ?? null;
        if (!$inventoryId) throw new Exception("No hay un inventario seleccionado.");

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $stmt->execute([$inventoryId]);
        $tableName = $stmt->fetchColumn();

        if (!$tableName) throw new Exception("No se encontró la tabla de base de datos asociada a este inventario.");

        // 3. Insertar usando TableModel (que maneja transacciones y seguridad)
        $tableModel = new \App\Models\TableModel();

        // Por defecto no sobrescribimos (append), salvo que quieras agregar esa opción en el futuro
        $overwrite = false;

        $insertedCount = $tableModel->bulkInsertData($tableName, $data, $overwrite);

        // 4. Limpieza
        unset($_SESSION['pending_import_data']);

        return [
            'success' => true,
            'message' => "Se importaron {$insertedCount} productos correctamente."
        ];
    }

    public function sanitizeColumnName($name): string
    {
        $name = html_entity_decode(trim($name));
        $name = mb_strtolower($name, 'UTF-8');
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u'
        ];
        $name = strtr($name, $replacements);
        $name = str_replace([' ', '-'], '_', $name);
        $name = preg_replace('/[^a-z0-9_]/', '', $name);
        return trim($name, '_') ?: 'col_' . uniqid();
    }

    public function cleanCellValue($value): string
    {
        $value = $this->convertToUtf8($value);
        // Si tiene etiquetas HTML (típico de Tienda Nube), las quitamos
        if (strpos($value, '<') !== false && strpos($value, '>') !== false) {
            $value = strip_tags(html_entity_decode($value));
        }
        return trim($value);
    }

    public function parseNumber($value): float
    {
        $value = trim($value);
        // Detección de formato europeo (1.200,50) vs americano (1,200.50)
        // Si hay coma y NO hay punto, o si la coma está después del punto (1.200,50)
        if (strpos($value, ',') !== false) {
            $lastComma = strrpos($value, ',');
            $lastPoint = strrpos($value, '.');

            // Si es formato 1.000,00 (coma al final) o solo 100,50
            if ($lastPoint === false || $lastComma > $lastPoint) {
                $value = str_replace('.', '', $value); // Quitar miles
                $value = str_replace(',', '.', $value); // Coma a punto
            }
        }
        // Limpiar cualquier otro caracter raro (monedas $)
        return (float) preg_replace('/[^0-9.-]/', '', $value);
    }

    // --- FUNCIONES PRINCIPALES ---

    public function getCsvHeaders($filePath): array
    {
        if (!file_exists($filePath)) return ['success' => false, 'message' => 'Archivo no encontrado.'];

        $delimiter = $this->detectDelimiter($filePath);
        $handle = fopen($filePath, 'r');
        if (!$handle) return ['success' => false, 'message' => 'No se pudo abrir el archivo.'];

        $firstLine = fgetcsv($handle, 0, $delimiter);
        fclose($handle);

        if (!$firstLine) return ['success' => false, 'message' => 'Archivo vacío o ilegible.'];

        $cleanHeaders = [];
        $uiHeaders = [];

        foreach ($firstLine as $rawHeader) {
            $utf8Header = $this->convertToUtf8($rawHeader);
            $uiHeaders[] = trim($utf8Header); // Para mostrar bonito "Precio de Venta"
            $cleanHeaders[] = $this->sanitizeColumnName($utf8Header); // Para la lógica "precio_de_venta"
        }

        // Evitar duplicados
        $counts = array_count_values($cleanHeaders);
        foreach ($cleanHeaders as $key => $header) {
            if ($counts[$header] > 1) {
                $cleanHeaders[$key] = $header . '_' . ($key + 1);
            }
        }

        return [
            'success' => true,
            'headers' => $cleanHeaders,
            'ui_headers' => $uiHeaders,
            'delimiter' => $delimiter
        ];
    }

    /**
     * ESTA ES LA FUNCIÓN QUE FALTABA Y CAUSABA EL ERROR
     */
    public function processCsvPreparation($postData, $fileData): array
    {
        if (!isset($fileData['csv_file']) || $fileData['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al recibir el archivo.");
        }

        $filePath = $fileData['csv_file']['tmp_name'];

        // 1. Detectar delimitador de nuevo (por seguridad) o usar el enviado
        $delimiter = $postData['delimiter'] ?? $this->detectDelimiter($filePath);

        // 2. Obtener mapeo
        $mapping = json_decode($postData['mapping'] ?? '[]', true);
        if (empty($mapping)) throw new Exception("No se recibieron datos de mapeo.");

        // 3. Leer cabeceras reales del archivo para saber índices
        $handle = fopen($filePath, 'r');
        $fileHeaders = fgetcsv($handle, 0, $delimiter);

        // Crear mapa: NombreSanitizado => ÍndiceColumna (0, 1, 2...)
        $headerMap = [];
        foreach ($fileHeaders as $index => $h) {
            $cleanName = $this->sanitizeColumnName($this->convertToUtf8($h));
            // Guardamos el índice donde está esta columna en el CSV
            // Usamos un array por si hay columnas repetidas, tomamos la primera coincidencia válida
            if (!isset($headerMap[$cleanName])) {
                $headerMap[$cleanName] = $index;
            }
        }

        // 4. Procesar Filas
        $processedData = [];
        $rowCount = 0;

        // Columnas numéricas que requieren limpieza especial
        $numericCols = ['stock', 'min_stock', 'sale_price', 'buy_price', 'cost'];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Ignorar filas vacías
            if (array_filter($row) === []) continue;

            $item = [];
            $hasData = false;

            foreach ($mapping as $sysCol => $csvColName) {
                // $sysCol = 'sale_price', $csvColName = 'precio_promocional' (sanitizado)

                if (empty($csvColName)) continue; // Columna ignorada

                // Buscar el índice numérico de la columna CSV
                $idx = $headerMap[$csvColName] ?? null;

                if ($idx !== null && isset($row[$idx])) {
                    $rawVal = $row[$idx];

                    // Limpieza inteligente
                    $cleanVal = $this->cleanCellValue($rawVal);

                    // Conversión numérica si aplica
                    if (in_array($sysCol, $numericCols)) {
                        $cleanVal = $this->parseNumber($cleanVal);
                    }

                    $item[$sysCol] = $cleanVal;
                    $hasData = true;
                }
            }

            if ($hasData) {
                $processedData[] = $item;
                $rowCount++;
            }
        }
        fclose($handle);

        // 5. Guardar en sesión para el paso final
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['pending_import_data'] = $processedData;

        return [
            'success' => true,
            'message' => "Se han procesado {$rowCount} filas correctamente.",
            'preview' => array_slice($processedData, 0, 5)
        ];
    }
}