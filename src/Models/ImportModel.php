<?php
namespace App\Models;

use Exception;

class ImportModel
{
    /**
     * Reads the first line of a CSV file and returns the headers.
     * Assumes comma (,) as the delimiter.
     *
     * @param string $filePath The temporary path to the uploaded CSV file.
     * @return array An array containing the header strings.
     * @throws Exception If the file cannot be opened or is empty.
     */
    public function parseCsvHeaders(string $filePath): array
    {
        // Open the uploaded file for reading
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception("No se pudo abrir el archivo CSV subido.");
        }

        // Read the first line (headers) using fgetcsv for proper CSV parsing
        $headers = fgetcsv($handle, 0, ','); // 0 = max length (no limit), ',' = delimiter
        fclose($handle);

        // Check if headers were read successfully and are not empty
        if ($headers === false || count($headers) === 0 || (count($headers) === 1 && empty($headers[0]))) {
            throw new Exception("El archivo CSV está vacío o la fila de cabeceras no es válida.");
        }

        // Trim whitespace from headers
        return array_map('trim', $headers);
    }

    /**
     * Parsea un archivo CSV completo según un mapeo dado y devuelve los datos estructurados.
     *
     * @param string $filePath Ruta al archivo CSV.
     * @param array $mapping Mapeo ['columna_stockify' => indice_columna_csv].
     * @return array Array de arrays asociativos, cada uno representando una fila.
     * @throws Exception Si hay errores de lectura o formato.
     */
    public function parseCsvDataWithMapping(string $filePath, array $mapping): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception("No se pudo abrir el archivo CSV para procesar.");
        }

        $headers = fgetcsv($handle, 0, ','); // Leer y descartar la fila de cabeceras
        if ($headers === false) {
            fclose($handle);
            throw new Exception("Error al leer la cabecera del archivo CSV.");
        }

        $parsedData = [];
        $rowNumber = 1; // Empezamos a contar desde la primera fila de datos

        while (($rowData = fgetcsv($handle, 0, ',')) !== false) {
            $rowNumber++;
            $newRow = [];
            $isEmptyRow = true; // Para ignorar filas completamente vacías

            // Construimos la nueva fila usando el mapeo
            foreach ($mapping as $stockifyColumn => $csvIndex) {
                // Verificamos si el índice del CSV existe en la fila actual
                if (isset($rowData[$csvIndex])) {
                    $value = trim($rowData[$csvIndex]);
                    $newRow[$stockifyColumn] = $value;
                    if (!empty($value)) {
                        $isEmptyRow = false;
                    }
                } else {
                    // Si el índice mapeado no existe (raro), asignamos null
                    $newRow[$stockifyColumn] = null;
                    // Podrías lanzar un warning aquí si quieres ser estricto
                    // error_log("Advertencia: Índice CSV {$csvIndex} no encontrado en la fila {$rowNumber}");
                }
            }

            // Solo añadimos la fila si no estaba completamente vacía
            if (!$isEmptyRow) {
                $parsedData[] = $newRow;
            }
        }

        fclose($handle);

        if (empty($parsedData)) {
            // Podría ser un archivo solo con cabeceras o filas vacías
            error_log("Advertencia: No se encontraron datos válidos en el archivo CSV después de la cabecera.");
        }

        return $parsedData;
    }
}