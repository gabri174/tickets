export interface Env {
	DB: D1Database;
	D1_API_TOKEN: string;
	PAYMENT_FULFILL_TOKEN: string;
}

function json(data: unknown, status = 200): Response { return Response.json(data, { status }); }
function ticketCode(): string { return crypto.randomUUID().replace(/-/g, '').slice(0, 20).toUpperCase(); }

export default {
	async fetch(request: Request, env: Env): Promise<Response> {
		const url = new URL(request.url);
		const pathname = url.pathname.replace(/\/+$/, "") || "/";
		const authHeader = request.headers.get("Authorization");
		const isPublicScan = pathname === "/api/validate" && request.method === "POST";
		const isVisual = pathname === "/" && request.method === "GET";
		const isFulfill = pathname === "/api/payment/fulfill" && request.method === "POST";

		if (isFulfill) {
			if (!authHeader || authHeader !== `Bearer ${env.PAYMENT_FULFILL_TOKEN}`) return json({ success:false, message:"No autorizado" },401);
			try {
				const body=await request.json() as {order_id?:number;event_id?:number;attendee_name?:string;attendee_email?:string;attendee_phone?:string;items?:Array<{ticket_type_id?:number|null;quantity:number}>};
				const orderId=Number(body.order_id||0), eventId=Number(body.event_id||0), name=String(body.attendee_name||"").trim(), email=String(body.attendee_email||"").trim().toLowerCase(), phone=String(body.attendee_phone||"").trim(), items=Array.isArray(body.items)?body.items:[];
				if(!orderId||!eventId||!name||!email||!items.length)return json({success:false,message:"Datos de fulfillment incompletos"},400);
				const order=await env.DB.prepare("SELECT id,status,inventory_reserved FROM payment_orders WHERE id=?").bind(orderId).first<{id:number;status:string;inventory_reserved:number}>();
				if(!order)return json({success:false,message:"Pedido no encontrado"},404);
				if(order.status==="fulfilled")return json({success:true,already_fulfilled:true});
				if(order.status!=="fulfilling"&&order.status!=="paid")return json({success:false,message:"El pedido no está listo para fulfillment"},409);
				if(Number(order.inventory_reserved)!==1)return json({success:false,message:"El inventario del pedido no está reservado"},409);
				const existing=await env.DB.prepare("SELECT COUNT(*) AS count FROM tickets WHERE payment_order_id=?").bind(orderId).first<{count:number}>();
				if(Number(existing?.count||0)>0){await env.DB.prepare("UPDATE payment_orders SET status='fulfilled',inventory_reserved=0,fulfillment_error=NULL WHERE id=?").bind(orderId).run();return json({success:true,already_fulfilled:true});}

				const statements:D1PreparedStatement[]=[]; let ticketCount=0;
				for(const item of items){const quantity=Math.floor(Number(item.quantity||0));if(quantity<1||quantity>100)return json({success:false,message:"Cantidad inválida"},400);const typeId=item.ticket_type_id?Number(item.ticket_type_id):null;ticketCount+=quantity;for(let i=0;i<quantity;i++)statements.push(env.DB.prepare("INSERT INTO tickets (event_id,ticket_type_id,ticket_code,attendee_name,attendee_email,attendee_phone,qr_code_path,payment_order_id) VALUES (?,?,?,?,?,?,?,?)").bind(eventId,typeId,ticketCode(),name,email,phone||null,null,orderId));}
				statements.push(env.DB.prepare("UPDATE payment_orders SET status='fulfilled',inventory_reserved=0,fulfillment_error=NULL,paid_at=COALESCE(paid_at,CURRENT_TIMESTAMP) WHERE id=? AND status='fulfilling' AND inventory_reserved=1").bind(orderId));
				await env.DB.batch(statements);
				return json({success:true,ticket_count:ticketCount});
			} catch(error) { return json({success:false,message:error instanceof Error?error.message:"Error de fulfillment"},500); }
		}

		if(!isPublicScan&&!isVisual){if(!authHeader||authHeader!==`Bearer ${env.D1_API_TOKEN}`)return json({success:false,message:"No autorizado"},401);}
		if(pathname==="/api/query"&&request.method==="POST"){
			try{const body:unknown=await request.json();if(!body||typeof body!=="object")return json({success:false,message:"Payload inválido"},400);const {sql,params,method}=body as {sql?:unknown;params?:unknown;method?:unknown};if(typeof sql!=="string"||sql.trim()==="")return json({success:false,message:"SQL faltante"},400);const statement=env.DB.prepare(sql).bind(...(Array.isArray(params)?params:[]));if(method==="run")return json({success:true,data:await statement.run()});if(method==="first")return json({success:true,data:await statement.first()});const {results,meta}=await statement.all();return json({success:true,data:{results,meta}});}catch{return json({success:false,message:"Error interno del servidor"},500);}
		}
		if(pathname==="/api/validate"&&request.method==="POST"){
			try{const body=await request.json() as {ticket_code?:unknown};const rawCode=typeof body.ticket_code==="string"?body.ticket_code.trim():"";if(!rawCode)return json({success:false,message:"Código de ticket requerido"},400);let code=rawCode;try{const parsed=new URL(rawCode);code=parsed.searchParams.get("code")||rawCode;}catch{if(rawCode.includes("code="))code=rawCode.split("code=").pop()?.split("&")[0]||rawCode;else if(rawCode.includes("/"))code=rawCode.split("/").pop()?.trim()||rawCode;}code=code.trim();const ticket=await env.DB.prepare("SELECT id,ticket_code,attendee_name,status FROM tickets WHERE ticket_code=?").bind(code).first<{id:number;ticket_code:string;attendee_name:string;status:string}>();if(!ticket)return json({success:false,message:"Ticket no encontrado"},404);if(ticket.status==="used")return json({success:false,message:"¡YA USADO!"},409);if(ticket.status!=="valid")return json({success:false,message:"Ticket no válido"},409);const result=await env.DB.prepare("UPDATE tickets SET status='used' WHERE ticket_code=? AND status='valid'").bind(code).run();if(result.meta.changes!==1)return json({success:false,message:"Ticket ya utilizado"},409);return json({success:true,message:"¡BIENVENIDO! "+ticket.attendee_name});}catch{return json({success:false,message:"Error al validar el ticket"},500);}
		}
		if(pathname==="/")return new Response(`<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Portería Cloudflare</title><script src="https://unpkg.com/html5-qrcode"></script></head><body><h2>🎟️ Portería Digital</h2><div id="reader"></div><div id="result"></div><script>const q=new Html5Qrcode("reader");function s(t){q.pause(true);fetch("/api/validate",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({ticket_code:t})}).then(r=>r.json()).then(d=>{const x=document.getElementById("result");x.textContent=d.message;x.style.display="block";setTimeout(()=>{x.style.display="none";q.resume()},3000)}).catch(()=>q.resume())}q.start({facingMode:"environment"},{fps:15,qrbox:250},s);</script></body></html>`,{headers:{"Content-Type":"text/html; charset=UTF-8"}});
		return json({success:false,message:"Not Found"},404);
	},
};
