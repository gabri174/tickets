-- Reserve inventory when a paid checkout is created so concurrent buyers cannot oversell.
ALTER TABLE payment_orders ADD COLUMN inventory_reserved INTEGER NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_payment_orders_inventory_reserved ON payment_orders(inventory_reserved, status);
