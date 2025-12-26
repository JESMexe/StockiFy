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
        if (!file_exists($filePath)) {
            throw new Exception("El archivo no existe.");
        }
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception("No se pudo abrir el archivo CSV.");
        }

        // Leemos la primera línea para sacar headers
        // Usamos str_getcsv para mejor control si fuera necesario, pero fgetcsv es estándar
        $headers = fgetcsv($handle, 0, ',');
        fclose($handle);

        if ($headers === false || count($headers) === 0) {
            throw new Exception("El archivo CSV parece estar vacío o dañado.");
        }

        // Limpiamos caracteres extraños (BOM, espacios invisibles) que rompen la comparación
        return array_map(function($h) {
            return trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h));
        }, $headers);
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

        $headers = fgetcsv($handle, 0, ',');
        if ($headers === false) {
            fclose($handle);
            throw new Exception("Error al leer la cabecera del archivo CSV.");
        }

        $headerMap = [];
        foreach ($headers as $index => $name) {
            $cleanName = trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $name));
            $headerMap[strtolower($cleanName)] = $index;
        }

        $parsedData = [];

        while (($rowData = fgetcsv($handle, 0, ',')) !== false) {
            if (count($rowData) === 1 && $rowData[0] === null) continue;

            $newRow = [];
            $hasData = false;

            foreach ($mapping as $dbCol => $csvColName) {
                // CASO A: EL USUARIO ELIGIÓ "VACIAR DATOS"
                if ($csvColName === '__EMPTY__') {
                    // Heurística simple: si parece numérico, ponemos 0, si no 'N/A'
                    // Esto asegura que el campo exista en $newRow y sobreescriba el viejo en el merge
                    $isNumericCol = in_array(strtolower($dbCol), ['stock', 'min_stock', 'sale_price', 'receipt_price', 'hard_gain', 'percentage_gain']);
                    $newRow[$dbCol] = $isNumericCol ? 0 : 'N/A';
                    $hasData = true; // Consideramos que esta fila "tiene cambios"
                }
                // CASO B: MAPEADO A COLUMNA CSV
                else {
                    $lookupName = strtolower(trim($csvColName));

                    // Buscamos el índice numérico usando el mapa
                    if (isset($headerMap[$lookupName])) {
                        $index = $headerMap[$lookupName];
                        $val = isset($rowData[$index]) ? trim($rowData[$index]) : null;

                        // Convertimos vacíos a NULL para base de datos
                        if ($val === '') $val = null;

                        $newRow[$dbCol] = $val;
                        $hasData = true;
                    } else {
                        // Si no encontramos la columna por nombre (raro si el header venía de ahí), ignoramos
                    }
                }
            }

            if ($hasData) {
                $parsedData[] = $newRow;
            }
        }

        fclose($handle);
        return $parsedData;
    }
}