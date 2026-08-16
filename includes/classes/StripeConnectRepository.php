<?php

class StripeConnectRepository
{
    private $apiUrl;
    private $apiToken;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) (defined('D1_API_URL') ? D1_API_URL : ''), '/');
        $this->apiToken = (string) (defined('D1_API_TOKEN') ? D1_API_TOKEN : '');
    }

    private function query($sql, array $params = [])
    {
        if ($this->apiUrl === '' || $this->apiToken === '') throw new RuntimeException('Configuración D1 incompleta.');
        $payload = json_encode(['sql'=>$sql,'params'=>array_values($params),'method'=>'first']);
        $ch = curl_init($this->apiUrl . '/api/query');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->apiToken],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $raw=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($raw===false)throw new RuntimeException('No se pudo conectar con D1: '.$error);
        $data=json_decode($raw,true);
        if($status!==200||!is_array($data)||empty($data['success']))throw new RuntimeException(is_array($data)?($data['message']??'Error D1'):'Respuesta D1 inválida');
        return $data['data']['results'][0]??$data['data']??null;
    }

    private function run($sql, array $params = [])
    {
        if ($this->apiUrl === '' || $this->apiToken === '') throw new RuntimeException('Configuración D1 incompleta.');
        $payload=json_encode(['sql'=>$sql,'params'=>array_values($params),'method'=>'run']);
        $ch=curl_init($this->apiUrl.'/api/query');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->apiToken],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $raw=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($raw===false)throw new RuntimeException('No se pudo conectar con D1: '.$error);
        $data=json_decode($raw,true);
        if($status!==200||!is_array($data)||empty($data['success']))throw new RuntimeException(is_array($data)?($data['message']??'Error D1'):'Respuesta D1 inválida');
        return $data['data']??null;
    }

    public function getStripeState($adminId)
    {
        return $this->query('SELECT stripe_account_id,stripe_onboarding_status,stripe_charges_enabled,stripe_payouts_enabled FROM admins WHERE id=?',[(int)$adminId]);
    }

    public function saveStripeAccount($adminId,$accountId,$status,$chargesEnabled,$payoutsEnabled)
    {
        $allowed=['not_started','pending','active','restricted'];
        if(!in_array($status,$allowed,true))$status='pending';
        $this->run('UPDATE admins SET stripe_account_id=?,stripe_onboarding_status=?,stripe_charges_enabled=?,stripe_payouts_enabled=? WHERE id=?',[(string)$accountId,$status,$chargesEnabled?1:0,$payoutsEnabled?1:0,(int)$adminId]);
    }
}
