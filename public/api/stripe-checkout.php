<?php
require_once '../../includes/config/config.php';
require_once '../../includes/functions/functions.php';
require_once '../../includes/classes/Database.php';
require_once '../../includes/classes/StripeConnectRepository.php';
require_once '../../includes/classes/StripeConnectService.php';
require_once '../../includes/classes/PaymentOrderRepository.php';
header('Content-Type: application/json; charset=UTF-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['success'=>false,'message'=>'Método no permitido.']);exit;}
$orderId=0;$eventId=0;$ticketTypeId=0;$quantity=0;$reserved=false;
try{
 if(!verify_csrf_token($_POST['csrf_token']??''))throw new RuntimeException('Token CSRF inválido.');
 $eventId=(int)($_POST['event_id']??0);$ticketTypeId=(int)($_POST['ticket_type_id']??0);$quantity=(int)($_POST['quantity']??0);$name=trim((string)($_POST['attendee_name']??''));$email=mb_strtolower(trim((string)($_POST['attendee_email']??'')));$phone=trim((string)($_POST['attendee_phone']??''));
 if($eventId<1||$quantity<1||$quantity>20||$name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Datos de compra inválidos.');
 $db=new Database();$event=$db->getEventById($eventId);if(!$event||($event['status']??'')!=='active')throw new RuntimeException('El evento no está disponible.');
 $price=(float)($event['price']??0);$itemName=(string)$event['title'];$selectedType=null;
 if($ticketTypeId>0){$selectedType=$db->getTicketTypeById($ticketTypeId);if(!$selectedType||(int)$selectedType['event_id']!==$eventId)throw new RuntimeException('Tipo de entrada inválido.');$price=(float)$selectedType['price'];$itemName.=' — '.(string)$selectedType['name'];}
 if($price<=0)throw new RuntimeException($price<0?'Precio inválido.':'Este evento no requiere pago con Stripe.');
 $stripeRepo=new StripeConnectRepository();$stripeState=$stripeRepo->getStripeState((int)$event['admin_id']);$stripeAccountId=(string)($stripeState['stripe_account_id']??'');if($stripeAccountId===''||empty($stripeState['stripe_charges_enabled'])||empty($stripeState['stripe_payouts_enabled']))throw new RuntimeException('El organizador todavía no tiene Stripe habilitado para recibir pagos.');
 $amountCents=(int)round($price*100)*$quantity;$token=bin2hex(random_bytes(24));$orders=new PaymentOrderRepository();$orderId=$orders->createOrder($token,$eventId,(int)$event['admin_id'],$name,$email,$phone,$amountCents,'eur',$stripeAccountId);if($orderId<1)throw new RuntimeException('No se pudo crear el pedido.');$orders->addItem($orderId,$selectedType?(int)$selectedType['id']:null,$quantity,(int)round($price*100));
 if(!$orders->reserveInventory($orderId,$eventId,$selectedType?(int)$selectedType['id']:null,$quantity)){$orders->markFailed($orderId,'Inventario insuficiente');throw new RuntimeException('No hay suficientes entradas disponibles.');}$reserved=true;
 try{$stripe=new StripeConnectService();$base=rtrim(SITE_URL,'/');$session=$stripe->createCheckoutSession($stripeAccountId,$orderId,$base.'/payment/success.php?order='.urlencode($token),$base.'/payment/cancel.php?order='.urlencode($token),[['quantity'=>$quantity,'unit_amount'=>(int)round($price*100),'currency'=>'eur','name'=>$itemName]],$email);if(empty($session['id'])||empty($session['url']))throw new RuntimeException('Stripe no devolvió una sesión de checkout válida.');$updated=$orders->markCheckoutCreated($orderId,$session['id'],$session['payment_intent']??null);if((int)($updated['meta']['changes']??0)!==1)throw new RuntimeException('No se pudo confirmar el pedido de checkout.');$reserved=false;}
 catch(Throwable $stripeError){if($reserved)$orders->releaseInventory($orderId,$eventId,$selectedType?(int)$selectedType['id']:null,$quantity);$orders->markFailed($orderId,$stripeError->getMessage());throw $stripeError;}
 echo json_encode(['success'=>true,'checkout_url'=>$session['url'],'order_token'=>$token]);
}catch(Throwable $e){qLog('[ERROR] Stripe checkout: '.$e->getMessage());http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
