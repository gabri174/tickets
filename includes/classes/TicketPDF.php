<?php
if (!class_exists('TCPDF')) {
    $manualPath = ROOT_PATH . '/vendor/tcpdf/tcpdf.php';
    if (file_exists($manualPath)) {
        require_once $manualPath;
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
        $this->SetTitle('Tickets - ' . $event['title']);
    }
    
    // Header
    public function Header() {
        // Logo o título
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'Tickets System', 0, 1, 'C');
        $this->Ln(10);
    }
    
    // Footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
    
    // Crear página principal
    public function createMainPage() {
        $this->AddPage();
        
        // Título del evento
        $this->SetFont('helvetica', 'B', 24);
        $this->Cell(0, 15, $this->event['title'], 0, 1, 'C');
        $this->Ln(10);
        
        // Información del evento
        $this->SetFont('helvetica', '', 12);
        
        $eventInfo = [
            'Fecha y Hora:' => formatDate($this->event['date_event'], 'd/m/Y H:i'),
            'Lugar:' => $this->event['location'],
            'Comprador:' => $this->attendeeName,
            'Tickets Comprados:' => count($this->tickets),
            'Total Pagado:' => formatCurrency($this->totalPrice)
        ];
        
        foreach ($eventInfo as $label => $value) {
            $this->Cell(50, 10, $label, 0, 0);
            $this->Cell(0, 10, $value, 0, 1);
        }
        
        $this->Ln(15);
        
        // Tabla de tickets
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Códigos de Tickets:', 0, 1);
        $this->Ln(5);
        
        // Encabezado de tabla
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(20, 10, '#', 1, 0, 'C');
        $this->Cell(80, 10, 'Código', 1, 0, 'C');
        $this->Cell(90, 10, 'URL de Verificación', 1, 1, 'C');
        
        // Filas de tickets
        $this->SetFont('helvetica', '', 10);
        foreach ($this->tickets as $index => $ticket) {
            $this->Cell(20, 10, ($index + 1), 1, 0, 'C');
            $this->Cell(80, 10, $ticket['code'], 1, 0, 'C');
            $this->SetFont('helvetica', '', 8);
            $url = SITE_URL . "/ticket.php?code=" . $ticket['code'];
            $this->Cell(90, 10, $url, 1, 1, 'C');
            $this->SetFont('helvetica', '', 10);
        }
        
        $this->Ln(15);
        
        // Instrucciones
        $this->SetFont('helvetica', 'I', 10);
        $instructions = "Instrucciones Importantes:\n" .
                       "• Presenta este documento o los códigos individuales en la entrada del evento.\n" .
                       "• Llega con al menos 30 minutos de antelación.\n" .
                       "• Este ticket es personal e intransferible.\n" .
                       "• Para verificación online, visita: " . SITE_URL . "/ticket.php?code=[CÓDIGO]";
        
        $this->MultiCell(0, 5, $instructions, 0, 'L');
    }
    
    // Crear páginas individuales para cada ticket
    public function createIndividualTickets() {
        foreach ($this->tickets as $ticket) {
            $this->AddPage();
            
            // Título
            $this->SetFont('helvetica', 'B', 18);
            $this->Cell(0, 15, 'Ticket Individual', 0, 1, 'C');
            $this->Ln(5);
            
            // Información del evento
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 10, $this->event['title'], 0, 1, 'C');
            $this->Ln(10);
            
            // Código grande
            $this->SetFont('courier', 'B', 16);
            $this->Cell(0, 15, $ticket['code'], 1, 1, 'C');
            $this->Ln(10);
            
            // QR Code si existe
            if ($ticket['qr_path'] && file_exists($ticket['qr_path'])) {
                $this->Image($ticket['qr_path'], 85, 100, 40, 40, 'PNG');
                $this->Ln(50);
            }
            
            // Información adicional
            $this->SetFont('helvetica', '', 10);
            $this->Cell(50, 8, 'Fecha:', 0, 0);
            $this->Cell(0, 8, formatDate($this->event['date_event']), 0, 1);
            
            $this->Cell(50, 8, 'Lugar:', 0, 0);
            $this->Cell(0, 8, $this->event['location'], 0, 1);
            
            $this->Cell(50, 8, 'Asistente:', 0, 0);
            $this->Cell(0, 8, $this->attendeeName, 0, 1);
            
            $this->Ln(10);
            
            // URL de verificación
            $this->SetFont('helvetica', '', 8);
            $url = SITE_URL . "/ticket.php?code=" . $ticket['code'];
            $this->Cell(0, 5, 'Verificación Online:', 0, 1, 'C');
            $this->SetFont('courier', '', 8);
            $this->MultiCell(0, 5, $url, 0, 'C');
        }
    }
    
    // Generar PDF completo
    public function generatePDF() {
        $this->createMainPage();
        $this->createIndividualTickets();
        return $this->Output('tickets_' . date('Y-m-d_H-i-s') . '.pdf', 'S');
    }
}
?>
