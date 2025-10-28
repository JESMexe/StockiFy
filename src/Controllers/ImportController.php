<?php
namespace App\Controllers;

use App\Models\ImportModel;
use Exception;

// Make sure to import Exception

class ImportController
{
    /**
     * Handles the request to get CSV headers from an uploaded file.
     */
    public function getCsvHeaders(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser(); // Security check

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado.']);
            return;
        }

        // --- File Upload Validation ---
        if (empty($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            $errorMessage = $this->getUploadErrorMessage($_FILES['csvFile']['error'] ?? UPLOAD_ERR_NO_FILE);
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            return;
        }

        $fileTmpPath = $_FILES['csvFile']['tmp_name'];
        $fileType = mime_content_type($fileTmpPath);

        // Basic MIME type check (more robust checks can be added)
        if ($fileType !== 'text/csv' && $fileType !== 'text/plain' && $fileType !== 'application/csv') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo inválido. Sube un archivo CSV. (' . $fileType . ')']);
            return;
        }
        // --- End File Validation ---


        try {
            $importModel = new ImportModel();
            $csvHeaders = $importModel->parseCsvHeaders($fileTmpPath);

            echo json_encode([
                'success' => true,
                'csvHeaders' => $csvHeaders,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } finally {
            if (isset($fileTmpPath) && file_exists($fileTmpPath)) {
                @unlink($fileTmpPath);
            }
        }
    }

    /**
     * Helper function to get user-friendly upload error messages.
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo subido excede el tamaño máximo permitido.';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió solo parcialmente.';
            case UPLOAD_ERR_NO_FILE:
                return 'No se subió ningún archivo.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal del servidor.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en el disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la subida del archivo.';
            default:
                return 'Error desconocido durante la subida del archivo.';
        }
    }

    /**
     * Procesa el archivo CSV subido con el mapeo, lo valida (parsea)
     * y guarda los datos parseados en la sesión.
     */
    public function processCsvPreparation(): void
    {
        header('Content-Type: application/json');
        $user = getCurrentUser();

        if (!$user) { /* ... error 401 ... */ return; }

        // Validación básica de archivo y POST data
        if (empty($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) { /* ... error 400 archivo ... */ return; }
        if (empty($_POST['mapping'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No se proporcionó el mapeo de columnas.']);
            return;
        }

        $fileTmpPath = $_FILES['csvFile']['tmp_name'];
        $mappingJson = $_POST['mapping'];
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true'; // Leemos la opción

        // Decodificamos el mapeo JSON
        $mapping = json_decode($mappingJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($mapping)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El formato del mapeo es inválido.']);
            return;
        }

        try {
            $importModel = new ImportModel();
            // Parseamos TODO el archivo según el mapeo
            $parsedData = $importModel->parseCsvDataWithMapping($fileTmpPath, $mapping);

            // Guardamos los datos parseados y el flag de overwrite en la sesión
            // ¡Importante! session_start() debe haberse llamado antes (getCurrentUser lo hace).
            $_SESSION['pending_import_data'] = $parsedData;
            $_SESSION['pending_import_overwrite'] = $overwrite; // Guardamos si se debe sobrescribir

            echo json_encode([
                'success' => true,
                'message' => 'Archivo validado y datos preparados para importar.',
                'rowCount' => count($parsedData) // Informamos cuántas filas se prepararon
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al procesar el archivo CSV: ' . $e->getMessage()]);
            // Limpiamos la sesión por si acaso
            unset($_SESSION['pending_import_data']);
            unset($_SESSION['pending_import_overwrite']);
        }
    }
}