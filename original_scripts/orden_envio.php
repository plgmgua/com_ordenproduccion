<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


require('/var/www/grimpsa_webserver/fpdf/fpdf.php');
require('/var/www/grimpsa_webserver/phpqrcode/qrlib.php');

// Seccion para definir variables
$date = date("d/m/Y");
$order_id = $_POST['order_id'] ?? '';
$qr_url = 'https://grimpsa.microservz.com/index.php/envios?order_id=' . ($_POST['order_id'] ?? '');
$cliente = $_POST['cliente'] ?? '';
$cliente = mb_convert_encoding($cliente, 'ISO-8859-1', 'UTF-8');
$agente_ventas = $_POST['agente_ventas'] ?? '';
$contacto = $_POST['contacto'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$direccion = $_POST['entrega_direccion'] ?? '';
$direccion = mb_convert_encoding($direccion, 'ISO-8859-1', 'UTF-8');
$instrucciones = $_POST['instrucciones'] ?? '';
$instrucciones = mb_convert_encoding($instrucciones, 'ISO-8859-1', 'UTF-8');
$envio_tipo = $_POST['envio_tipo'] ?? '';
$envio_descripcion = $_POST['envio_descripcion'] ?? '';
$envio_descripcion = mb_convert_encoding($envio_descripcion, 'ISO-8859-1', 'UTF-8');




// Class definition for generating the PDF
class ReceiptPDF extends FPDF {
    private $qrCodePath;

    function __construct($qrCodePath) {
        parent::__construct();
        $this->qrCodePath = $qrCodePath;
    }

    function Header() {
        // Empty header to customize each page header manually
    }

    function addReceipt( $quantity, $startY = 0) {
        // Explicitly declare global variables to ensure they are accessible
        global $date, $order_id, $qr_url, $cliente, $agente_ventas, $contacto, $telefono, $direccion, $instrucciones, $envio_tipo, $envio_descripcion;

        // Ensure $startY is treated as a float
        $startY = (float)$startY;

        $this->SetY($startY); // Set the starting Y position for the receipt content
        $this->SetFont('Arial', 'B', 14);

        // Logo and QR Code on the same level
        $this->Image('http://grupoimpre.com/images/logotransparency.gif', 10, $startY, 55);

        // Generate QR Code and position it at the same Y level as the logo
        QRcode::png($qr_url, $this->qrCodePath, 'L', 4, 4);
        $this->Image($this->qrCodePath, 159, $startY - 10, 40);

        // Receipt title
        $this->SetY($startY + 10);
        $this->SetFont('Arial', 'B', 26);
        $this->Cell(0, 10, 'Envio # ' . $order_id, 0, 1, 'C');
        
        // Date
        $this->SetY($startY + 20);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, 'GUATEMALA, ' . $date, 0, 1, 'C');

        // Move below QR code area for table
        $this->SetY($startY + 30);

        // Table setup
        $cellHeight = 5;

        // Row 1
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(37, $cellHeight, 'Cliente', 1);
        $this->SetFont('Arial', '', 9);
        $this->Cell(153, $cellHeight, $cliente, 1);
        $this->Ln();
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(37, $cellHeight, 'Agente de Ventas', 1);
        $this->SetFont('Arial', '', 9);
        $this->Cell(153, $cellHeight, $agente_ventas, 1);
        $this->Ln();

        // Row 2
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(37, $cellHeight, 'Contacto', 1);
        $this->SetFont('Arial', '', 9);
        $this->Cell(81, $cellHeight, $contacto, 1);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(32, $cellHeight, 'Telefono', 1);
        $this->SetFont('Arial', '', 9);
        $this->Cell(40, $cellHeight, $telefono, 1);
        $this->Ln();

// Set the cell width and font settings
$cellWidth = 153;
$cellHeight = 6; // Starting height for each line

// Calculate the number of lines needed
$nbLines = $this->GetStringWidth($direccion) > $cellWidth ? ceil($this->GetStringWidth($direccion) / $cellWidth) : 1;
$multiCellHeight = $cellHeight * $nbLines; // Total height for MultiCell

// Row 3 - merged cells for columns 2-4
$this->SetFont('Arial', 'B', 10);
$this->Cell(37, $multiCellHeight, 'Direccion de entrega', 1); // Adjust the left cell to match height

$this->SetFont('Arial', '', 9);
$this->MultiCell($cellWidth, $cellHeight, $direccion, 1, 'J');

// No need for an additional Ln() because MultiCell moves to the next line by itself


        // Row 4 - fully merged with "Instrucciones de entrega" content
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(190, $cellHeight, 'Instrucciones de entrega', 1, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->MultiCell(190, $cellHeight, $instrucciones, 1, 'J');

        // Row 6 - merged cells for columns 2-4
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(37, $cellHeight, 'Tipo de Entrega', 1);
        $this->SetFont('Arial', '', 9);
        $this->Cell(153, $cellHeight, $envio_tipo, 1);
        $this->Ln();

        // Row 7 - merged cells for columns 2-4
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(37, $cellHeight, 'Trabajo', 1);
        $this->SetFont('Arial', '', 9);
        $this->Cell(153, $cellHeight, $envio_descripcion, 1);
        $this->Ln();

        // Row 8 - all cells merged
        $this->Cell(190, $cellHeight * 3, '', 1);
        $this->Ln();

        // Row 9 - all cells merged with light gray background
        $this->SetFillColor(211, 211, 211);
        $this->Cell(190, 2, '', 0, 0, '', true);
        $this->Ln();

        // Row 10 - merged cells for columns 2-3 without borders, centered text
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(45, $cellHeight, 'FECHA', 0, 0, 'C');
        $this->Cell(95, $cellHeight, 'NOMBRE Y FIRMA', 0, 0, 'C');
        $this->Cell(50, $cellHeight, 'Sello', 0, 0, 'C');
        $this->Ln();
    }
}

// Create an instance of ReceiptPDF
$qrCodePath = '/var/www/grimpsa_webserver/temp/qrcode.png';
$pdf = new ReceiptPDF($qrCodePath);

// Add a new page
$pdf->AddPage();



// Add the first receipt at the top half of the page (starting Y position 10)
$pdf->addReceipt('5', 10);

// Add the second receipt on the bottom half of the page (starting around half-page Y-position)
$pdf->addReceipt('5', 160);

// Output the PDF
$pdf->Output('I', 'two_receipts_on_one_page.pdf');
//incio registro para cerrar la orden
$servername = "localhost";
$username = "microservz";
$password = "5Upn..804oF7@OWA";
$dbname = "grimpsa_prod";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// First query to insert a new record
$sql_insert = "INSERT INTO ordenes_info (numero_de_orden, tipo_de_campo, valor)
VALUES ('" . $order_id . "', 'historial', 'cerrada')";

if ($conn->query($sql_insert) === TRUE) {
    echo "New record created successfully";
} else {
    echo "Error: " . $sql_insert . "<br>" . $conn->error;
}

// Second query to update the record
$sql_update = "UPDATE ordenes_info 
SET valor = 'terminada' 
WHERE numero_de_orden = '" . $order_id . "' AND tipo_de_campo = 'estado'";

if ($conn->query($sql_update) === TRUE) {
    echo "Record updated successfully";
} else {
    echo "Error: " . $sql_update . "<br>" . $conn->error;
}

$conn->close();



//fin registro para cerrar la orden
?>
