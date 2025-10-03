<?php
require __DIR__ . '/../../vendor/autoload.php'; // Include PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generate Excel file with query results and pivot table
 *
 * @param array $results Query results to include in the Excel file
 * @param string $startDate Start date in YYYY-MM-DD format
 * @param string $endDate End date in YYYY-MM-DD format
 * @return string File name of the generated Excel file
 * @throws Exception If the file cannot be created
 */
function generateExcelFileWithPivot($results, $startDate, $endDate) {
    $fileName = 'ordenes_' . str_replace('-', '', $startDate) . '_' . str_replace('-', '', $endDate) . '.xlsx';
    $filePath = '/var/www/grimpsa_webserver/excel_tmp/' . $fileName;

    // Create a new spreadsheet
    $spreadsheet = new Spreadsheet();

    // Sheet 1: Raw Data
    $sheet1 = $spreadsheet->setActiveSheetIndex(0);
    $sheet1->setTitle('Raw Data');

    // Add headers
    $sheet1->fromArray(['Date', 'Agent', 'Client', 'Work Order', 'Value'], null, 'A1');
    $sheet1->getStyle('A1:E1')->getFont()->setBold(true);

    // Add data
    $rowIndex = 2;
    foreach ($results as $row) {
        $sheet1->setCellValue("A{$rowIndex}", $row['formatted_date']);
        $sheet1->setCellValue("B{$rowIndex}", $row['agente_de_ventas']);
        $sheet1->setCellValue("C{$rowIndex}", $row['nombre_del_cliente']);
        $sheet1->setCellValue("D{$rowIndex}", $row['orden_de_trabajo']);
        $sheet1->setCellValue("E{$rowIndex}", $row['valor_decimal']);
        $rowIndex++;
    }

    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet1->getColumnDimension($col)->setAutoSize(true);
    }

    // Sheet 2: Pivot Table
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Pivot Table');

    // Add headers
    $sheet2->fromArray(['Fecha', 'Agente de Ventas', 'Cliente', 'Orden #', 'Valor a Facturar', 'Total Agente', 'Total Fecha'], null, 'A1');
    $sheet2->getStyle('A1:G1')->getFont()->setBold(true);

    // Add pivot-style data
    $rowIndex = 2;
    foreach ($results as $row) {
        $sheet2->setCellValue("A{$rowIndex}", $row['formatted_date']);
        $sheet2->setCellValue("B{$rowIndex}", $row['agente_de_ventas']);
        $sheet2->setCellValue("C{$rowIndex}", $row['nombre_del_cliente']);
        $sheet2->setCellValue("D{$rowIndex}", $row['orden_de_trabajo']);
        $sheet2->setCellValue("E{$rowIndex}", $row['valor_decimal']);
        // Add Total Agente and Total Fecha calculations as needed
        $rowIndex++;
    }

    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet2->getColumnDimension($col)->setAutoSize(true);
    }

    // Save the Excel file
    $writer = new Xlsx($spreadsheet);
    try {
        $writer->save($filePath);
        return $fileName;
    } catch (Exception $e) {
        throw new Exception("Error generating Excel file: " . $e->getMessage());
    }
}
