export interface Env {
	DB: D1Database;
	D1_API_TOKEN: string;
}

export default {
	async fetch(request: Request, env: Env): Promise<Response> {
		const url = new URL(request.url);
		const pathname = url.pathname.replace(/\/+$/, "") || "/";

		const authHeader = request.headers.get("Authorization");
		const isPublicScan = pathname === "/api/validate" && request.method === "POST";
		const isVisual = pathname === "/" && request.method === "GET";

		if (!isPublicScan && !isVisual) {
			if (!authHeader || authHeader !== `Bearer ${env.D1_API_TOKEN}`) {
				return Response.json(
					{ success: false, message: "No autorizado" },
					{ status: 401, headers: { "X-Worker-Route": "Unauthorized" } },
				);
			}
		}

		// Proxy temporal de consultas para la aplicación PHP.
		// Se mantiene protegido por D1_API_TOKEN mientras se migra a endpoints específicos.
		if (pathname === "/api/query" && request.method === "POST") {
			try {
				const body: unknown = await request.json();
				if (!body || typeof body !== "object") {
					return Response.json({ success: false, message: "Payload inválido" }, { status: 400 });
				}

				const { sql, params, method } = body as {
					sql?: unknown;
					params?: unknown;
					method?: unknown;
				};

				if (typeof sql !== "string" || sql.trim() === "") {
					return Response.json({ success: false, message: "SQL faltante" }, { status: 400 });
				}

				const boundParams = Array.isArray(params) ? params : [];
				const statement = env.DB.prepare(sql).bind(...boundParams);

				if (method === "run") {
					return Response.json({ success: true, data: await statement.run() });
				}

				if (method === "first") {
					return Response.json({ success: true, data: await statement.first() });
				}

				const { results, meta } = await statement.all();
				return Response.json({ success: true, data: { results, meta } });
			} catch {
				return Response.json(
					{ success: false, message: "Error interno del servidor" },
					{ status: 500 },
				);
			}
		}

		// Validación de tickets para el escáner.
		if (pathname === "/api/validate" && request.method === "POST") {
			try {
				const body = (await request.json()) as { ticket_code?: unknown };
				const rawCode = typeof body.ticket_code === "string" ? body.ticket_code.trim() : "";

				if (!rawCode) {
					return Response.json({ success: false, message: "Código de ticket requerido" }, { status: 400 });
				}

				let code = rawCode;
				try {
					const parsed = new URL(rawCode);
					code = parsed.searchParams.get("code") || rawCode;
				} catch {
					if (rawCode.includes("code=")) {
						code = rawCode.split("code=").pop()?.split("&")[0] || rawCode;
					} else if (rawCode.includes("/")) {
						code = rawCode.split("/").pop()?.trim() || rawCode;
					}
				}

				code = code.trim();

				const ticket = await env.DB
					.prepare("SELECT id, ticket_code, attendee_name, status FROM tickets WHERE ticket_code = ?")
					.bind(code)
					.first<{ id: number; ticket_code: string; attendee_name: string; status: string }>();

				if (!ticket) {
					return Response.json({ success: false, message: "Ticket no encontrado" }, { status: 404 });
				}

				if (ticket.status === "used") {
					return Response.json({ success: false, message: "¡YA USADO!" }, { status: 409 });
				}

				if (ticket.status !== "valid") {
					return Response.json({ success: false, message: "Ticket no válido" }, { status: 409 });
				}

				const result = await env.DB
					.prepare("UPDATE tickets SET status = 'used' WHERE ticket_code = ? AND status = 'valid'")
					.bind(code)
					.run();

				if (result.meta.changes !== 1) {
					return Response.json({ success: false, message: "Ticket ya utilizado" }, { status: 409 });
				}

				return Response.json({ success: true, message: "¡BIENVENIDO! " + ticket.attendee_name });
			} catch {
				return Response.json({ success: false, message: "Error al validar el ticket" }, { status: 500 });
			}
		}

		if (pathname === "/") {
			return new Response(
				`<!DOCTYPE html>
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
								setTimeout(() => { dr.style.display = "none"; html5QrCode.resume(); }, 3000);
							})
							.catch(() => {
								const dr = document.getElementById("result");
								dr.style.display = "block";
								dr.innerText = "Error de conexión";
								dr.className = "error";
								setTimeout(() => { dr.style.display = "none"; html5QrCode.resume(); }, 3000);
							});
						}
						html5QrCode.start({ facingMode: "environment" }, { fps: 15, qrbox: 250 }, onScanSuccess);
					</script>
				</body>
				</html>`,
				{ headers: { "Content-Type": "text/html; charset=UTF-8" } },
			);
		}

		return Response.json({ success: false, message: "Not Found" }, { status: 404 });
	},
};
