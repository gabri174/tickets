<?php
if (!class_exists('TCPDF')) {
    $paths = [
        ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        ROOT_PATH . '/vendor/tcpdf/tcpdf.php'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

if (!class_exists('TCPDF')) {
    throw new Exception("Librería TCPDF no encontrada. Por favor ejecuta 'composer install'");
}

class TicketPDF extends TCPDF {
    private $event;
    private $tickets;
    private $totalPrice;
    
    public function __construct($event, $tickets, $totalPrice) {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $this->event = $event;
        $this->tickets = $tickets;
        $this->totalPrice = $totalPrice;
        
        $this->SetCreator('Tickets System');
        $this->SetAuthor('Tickets System');
        $this->SetTitle('Ticket - ' . $event['title']);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(false);
    }
    
    public function generatePDF() {
        foreach ($this->tickets as $ticket) {
            $this->AddPage();
            // El nombre del asistente ahora viene en el array de cada ticket
            $attendeeName = $ticket['name'] ?? 'ASISTENTE';
            $this->drawTicket($ticket, $attendeeName);
        }
        return $this->Output('tickets_' . date('Ymd_His') . '.pdf', 'S');
    }
    
    private function drawTicket($ticket, $attendeeName) {
        $w = 100; // Ancho del ticket
        $h = 190; // Alto del ticket
        $x = ($this->getPageWidth() - $w) / 2;
        $y = 15;
        
        // Colores
        $colorBg = [255, 255, 255]; 
        $colorAccent = [218, 251, 113]; // #DAFB71 (Lime)
        $colorText = [10, 14, 20];
        $colorLabel = [120, 120, 120];
        $colorBorder = [230, 230, 230];
        
        // 0. Sombra/Borde exterior leve
        $this->SetDrawColor($colorBorder[0], $colorBorder[1], $colorBorder[2]);
        $this->SetFillColor($colorBg[0], $colorBg[1], $colorBg[2]);
        $this->RoundedRect($x, $y, $w, $h, 8, '1111', 'DF');
        
        // 1. IMAGEN DEL EVENTO (Arriba)
        $imgAreaH = 65;
        $imagePath = ROOT_PATH . '/public/' . ($this->event['image_url'] ?? '');
        
        if (!empty($this->event['image_url']) && file_exists($imagePath)) {
            // Imagen principal
            $this->Image($imagePath, $x + 1, $y + 1, $w - 2, $imgAreaH, '', '', '', false, 300, '', false, false, 0, 'CT', false, false);
        } else {
            $this->SetFillColor(20, 20, 20);
            $this->Rect($x + 1, $y + 1, $w - 2, $imgAreaH, 'F');
        }

        // Badge "VÁLIDO"
        $this->SetFillColor($colorAccent[0], $colorAccent[1], $colorAccent[2]);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', 'B', 8);
        $this->RoundedRect($x + $w - 25, $y + 5, 20, 6, 3, '1111', 'F');
        $this->SetXY($x + $w - 25, $y + 5);
        $this->Cell(20, 6, 'VÁLIDO', 0, 0, 'C');
        
        $currentY = $y + $imgAreaH + 12;
        
        // 2. TÍTULO DEL EVENTO
        $this->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
        $this->SetFont('helvetica', 'B', 18);
        $this->SetXY($x + 5, $currentY);
        $this->MultiCell($w - 10, 10, $this->event['title'], 0, 'C', false, 1);
        
        $currentY = $this->GetY() + 8;
        
        // 3. GRID DE INFORMACIÓN (2x2)
        $col1X = $x + 12;
        $col2X = $x + ($w / 2) + 5;
        
        // Fila 1: FECHA y HORA
        $this->SetFont('helvetica', 'B', 7);
        $this->SetTextColor($colorLabel[0], $colorLabel[1], $colorLabel[2]);
        $this->SetXY($col1X, $currentY);
        $this->Cell(30, 4, 'FECHA', 0, 0, 'L');
        $this->SetXY($col2X, $currentY);
        $this->Cell(30, 4, 'HORA', 0, 1, 'L');
        
        $currentY += 4;
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
        $this->SetXY($col1X, $currentY);
        $this->Cell(30, 5, formatDate($this->event['date_event'], 'd M, Y'), 0, 0, 'L');
        $this->SetXY($col2X, $currentY);
        $this->Cell(30, 5, '19:30 PM', 0, 1, 'L'); // Mock time
        
        $currentY += 10;
        
        // Fila 2: LUGAR y TIPO
        $this->SetFont('helvetica', 'B', 7);
        $this->SetTextColor($colorLabel[0], $colorLabel[1], $colorLabel[2]);
        $this->SetXY($col1X, $currentY);
        $this->Cell(30, 4, 'LUGAR', 0, 0, 'L');
        $this->SetXY($col2X, $currentY);
        $this->Cell(30, 4, 'TIPO', 0, 1, 'L');
        
        $currentY += 4;
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
        $this->SetXY($col1X, $currentY);
        $this->Cell($w/2 - 15, 5, $this->event['location'], 0, 0, 'L', false, '', 1); // Truncate if long
        $this->SetXY($col2X, $currentY);
        $this->Cell(30, 5, strtoupper($ticket['type_name'] ?? 'General'), 0, 1, 'L');

        $currentY += 15;
        
        // 4. DIVIDER (Dashed Line with Side Circles)
        $dividerY = $currentY;
        $this->SetLineStyle(array('dash' => '1,1', 'color' => $colorBorder));
        $this->Line($x + 5, $dividerY, $x + $w - 5, $dividerY);
        
        // Side cut-outs
        $this->SetFillColor(255, 255, 255); // Color de la página
        $this->SetDrawColor($colorBorder[0], $colorBorder[1], $colorBorder[2]);
        $this->Circle($x, $dividerY, 4, 0, 360, 'F', array('color' => $colorBorder));
        $this->Circle($x + $w, $dividerY, 4, 0, 360, 'F', array('color' => $colorBorder));
        
        $currentY += 8;
        
        // 5. ASISTENTE Y QR
        $this->SetFont('helvetica', 'B', 7);
        $this->SetTextColor($colorLabel[0], $colorLabel[1], $colorLabel[2]);
        $this->SetXY($x, $currentY);
        $this->Cell($w, 4, 'ASISTENTE', 0, 1, 'C');
        
        $currentY += 4;
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
        $this->SetXY($x, $currentY);
        $this->Cell($w, 6, strtoupper($attendeeName), 0, 1, 'C');
        
        $currentY += 5;
        
        // QR CODE
        if ($ticket['qr_path'] && file_exists($ticket['qr_path'])) {
            $qrSize = 40;
            $this->Image($ticket['qr_path'], $x + ($w - $qrSize)/2, $currentY, $qrSize, $qrSize, 'PNG');
            $currentY += $qrSize + 2;
        }
        
        // Ticket Code
        $this->SetFont('courier', '', 8);
        $this->SetTextColor($colorLabel[0], $colorLabel[1], $colorLabel[2]);
        $this->SetXY($x, $currentY);
        $this->Cell($w, 5, $ticket['code'], 0, 1, 'C');
        
        // Footer message
        $this->SetFont('helvetica', 'B', 6);
        $this->SetTextColor($colorAccent[0], $colorAccent[1], $colorAccent[2]);
        $this->SetXY($x, $currentY + 6);
        $this->Cell($w, 4, 'PRESENTA ESTE QR EN LA ENTRADA', 0, 1, 'C');
    }
}
?>
