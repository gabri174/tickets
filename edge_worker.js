/**
 * Cloudflare Worker / Vercel Edge Function - SRE 3M Architecture
 * 
 * Este worker actúa como la PRIMERA BARRERA.
 * Soporta millones de peticiones por segundo sin enviar el tráfico masivo a tu servidor (Servebyt).
 * 
 * 1. Muestra tu frontend en Edge
 * 2. Hace el Lock Atómico en Upstash Redis (Stock)
 * 3. Envía la compra directamente a Upstash QStash (Cola)
 * 4. QStash enviará las compras a tu servidor despacio (ej. 5 por segundo)
 */

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // ────────────────────────────────────────────────────────
    // RUTA DE SINCRONIZACIÓN D1 (POST /api/d1-sync) - Migración
    // ────────────────────────────────────────────────────────
    if (request.method === "POST" && url.pathname === "/api/d1-sync") {
      // Proteger endpoint con token secreto compartido
      const authHeader = request.headers.get("Authorization");
      if (authHeader !== `Bearer ${env.D1_SYNC_TOKEN}`) {
        return new Response("Unauthorized", { status: 401 });
      }
      
      try {
        const data = await request.json();
        if (data.action === "insert_ticket" && env.DB) {
          // Dual-write: insert ticket into D1 using bound Database
          const stmt = env.DB.prepare(
            `INSERT INTO tickets (event_id, ticket_type_id, ticket_code, attendee_name, attendee_email, attendee_phone, status, qr_code_path, referral, zip_code)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
          ).bind(
            data.ticket.event_id,
            data.ticket.ticket_type_id,
            data.ticket.ticket_code,
            data.ticket.attendee_name,
            data.ticket.attendee_email,
            data.ticket.attendee_phone,
            data.ticket.status,
            data.ticket.qr_code_path,
            data.ticket.referral,
            data.ticket.zip_code
          );
          
          await stmt.run();
          return new Response(JSON.stringify({ success: true }), { status: 200 });
        }
        return new Response("Ignored or unsupported action", { status: 200 });
      } catch (e) {
        return new Response(`D1 Sync Error: ${e.message}`, { status: 500 });
      }
    }

    // ────────────────────────────────────────────────────────
    // RUTA DE COMPRA (Interceptar POST a /buy.php)
    // ────────────────────────────────────────────────────────
    if (request.method === "POST" && url.pathname === "/buy.php") {
      try {
        const formData = await request.formData();
        const eventId = url.searchParams.get("id") || formData.get("event_id") || "8";
        const quantity = parseInt(formData.get("quantity")) || 1;
        const ticketTypeId = formData.get("ticket_type_id");

        // --- CAPA 3: Semáforo de Inventario en Upstash Redis ---
        const stockKey = `stock:event:${eventId}`;
        // Script LUA para restar stock de forma atómica
        const luaScript = `
          local key = KEYS[1]
          local qty = tonumber(ARGV[1])
          local current = tonumber(redis.call('GET', key))
          if current == nil then return -1 end
          if current < qty then return -2 end
          return redis.call('DECRBY', key, qty)
        `;

        const redisResponse = await fetch(`${env.UPSTASH_REDIS_REST_URL}/eval`, {
          method: "POST",
          headers: {
            "Authorization": `Bearer ${env.UPSTASH_REDIS_REST_TOKEN}`,
            "Content-Type": "application/json"
          },
          body: JSON.stringify([luaScript, 1, stockKey, quantity.toString()])
        });

        const redisData = await redisResponse.json();
        const remainingStock = parseInt(redisData.result);

        if (remainingStock === -2) {
          return Response.redirect(`${url.origin}/buy.php?id=${eventId}&error=AGOTADO_Sin_stock_disponible`, 302);
        }
        if (remainingStock === -1) {
          return Response.redirect(`${url.origin}/buy.php?id=${eventId}&error=Evento_no_disponible_en_cache`, 302);
        }

        // --- CAPA 2: Enviar a la Cola (Upstash QStash) ---
        // Extraer datos del formulario (esto asume que envuelves los datos del attendee en JSON, 
        // pero para simplificar enviamos el RAW body)
        
        let purchaseData = {
          event_id: eventId,
          ticket_type_id: ticketTypeId,
          quantity: quantity,
          phone: formData.get("phone"),
          zip_code: formData.get("zip_code"),
          // Aquí mapearías todos los attendees recibidos en el POST
          attendees: [{
            name: formData.get("attendees[0][name]"),
            surname: formData.get("attendees[0][surname]"),
            email: formData.get("attendees[0][email]")
          }],
          total_price: 0 // Lo recalculará el backend, esto es para entradas gratis
        };

        const qstashPayload = {
          action: "complete_purchase",
          purchase_data: purchaseData,
          enqueued_at: Date.now(),
          attempt: 1
        };

        const qstashResponse = await fetch(`${env.QSTASH_URL}/v2/publish/${env.QUEUE_WORKER_URL}`, {
          method: "POST",
          headers: {
            "Authorization": `Bearer ${env.UPSTASH_QSTASH_TOKEN}`,
            "Content-Type": "application/json",
            "Upstash-Retries": "3"
          },
          body: JSON.stringify(qstashPayload)
        });

        if (qstashResponse.ok) {
          // Éxito: Redirigir a success.php de forma transparente
          return Response.redirect(`${url.origin}/success.php`, 302);
        } else {
          // Falla QStash -> Devolvemos el stock a Redis (compensación)
          await fetch(`${env.UPSTASH_REDIS_REST_URL}/incrby/${stockKey}/${quantity}`, {
            method: "POST",
            headers: { "Authorization": `Bearer ${env.UPSTASH_REDIS_REST_TOKEN}` }
          });
          
          let errorText = "Desconocido";
          try {
            errorText = await qstashResponse.text();
            errorText = encodeURIComponent(errorText.substring(0, 100)); // Capping length
          } catch(e) {}

          return Response.redirect(`${url.origin}/buy.php?id=${eventId}&error=Colapso_pasarela_qstash_fail_${qstashResponse.status}_${errorText}`, 302);
        }

      } catch (e) {
        return Response.redirect(`${url.origin}/buy.php?error=Edge_Error`, 302);
      }
    }

    // Si no es POST /buy.php, pasamos la petición al servidor original (Servebyt) de forma transparente
    const originResponse = await fetch(request);
    
    // Podemos inyectar aquí cabeceras de caché ultra agresivas para los HTML y CSS si se desea
    return originResponse;
  }
};
