-- ============================================================================
-- One-time cleanup before converting custom-field columns from VARCHAR back to
-- INT / DECIMAL. Run this on the iservice3 database BEFORE reinstalling/updating
-- the iService plugin, otherwise the ALTER TABLE will abort with
-- "Truncated incorrect INTEGER/DECIMAL value: ''".
--
-- Step 1: empty string -> NULL  (all affected number columns)
-- Step 2: strip decimal-formatted strings (e.g. '174.00' -> 174) for INT columns
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Step 1: '' -> NULL
-- ----------------------------------------------------------------------------

UPDATE glpi_plugin_fields_ticketticketcustomfields SET
    movement_id_field        = NULLIF(TRIM(movement_id_field), ''),
    movement2_id_field       = NULLIF(TRIM(movement2_id_field), ''),
    em_mail_id_field         = NULLIF(TRIM(em_mail_id_field), ''),
    total2_black_field       = NULLIF(TRIM(total2_black_field), ''),
    total2_color_field       = NULLIF(TRIM(total2_color_field), '');

UPDATE glpi_plugin_fields_printerprintercustomfields SET
    invoiced_total_black_field = NULLIF(TRIM(invoiced_total_black_field), ''),
    invoiced_total_color_field = NULLIF(TRIM(invoiced_total_color_field), ''),
    invoiced_value_field       = NULLIF(TRIM(invoiced_value_field), ''),
    week_nr_field              = NULLIF(TRIM(week_nr_field), ''),
    daily_bk_average_field     = NULLIF(TRIM(daily_bk_average_field), ''),
    daily_color_average_field  = NULLIF(TRIM(daily_color_average_field), ''),
    uc_bk_field                = NULLIF(TRIM(uc_bk_field), ''),
    uc_cyan_field              = NULLIF(TRIM(uc_cyan_field), ''),
    uc_magenta_field           = NULLIF(TRIM(uc_magenta_field), ''),
    uc_yellow_field            = NULLIF(TRIM(uc_yellow_field), '');

UPDATE glpi_plugin_fields_suppliersuppliercustomfields SET
    payment_deadline_field = NULLIF(TRIM(payment_deadline_field), '');

UPDATE glpi_plugin_fields_contractcontractcustomfields SET
    copy_price_bk_field        = NULLIF(TRIM(copy_price_bk_field), ''),
    copy_price_col_field       = NULLIF(TRIM(copy_price_col_field), ''),
    included_copies_bk_field   = NULLIF(TRIM(included_copies_bk_field), ''),
    included_copies_col_field  = NULLIF(TRIM(included_copies_col_field), ''),
    included_copy_value_field  = NULLIF(TRIM(included_copy_value_field), ''),
    monthly_fee_field          = NULLIF(TRIM(monthly_fee_field), ''),
    currency_field             = NULLIF(TRIM(currency_field), ''),
    copy_price_divider_field   = NULLIF(TRIM(copy_price_divider_field), '');

UPDATE glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields SET
    atc_field              = NULLIF(TRIM(atc_field), ''),
    life_coefficient_field = NULLIF(TRIM(life_coefficient_field), '');

UPDATE glpi_plugin_fields_cartridgecartridgecustomfields SET
    tickets_id_use_field                        = NULLIF(TRIM(tickets_id_use_field), ''),
    tickets_id_out_field                        = NULLIF(TRIM(tickets_id_out_field), ''),
    pages_out_field                             = NULLIF(TRIM(pages_out_field), ''),
    pages_color_out_field                       = NULLIF(TRIM(pages_color_out_field), ''),
    pages_use_field                             = NULLIF(TRIM(pages_use_field), ''),
    pages_color_use_field                       = NULLIF(TRIM(pages_color_use_field), ''),
    suppliers_id_field                          = NULLIF(TRIM(suppliers_id_field), ''),
    locations_id_field                          = NULLIF(TRIM(locations_id_field), ''),
    plugin_fields_cartridgeitemtypedropdowns_id = NULLIF(TRIM(plugin_fields_cartridgeitemtypedropdowns_id), '');

-- ----------------------------------------------------------------------------
-- Step 2: strip decimals for INT-target columns only ('174.00' -> 174)
-- (Decimal columns such as prices/coefficients are intentionally excluded.)
-- ----------------------------------------------------------------------------

UPDATE glpi_plugin_fields_ticketticketcustomfields SET
    movement_id_field  = FLOOR(movement_id_field),
    movement2_id_field = FLOOR(movement2_id_field),
    em_mail_id_field   = FLOOR(em_mail_id_field),
    total2_black_field = FLOOR(total2_black_field),
    total2_color_field = FLOOR(total2_color_field)
WHERE movement_id_field LIKE '%.%'
   OR movement2_id_field LIKE '%.%'
   OR em_mail_id_field LIKE '%.%'
   OR total2_black_field LIKE '%.%'
   OR total2_color_field LIKE '%.%';

UPDATE glpi_plugin_fields_printerprintercustomfields SET
    invoiced_total_black_field = FLOOR(invoiced_total_black_field),
    invoiced_total_color_field = FLOOR(invoiced_total_color_field),
    week_nr_field              = FLOOR(week_nr_field),
    daily_bk_average_field     = FLOOR(daily_bk_average_field),
    daily_color_average_field  = FLOOR(daily_color_average_field)
WHERE invoiced_total_black_field LIKE '%.%'
   OR invoiced_total_color_field LIKE '%.%'
   OR week_nr_field LIKE '%.%'
   OR daily_bk_average_field LIKE '%.%'
   OR daily_color_average_field LIKE '%.%';

UPDATE glpi_plugin_fields_suppliersuppliercustomfields SET
    payment_deadline_field = FLOOR(payment_deadline_field)
WHERE payment_deadline_field LIKE '%.%';

UPDATE glpi_plugin_fields_contractcontractcustomfields SET
    included_copies_bk_field  = FLOOR(included_copies_bk_field),
    included_copies_col_field = FLOOR(included_copies_col_field),
    copy_price_divider_field  = FLOOR(copy_price_divider_field)
WHERE included_copies_bk_field LIKE '%.%'
   OR included_copies_col_field LIKE '%.%'
   OR copy_price_divider_field LIKE '%.%';

UPDATE glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields SET
    atc_field = FLOOR(atc_field)
WHERE atc_field LIKE '%.%';

UPDATE glpi_plugin_fields_cartridgecartridgecustomfields SET
    tickets_id_use_field                        = FLOOR(tickets_id_use_field),
    tickets_id_out_field                        = FLOOR(tickets_id_out_field),
    pages_out_field                             = FLOOR(pages_out_field),
    pages_color_out_field                       = FLOOR(pages_color_out_field),
    pages_use_field                             = FLOOR(pages_use_field),
    pages_color_use_field                       = FLOOR(pages_color_use_field),
    suppliers_id_field                          = FLOOR(suppliers_id_field),
    locations_id_field                          = FLOOR(locations_id_field),
    plugin_fields_cartridgeitemtypedropdowns_id = FLOOR(plugin_fields_cartridgeitemtypedropdowns_id)
WHERE tickets_id_use_field LIKE '%.%'
   OR tickets_id_out_field LIKE '%.%'
   OR pages_out_field LIKE '%.%'
   OR pages_color_out_field LIKE '%.%'
   OR pages_use_field LIKE '%.%'
   OR pages_color_use_field LIKE '%.%'
   OR suppliers_id_field LIKE '%.%'
   OR locations_id_field LIKE '%.%'
   OR plugin_fields_cartridgeitemtypedropdowns_id LIKE '%.%';
