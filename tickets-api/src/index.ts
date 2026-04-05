export interface Env {
	DB: D1Database;
	D1_API_TOKEN: string;
}

export default {
	async fetch(request: Request, env: Env): Promise<Response> {
		const url = new URL(request.url);

		// --- 0. SEGURIDAD (Token API) ---
		const authHeader = request.headers.get("Authorization");
		const isPublicScan = url.pathname === "/api/validate" && request.method === "POST";
		const isPublicCreate = url.pathname === "/api/create-ticket" && request.method === "POST";
		const isVisual = url.pathname === "/" && request.method === "GET";

		if (!isPublicScan && !isPublicCreate && !isVisual) {
			if (!authHeader || authHeader !== `Bearer ${env.D1_API_TOKEN}`) {
				return Response.json({ success: false, message: "No autorizado" }, { status: 401 });
			}
		}

		// --- A. PROXY GENÉRICO DE CONSULTAS (Para PHP) ---
		if (url.pathname === "/api/query" && request.method === "POST") {
			try {
				const body: any = await request.json();
				const { sql, params, method } = body;

				if (!sql) return Response.json({ success: false, message: "SQL faltante" }, { status: 400 });

				const statement = env.DB.prepare(sql).bind(...(params || []));
				
				let result;
				if (method === "run") {
					result = await statement.run();
				} else if (method === "first") {
					result = await statement.first();
				} else {
					// Por defecto usamos all para SELECT
					const { results, meta }: any = await statement.all();
					result = { results, meta };
				}

				return Response.json({ success: true, data: result });
			} catch (e: any) {
				return Response.json({ success: false, message: "Error D1: " + e.message }, { status: 500 });
			}
		}

		// --- B. CREAR TICKET (Legacy/Directo) ---
		if (url.pathname === "/api/create-ticket" && request.method === "POST") {
			try {
				const body: any = await request.json();
				const { ticket_code, attendee_name, attendee_email } = body;

				await env.DB.prepare(
					"INSERT INTO tickets (ticket_code, attendee_name, attendee_email, status) VALUES (?, ?, ?, 'valid')"
				).bind(ticket_code, attendee_name, attendee_email).run();

				return Response.json({ success: true, message: "Ticket creado en Cloudflare" });
			} catch (e: any) {
				return Response.json({ success: false, message: "Error al crear: " + e.message }, { status: 500 });
			}
		}

		// --- C. VALIDAR TICKET (Escáner) ---
		if (url.pathname === "/api/validate" && request.method === "POST") {
			try {
				const body: any = await request.json();
				let code = body.ticket_code.includes('/') ? body.ticket_code.split('/').pop().trim() : body.ticket_code.trim();

				const ticket = await env.DB.prepare("SELECT attendee_name, status FROM tickets WHERE ticket_code = ?").bind(code).first();

				if (!ticket) return Response.json({ success: false, message: "No existe en Cloudflare" }, { status: 404 });
				if (ticket.status === 'used') return Response.json({ success: false, message: "¡YA USADO!" }, { status: 409 });

				await env.DB.prepare("UPDATE tickets SET status = 'used' WHERE ticket_code = ?").bind(code).run();
				return Response.json({ success: true, message: "¡BIENVENIDO! " + ticket.attendee_name });
			} catch (e: any) {
				return Response.json({ success: false, message: e.message }, { status: 500 });
			}
		}

		// --- D. INTERFAZ VISUAL ---
		if (url.pathname === "/" || url.pathname === "") {
			return new Response(`
				<!DOCTYPE html>
				<html lang="es">
				<head>
					<meta charset="UTF-8">
					<meta name="viewport" content="width=device-width, initial-scale=1.0">
					<title>Portería Cloudflare</title>
					<script src="https://unpkg.com/html5-qrcode"></script>
					<style>
						body { font-family: sans-serif; text-align: center; background: #0f172a; color: white; padding: 20px; }
						#reader { width: 100%; max-width: 450px; margin: 20px auto; border-radius: 15px; overflow: hidden; border: 2px solid #334155; }
						#result { margin-top: 20px; padding: 20px; border-radius: 10px; font-weight: bold; font-size: 1.5rem; display: none; }
						.success { background: #10b981; } .error { background: #ef4444; }
						.info { color: #94a3b8; font-size: 0.9rem; }
					</style>
				</head>
				<body>
					<h2>🎟️ Portería Digital</h2>
					<p class="info">Sistema 100% en Cloudflare D1</p>
					<div id="reader"></div>
					<div id="result"></div>
					<script>
						const html5QrCode = new Html5Qrcode("reader");
						function onScanSuccess(decodedText) {
							html5QrCode.pause(true);
							fetch("/api/validate", {
								method: "POST",
								headers: { "Content-Type": "application/json" },
								body: JSON.stringify({ ticket_code: decodedText })
							})
							.then(res => res.json())
							.then(data => {
								const dr = document.getElementById("result");
								dr.style.display = "block";
								dr.innerText = data.message;
								dr.className = data.success ? "success" : "error";
								setTimeout(() => { dr.style.display="none"; html5QrCode.resume(); }, 3000);
							});
						}
						html5QrCode.start({ facingMode: "environment" }, { fps: 15, qrbox: 250 }, onScanSuccess);
					</script>
				</body>
				</html>
			`, { headers: { "Content-Type": "text/html" } });
		}

		return Response.json({ success: false, message: "Not Found" }, { status: 404 });
	}
};