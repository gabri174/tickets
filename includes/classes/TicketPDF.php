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
    private $attendeeName;
    private $totalPrice;
    
    public function __construct($event, $tickets, $attendeeName, $totalPrice) {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $this->event = $event;
        $this->tickets = $tickets;
        $this->attendeeName = $attendeeName;
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
            $this->drawTicket($ticket);
        }
        return $this->Output('tickets_' . date('Ymd_His') . '.pdf', 'S');
    }
    
    private function drawTicket($ticket) {
        $w = 90; // Ancho del ticket
        $h = 240; // Alto del ticket
        $x = ($this->getPageWidth() - $w) / 2;
        $y = 20;
        
        // Colores
        $colorBg = [252, 251, 247]; // Crema claro
        $colorStub = [235, 207, 148]; // Dorado claro/Beige
        $colorText = [20, 20, 20];
        
        // Fondo principal (Tarjeta con bordes redondeados)
        $this->SetFillColor($colorBg[0], $colorBg[1], $colorBg[2]);
        $this->RoundedRect($x, $y, $w, $h, 5, '1111', 'F');
        
        // 1. IMAGEN DEL EVENTO (Arriba con redondeo)
        $imgAreaH = 100;
        $imgMargin = 5;
        // image_url ya contiene 'uploads/filename.ext'
        $imagePath = ROOT_PATH . '/public/' . ($this->event['image_url'] ?? '');
        
        if (!empty($this->event['image_url']) && file_exists($imagePath)) {
            // Dibujamos la imagen centrada y recortada (aprox)
            $this->Image($imagePath, $x + $imgMargin, $y + $imgMargin, $w - ($imgMargin * 2), $imgAreaH - $imgMargin, '', '', '', false, 300, '', false, false, 0, false, false, false);
        } else {
            // Rectángulo gris si no hay imagen
            $this->SetFillColor(230, 230, 230);
            $this->Rect($x + $imgMargin, $y + $imgMargin, $w - ($imgMargin * 2), $imgAreaH - $imgMargin, 'F');
        }
        
        $currentY = $y + $imgAreaH + 5;
        
        // 2. FECHA (Estilo artístico: Día - Mes - Año)
        $this->SetTextColor($colorText[0], $colorText[1], $colorText[2]);
        $date = strtotime($this->event['date_event']);
        $day = date('d', $date);
        $month = date('m', $date);
        $year = date('Y', $date);
        
        $this->SetFont('helvetica', 'B', 20);
        $this->SetXY($x, $currentY);
        $this->Cell($w/3, 10, $day, 0, 0, 'C');
        
        $this->Line($x + $w/3, $currentY + 2, $x + 2*$w/3, $currentY + 2);
        
        $this->SetXY($x + 2*$w/3, $currentY);
        $this->Cell($w/3, 10, $month, 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 8);
        $this->SetXY($x + 2*$w/3, $currentY + 8);
        $this->Cell($w/3, 5, $year, 0, 1, 'C');
        
        $this->Ln(5);
        $currentY += 15;
        
        // 3. TÍTULO DEL EVENTO
        $this->SetFont('times', 'B', 28);
        $this->SetXY($x + 5, $currentY);
        $this->MultiCell($w - 10, 12, strtoupper($this->event['title']), 0, 'C');
        
        $currentY = $this->GetY() + 10;
        
        // Línea de perforación
        $this->SetLineStyle(array('dash' => '2,2'));
        $this->Line($x, $y + $h - 60, $x + $w, $y + $h - 60);
        $this->SetLineStyle(array('dash' => 0));
        
        // 4. EL "STUB" (PARTE INFERIOR COLOREADA)
        $stubY = $y + $h - 59.5;
        $stubH = 59.5;
        $this->SetFillColor($colorStub[0], $colorStub[1], $colorStub[2]);
        $this->RoundedRect($x, $stubY, $w, $stubH, 5, '0011', 'F');
        
        // Texto Ticket
        $this->SetXY($x + 8, $stubY + 8);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 5, '● TICKET', 0, 1);
        
        // Nombre del asistente
        $this->SetXY($x + 8, $stubY + 15);
        $this->SetFont('helvetica', '', 7);
        $this->MultiCell($w/2, 3, "ESTE TICKET PERTENECE A:\n" . strtoupper($this->attendeeName), 0, 'L');
        
        // QR CODE (Sustituyendo el barcode de la imagen)
        if ($ticket['qr_path'] && file_exists($ticket['qr_path'])) {
            $this->Image($ticket['qr_path'], $x + $w - 35, $stubY + 12, 28, 28, 'PNG');
        }
        
        // Código de acceso texto
        $this->SetXY($x + 8, $stubY + 30);
        $this->SetFont('courier', 'B', 12);
        $this->Cell(0, 10, $ticket['code'], 0, 1);
        
        // Círculos laterales (Efecto de ticket cortado)
        $this->SetFillColor(255, 255, 255);
        $this->Circle($x, $y + $h - 60, 4, 0, 360, 'F');
        $this->Circle($x + $w, $y + $h - 60, 4, 0, 360, 'F');
        
        // Decoración dentada abajo
        $step = 5;
        for ($i = $x; $i < $x + $w; $i += $step) {
            $this->Circle($i + ($step/2), $y + $h, 2, 0, 360, 'F');
        }
    }
}
?>
