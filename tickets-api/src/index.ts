export interface Env {
	DB: D1Database;
	D1_API_TOKEN: string;
	PAYMENT_FULFILL_TOKEN: string;
}

function json(data: unknown, status = 200): Response {
	return Response.json(data, { status });
}

function ticketCode(): string {
	return crypto.randomUUID().replace(/-/g, '').slice(0, 20).toUpperCase();
}

export default {
	async fetch(request: Request, env: Env): Promise<Response> {
		const url = new URL(request.url);
		const pathname = url.pathname.replace(/\/+$/, "") || "/";

		const authHeader = request.headers.get("Authorization");
		const isPublicScan = pathname === "/api/validate" && request.method === "POST";
		const isVisual = pathname === "/" && request.method === "GET";
		const isFulfill = pathname === "/api/payment/fulfill" && request.method === "POST";

		if (isFulfill) {
			if (!authHeader || authHeader !== `Bearer ${env.PAYMENT_FULFILL_TOKEN}`) {
				return json({ success: false, message: "No autorizado" }, 401);
			}
			try {
				const body = await request.json() as {
					order_id?: number;
					event_id?: number;
					attendee_name?: string;
					attendee_email?: string;
					attendee_phone?: string;
					items?: Array<{ ticket_type_id?: number | null; quantity: number }>;
				};

				const orderId = Number(body.order_id || 0);
				const eventId = Number(body.event_id || 0);
				const name = String(body.attendee_name || "").trim();
				const email = String(body.attendee_email || "").trim().toLowerCase();
				const phone = String(body.attendee_phone || "").trim();
				const items = Array.isArray(body.items) ? body.items : [];

				if (!orderId || !eventId || !name || !email || !items.length) {
					return json({ success: false, message: "Datos de fulfillment incompletos" }, 400);
				}

				const order = await env.DB.prepare("SELECT id,status FROM payment_orders WHERE id=?")
					.bind(orderId).first<{ id: number; status: string }>();
				if (!order) return json({ success: false, message: "Pedido no encontrado" }, 404);
				if (order.status === "fulfilled") return json({ success: true, already_fulfilled: true });
				if (order.status !== "fulfilling" && order.status !== "paid") {
					return json({ success: false, message: "El pedido no está listo para fulfillment" }, 409);
				}

				const existing = await env.DB.prepare("SELECT COUNT(*) AS count FROM tickets WHERE payment_order_id=?")
					.bind(orderId).first<{ count: number }>();
				if (Number(existing?.count || 0) > 0) {
					await env.DB.prepare("UPDATE payment_orders SET status='fulfilled', fulfillment_error=NULL WHERE id=?").bind(orderId).run();
					return json({ success: true, already_fulfilled: true });
				}

				const statements: D1PreparedStatement[] = [];
				for (const item of items) {
					const quantity = Math.floor(Number(item.quantity || 0));
					if (quantity < 1 || quantity > 100) return json({ success: false, message: "Cantidad inválida" }, 400);
					const typeId = item.ticket_type_id ? Number(item.ticket_type_id) : 0;
					if (typeId) {
						statements.push(env.DB.prepare("UPDATE ticket_types SET available_tickets=available_tickets-? WHERE id=? AND event_id=? AND available_tickets>=?")
							.bind(quantity, typeId, eventId, quantity));
					} else {
						statements.push(env.DB.prepare("UPDATE events SET available_tickets=available_tickets-? WHERE id=? AND available_tickets>=?")
							.bind(quantity, eventId, quantity));
					}
				}

				for (const item of items) {
					const quantity = Math.floor(Number(item.quantity || 0));
					const typeId = item.ticket_type_id ? Number(item.ticket_type_id) : null;
					for (let i = 0; i < quantity; i++) {
						statements.push(env.DB.prepare("INSERT INTO tickets (event_id,ticket_type_id,ticket_code,attendee_name,attendee_email,attendee_phone,qr_code_path,payment_order_id) VALUES (?,?,?,?,?,?,?,?)")
							.bind(eventId, typeId, ticketCode(), name, email, phone || null, null, orderId));
					}
				}

				statements.push(env.DB.prepare("UPDATE payment_orders SET status='fulfilled', fulfillment_error=NULL, paid_at=COALESCE(paid_at,CURRENT_TIMESTAMP) WHERE id=?").bind(orderId));
				await env.DB.batch(statements);
				return json({ success: true, ticket_count: statements.length - items.length - 1 });
			} catch (error) {
				return json({ success: false, message: error instanceof Error ? error.message : "Error de fulfillment" }, 500);
			}
		}

		if (!isPublicScan && !isVisual) {
			if (!authHeader || authHeader !== `Bearer ${env.D1_API_TOKEN}`) {
				return json({ success: false, message: "No autorizado" }, 401);
			}
		}

		if (pathname === "/api/query" && request.method === "POST") {
			try {
				const body: unknown = await request.json();
				if (!body || typeof body !== "object") return json({ success: false, message: "Payload inválido" }, 400);
				const { sql, params, method } = body as { sql?: unknown; params?: unknown; method?: unknown };
				if (typeof sql !== "string" || sql.trim() === "") return json({ success: false, message: "SQL faltante" }, 400);
				const boundParams = Array.isArray(params) ? params : [];
				const statement = env.DB.prepare(sql).bind(...boundParams);
				if (method === "run") return json({ success: true, data: await statement.run() });
				if (method === "first") return json({ success: true, data: await statement.first() });
				const { results, meta } = await statement.all();
				return json({ success: true, data: { results, meta } });
			} catch {
				return json({ success: false, message: "Error interno del servidor" }, 500);
			}
		}

		if (pathname === "/api/validate" && request.method === "POST") {
			try {
				const body = await request.json() as { ticket_code?: unknown };
				const rawCode = typeof body.ticket_code === "string" ? body.ticket_code.trim() : "";
				if (!rawCode) return json({ success: false, message: "Código de ticket requerido" }, 400);
				let code = rawCode;
				try {
					const parsed = new URL(rawCode);
					code = parsed.searchParams.get("code") || rawCode;
				} catch {
					if (rawCode.includes("code=")) code = rawCode.split("code=").pop()?.split("&")[0] || rawCode;
					else if (rawCode.includes("/")) code = rawCode.split("/").pop()?.trim() || rawCode;
				}
				code = code.trim();
				const ticket = await env.DB.prepare("SELECT id,ticket_code,attendee_name,status FROM tickets WHERE ticket_code=?")
					.bind(code).first<{ id: number; ticket_code: string; attendee_name: string; status: string }>();
				if (!ticket) return json({ success: false, message: "Ticket no encontrado" }, 404);
				if (ticket.status === "used") return json({ success: false, message: "¡YA USADO!" }, 409);
				if (ticket.status !== "valid") return json({ success: false, message: "Ticket no válido" }, 409);
				const result = await env.DB.prepare("UPDATE tickets SET status='used' WHERE ticket_code=? AND status='valid'").bind(code).run();
				if (result.meta.changes !== 1) return json({ success: false, message: "Ticket ya utilizado" }, 409);
				return json({ success: true, message: "¡BIENVENIDO! " + ticket.attendee_name });
			} catch {
				return json({ success: false, message: "Error al validar el ticket" }, 500);
			}
		}

		if (pathname === "/") {
			return new Response(`<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Portería Cloudflare</title><script src="https://unpkg.com/html5-qrcode"></script><style>body{font-family:sans-serif;text-align:center;background:#0f172a;color:white;padding:20px}#reader{width:100%;max-width:450px;margin:20px auto;border-radius:15px;overflow:hidden;border:2px solid #334155}#result{margin-top:20px;padding:20px;border-radius:10px;font-weight:bold;font-size:1.5rem;display:none}.success{background:#10b981}.error{background:#ef4444}.info{color:#94a3b8;font-size:.9rem}</style></head><body><h2>🎟️ Portería Digital</h2><p class="info">Sistema 100% en Cloudflare D1</p><div id="reader"></div><div id="result"></div><script>const html5QrCode=new Html5Qrcode("reader");function onScanSuccess(decodedText){html5QrCode.pause(true);fetch("/api/validate",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({ticket_code:decodedText})}).then(res=>res.json()).then(data=>{const dr=document.getElementById("result");dr.style.display="block";dr.innerText=data.message;dr.className=data.success?"success":"error";setTimeout(()=>{dr.style.display="none";html5QrCode.resume()},3000)}).catch(()=>{const dr=document.getElementById("result");dr.style.display="block";dr.innerText="Error de conexión";dr.className="error";setTimeout(()=>{dr.style.display="none";html5QrCode.resume()},3000)})}html5QrCode.start({facingMode:"environment"},{fps:15,qrbox:250},onScanSuccess);</script></body></html>`, { headers: { "Content-Type": "text/html; charset=UTF-8" } });
		}

		return json({ success: false, message: "Not Found" }, 404);
	},
};
