<?php

class StripeConnectService
{
    private $secretKey;

    public function __construct()
    {
        $this->secretKey = (string) (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '');
        if ($this->secretKey === '') throw new RuntimeException('Stripe no está configurado.');
    }

    private function request($method, $path, array $params = [], $connectedAccount = null)
    {
        $ch = curl_init('https://api.stripe.com/v1' . $path);
        $headers = ['Authorization: Bearer ' . $this->secretKey, 'Content-Type: application/x-www-form-urlencoded'];
        if ($connectedAccount) $headers[] = 'Stripe-Account: ' . (string)$connectedAccount;
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        if (strtoupper($method) !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        $raw=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($raw===false)throw new RuntimeException('No se pudo conectar con Stripe: '.$error);
        $data=json_decode($raw,true);if(!is_array($data))throw new RuntimeException('Respuesta inválida de Stripe.');
        if($status<200||$status>=300)throw new RuntimeException($data['error']['message']??'Error desconocido de Stripe.');
        return $data;
    }

    public function createExpressAccount($email,$country='ES'){return $this->request('POST','/accounts',['type'=>'express','country'=>strtoupper($country),'email'=>(string)$email,'metadata[platform]'=>SITE_NAME]);}
    public function createOnboardingLink($accountId){$base=rtrim(SITE_URL,'/');return $this->request('POST','/account_links',['account'=>(string)$accountId,'refresh_url'=>$base.'/admin/stripe-connect.php?action=onboarding','return_url'=>$base.'/admin/stripe-connect.php?action=return','type'=>'account_onboarding']);}
    public function retrieveAccount($accountId){return $this->request('GET','/accounts/'.rawurlencode((string)$accountId));}
    public function createExpressLoginLink($accountId){return $this->request('POST','/accounts/'.rawurlencode((string)$accountId).'/login_links');}

    public function createCheckoutSession($connectedAccount,$orderId,$successUrl,$cancelUrl,array $lineItems,$customerEmail=null)
    {
        if(!$connectedAccount)throw new RuntimeException('La cuenta Stripe del organizador no está disponible.');
        if(!$lineItems)throw new RuntimeException('No hay artículos para pagar.');
        $params=['mode'=>'payment','success_url'=>(string)$successUrl,'cancel_url'=>(string)$cancelUrl,'client_reference_id'=>(string)$orderId,'metadata[order_id]'=> (string)$orderId];
        if($customerEmail)$params['customer_email']=(string)$customerEmail;
        foreach($lineItems as $index=>$item){$p='line_items['.$index.']';$params[$p.'[quantity]']=(int)$item['quantity'];$params[$p.'[price_data][currency]']=strtolower((string)($item['currency']??'eur'));$params[$p.'[price_data][unit_amount]']=(int)$item['unit_amount'];$params[$p.'[price_data][product_data][name]']=(string)$item['name'];}
        return $this->request('POST','/checkout/sessions',$params,$connectedAccount);
    }

    public function retrieveCheckoutSession($sessionId,$connectedAccount=null){return $this->request('GET','/checkout/sessions/'.rawurlencode((string)$sessionId),[],$connectedAccount);}
    public function refundPaymentIntent($paymentIntentId,$connectedAccount=null){return $this->request('POST','/refunds',['payment_intent'=>(string)$paymentIntentId],$connectedAccount);}

    public function verifyWebhookSignature($payload,$signature,$secret,$tolerance=300)
    {
        if(!$signature||!$secret)throw new RuntimeException('Firma webhook no configurada.');
        $timestamp=null;$signatures=[];
        foreach(explode(',',$signature) as $part){$parts=explode('=',trim($part),2);if(count($parts)!==2)continue;if($parts[0]==='t')$timestamp=(int)$parts[1];if($parts[0]==='v1')$signatures[]=$parts[1];}
        if(!$timestamp||!$signatures||abs(time()-$timestamp)>(int)$tolerance)throw new RuntimeException('Firma webhook de Stripe inválida o caducada.');
        $expected=hash_hmac('sha256',$timestamp.'.'.$payload,$secret);$valid=false;
        foreach($signatures as $candidate){if(hash_equals($expected,$candidate)){$valid=true;break;}}
        if(!$valid)throw new RuntimeException('Firma webhook de Stripe no válida.');
        return true;
    }
}
