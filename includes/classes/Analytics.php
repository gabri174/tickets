<?php
/**
 * Clase de Analíticas - Adaptada para Cloudflare D1 (v2.0)
 */
class Analytics {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtener Funnel de Conversión para un evento o todos los del organizador
     */
    public function getConversionFunnel($organizerId, $eventId = null) {
        $params = [$organizerId];
        $eventFilter = "";
        
        if ($eventId) {
            $eventFilter = " AND e.id = ?";
            $params[] = $eventId;
        }

        // 1. Visitas (Unique sessions)
        $sqlVisits = "SELECT COUNT(DISTINCT ev.session_id) as total 
                      FROM event_visits ev 
                      JOIN events e ON ev.event_id = e.id 
                      WHERE e.admin_id = ?" . $eventFilter;
        
        $resVisits = $this->db->query($sqlVisits, $params);
        $visits = $resVisits[0]['total'] ?? 0;

        // 2. Tickets Pagados (Status 'valid')
        $sqlPaid = "SELECT COUNT(*) as total 
                    FROM tickets t 
                    JOIN events e ON t.event_id = e.id 
                    WHERE e.admin_id = ? AND t.status = 'valid'" . $eventFilter;
        
        $resPaid = $this->db->query($sqlPaid, $params);
        $paid = $resPaid[0]['total'] ?? 0;

        return [
            'visits' => (int)$visits,
            'paid' => (int)$paid,
            'rate' => $visits > 0 ? round(($paid / $visits) * 100, 2) : 0
        ];
    }

    /**
     * Atribución de Ventas por Afiliado (referral)
     */
    public function getSalesAttribution($organizerId, $eventId = null) {
        $params = [$organizerId];
        $eventFilter = "";
        
        if ($eventId) {
            $eventFilter = " AND e.id = ?";
            $params[] = $eventId;
        }

        $sql = "SELECT t.referral as source, COUNT(*) as sales, SUM(tt.price) as revenue
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                WHERE e.admin_id = ? AND t.status = 'valid' " . $eventFilter . "
                GROUP BY t.referral
                ORDER BY sales DESC";
        
        return $this->db->query($sql, $params);
    }

    /**
     * Geolocalización por Código Postal
     */
    public function getGeolocalization($organizerId, $eventId = null) {
        $params = [$organizerId];
        $eventFilter = "";
        
        if ($eventId) {
            $eventFilter = " AND e.id = ?";
            $params[] = $eventId;
        }

        $sql = "SELECT t.zip_code, COUNT(*) as total
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                WHERE e.admin_id = ? AND t.status = 'valid' " . $eventFilter . "
                AND t.zip_code IS NOT NULL
                GROUP BY t.zip_code
                ORDER BY total DESC";
        
        return $this->db->query($sql, $params);
    }

    /**
     * Análisis de Recurrencia
     */
    public function getRecurrenceData($organizerId) {
        $sql = "SELECT attendee_email, COUNT(*) as appearances
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                WHERE e.admin_id = ? AND t.status = 'valid'
                GROUP BY attendee_email
                HAVING appearances > 1
                ORDER BY appearances DESC";
        
        return $this->db->query($sql, [$organizerId]);
    }

    /**
     * Predicción de Sold Out
     */
    public function getSoldOutPrediction($eventId) {
        // Obtenemos tickets vendidos en las últimas 24h vs total disponible
        $sql = "SELECT 
                    e.available_tickets,
                    (SELECT COUNT(*) FROM tickets WHERE event_id = e.id AND purchase_date >= datetime('now', '-1 day')) as sales_last_24h
                FROM events e
                WHERE e.id = ?";
        
        $data = $this->db->query($sql, [$eventId], 'first');

        if (!$data || $data['sales_last_24h'] == 0) return "Estable";

        $daysRemaining = $data['available_tickets'] / $data['sales_last_24h'];
        
        if ($daysRemaining < 1) return "Crítico (< 24h)";
        if ($daysRemaining < 3) return "Inminente (< 3 días)";
        if ($daysRemaining < 7) return "Próximo (< 1 semana)";
        
        return "Normal";
    }
}
