-- Payment orders and idempotent Stripe fulfillment.
CREATE TABLE IF NOT EXISTS payment_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_token TEXT NOT NULL UNIQUE,
    event_id INTEGER NOT NULL,
    admin_id INTEGER NOT NULL,
    attendee_name TEXT NOT NULL,
    attendee_email TEXT NOT NULL,
    attendee_phone TEXT,
    amount_cents INTEGER NOT NULL,
    currency TEXT NOT NULL DEFAULT 'eur',
    status TEXT NOT NULL DEFAULT 'pending',
    stripe_account_id TEXT,
    stripe_checkout_session_id TEXT UNIQUE,
    stripe_payment_intent_id TEXT,
    fulfillment_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_payment_orders_event ON payment_orders(event_id);
CREATE INDEX IF NOT EXISTS idx_payment_orders_status ON payment_orders(status);
CREATE INDEX IF NOT EXISTS idx_payment_orders_stripe_pi ON payment_orders(stripe_payment_intent_id);

CREATE TABLE IF NOT EXISTS payment_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    ticket_type_id INTEGER,
    quantity INTEGER NOT NULL,
    unit_amount_cents INTEGER NOT NULL,
    FOREIGN KEY (order_id) REFERENCES payment_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_payment_order_items_order ON payment_order_items(order_id);

ALTER TABLE tickets ADD COLUMN payment_order_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_tickets_payment_order ON tickets(payment_order_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_tickets_order_code ON tickets(payment_order_id, ticket_code);
