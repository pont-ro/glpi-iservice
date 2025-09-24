-- Purpose: Update glpi_cartridges.printers_id using the related item (Printer) from glpi_items_tickets
-- Relation: glpi_plugin_fields_cartridgecartridgecustomfields.tickets_id_use_field gives the ticket id used for the install
-- Join logic:
--   glpi_plugin_fields_cartridgecartridgecustomfields.items_id = glpi_cartridges.id (and itemtype = 'cartridge')
--   glpi_items_tickets.tickets_id = glpi_plugin_fields_cartridgecartridgecustomfields.tickets_id_use_field (and itemtype = 'Printer')
-- The UPDATE only affects cartridges where a matching printer item exists for the install ticket.
-- Optional safety: Only update when printers_id is NULL or 0 to avoid overwriting valid links.

UPDATE glpi_cartridges c
JOIN glpi_plugin_fields_cartridgecartridgecustomfields ccf
  ON ccf.items_id = c.id AND ccf.itemtype = 'Cartridge'
JOIN glpi_items_tickets it
  ON it.tickets_id = ccf.tickets_id_use_field AND it.itemtype = 'Printer'
SET c.printers_id = it.items_id
WHERE (c.printers_id IS NULL OR c.printers_id = 0 and c.date_use is not null);