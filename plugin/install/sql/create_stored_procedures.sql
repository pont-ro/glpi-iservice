
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

DROP FUNCTION IF EXISTS `getPrinterDailyAverage`;
DROP PROCEDURE IF EXISTS `getPrinterDailyAverageCalculation`;
DROP FUNCTION IF EXISTS `getCartridgePercentageEstimate`;
DROP FUNCTION IF EXISTS `getPrinterCounterEstimate`;
DROP FUNCTION IF EXISTS `getCartridgePercentage`;
DROP FUNCTION IF EXISTS `getCartridgeDaysToEmptyEstimate`;
DROP FUNCTION IF EXISTS `getCartridgeDaysToEmpty`;
DROP FUNCTION IF EXISTS `getCartridgeCompatiblePrinterCount`;
DROP FUNCTION IF EXISTS `getCartridgeChangeableCartridgeCount`;

-- Dumping structure for function glpi.getCartridgeChangeableCartridgeCount
DELIMITER //
CREATE FUNCTION `getCartridgeChangeableCartridgeCount`(
	 `cartridgeId` INT
) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
    DECLARE cartridgeCount INT;
    SELECT COUNT(DISTINCT c2.id) INTO cartridgeCount
    FROM glpi_plugin_iservice_cartridges c1
    LEFT JOIN glpi_locations l1 ON l1.id = c1.locations_id_field
    JOIN glpi_infocoms ic on ic.items_id = c1.printers_id and ic.itemtype = 'Printer'
    JOIN glpi_plugin_fields_suppliersuppliercustomfields cfs ON cfs.items_id = ic.suppliers_id AND cfs.itemtype = 'Supplier'
    JOIN glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci1 ON cfci1.items_id = c1.cartridgeitems_id AND cfci1.itemtype = 'CartridgeItem'
    JOIN glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci2 ON FIND_IN_SET(cfci2.mercury_code_field, replace(cfci1.compatible_mercury_codes_field, "'", "")) and cfci2.itemtype = 'CartridgeItem'
    JOIN glpi_plugin_iservice_cartridges c2 ON c2.cartridgeitems_id = cfci2.items_id
    LEFT JOIN glpi_locations l2 ON l2.id = c2.locations_id_field
    WHERE c1.id = cartridgeId
      AND c2.date_use IS NULL AND c2.date_out IS NULL
      AND FIND_IN_SET (c2.suppliers_id_field, cfs.group_field)
      AND COALESCE(c2.printers_id, 0) = 0
      AND (cfci1.plugin_fields_cartridgeitemtypedropdowns_id = cfci2.plugin_fields_cartridgeitemtypedropdowns_id or COALESCE(cfci2.plugin_fields_cartridgeitemtypedropdowns_id, 0) = 0)
      AND (c2.locations_id_field = c1.locations_id_field OR COALESCE(l1.locations_id, 0) = COALESCE(l2.locations_id, 0))
      ;
    RETURN cartridgeCount;
END//
DELIMITER ;

-- Dumping structure for function glpi.getCartridgeCompatiblePrinterCount
DELIMITER //
CREATE FUNCTION `getCartridgeCompatiblePrinterCount`(
    `cartridgeId` INT
) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
    DECLARE printerCount INT;
    SELECT COUNT(DISTINCT p.id) INTO printerCount
    FROM glpi_plugin_iservice_cartridges c
    LEFT JOIN glpi_locations l1 on l1.id = c.locations_id_field
    LEFT JOIN glpi_plugin_fields_suppliercustomfields cfs on cfs.items_id = c.suppliers_id_field and cfs.itemtype = 'Supplier'
    JOIN glpi_cartridgeitems_printermodels cp ON cp.cartridgeitems_id = c.cartridgeitems_id
    JOIN glpi_printers p ON p.printermodels_id = cp.printermodels_id
    LEFT JOIN glpi_locations l2 on l2.id = p.locations_id
    JOIN glpi_infocoms ic ON ic.itemtype = 'Printer'
                         AND ic.items_id = p.id
                         AND FIND_IN_SET (ic.suppliers_id, cfs.group_field)
    WHERE c.id = cartridgeId
      AND (c.locations_id_field = p.locations_id OR COALESCE(l1.locations_id, 0) = COALESCE(l2.locations_id, 0))
    ;
    RETURN printerCount;
END//
DELIMITER ;

-- Dumping structure for function glpi.getCartridgeDaysToEmpty
DELIMITER //
CREATE FUNCTION `getCartridgeDaysToEmpty`(
	 `cartridgeId` INT
) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
    DECLARE days DECIMAL(5,2);
    SELECT ROUND(IF(cfci.atc_field = 0, 1000, cfci.atc_field) * IF(cfci.life_coefficient_field = 0, 1000, cfci.life_coefficient_field) * CASE cfci.plugin_fields_cartridgeitemtypedropdowns_id
           WHEN 2 THEN IF (cfp.uc_cyan_field = 0, 1, cfp.uc_cyan_field) / IF(cfp.daily_color_average_field = 0, 180, cfp.daily_color_average_field)
           WHEN 3 THEN IF(cfp.uc_magenta_field = 0, 1, cfp.uc_magenta_field) / IF(cfp.daily_color_average_field = 0, 180, cfp.daily_color_average_field)
           WHEN 4 THEN IF(cfp.uc_yellow_field = 0, 1, cfp.uc_yellow_field) / IF(cfp.daily_color_average_field = 0, 180, cfp.daily_color_average_field)
           ELSE IF(cfp.uc_bk_field = 0, 1, cfp.uc_bk_field) / IF(cfp.daily_bk_average_field = 0, 180, cfp.daily_bk_average_field)
         END) INTO days
    FROM glpi_cartridges c
    LEFT JOIN glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci ON cfci.itemtype = 'CartridgeItem' AND cfci.items_id = c.cartridgeitems_id
    LEFT JOIN glpi_plugin_fields_printerprintercustomfields cfp ON cfp.itemtype = 'Printer' AND cfp.items_id = c.printers_id
    WHERE c.id = cartridgeId;
    RETURN coalesce(days, 180);
END//
DELIMITER ;

-- Dumping structure for function glpi.getCartridgeDaysToEmptyEstimate
DELIMITER //
CREATE FUNCTION `getCartridgeDaysToEmptyEstimate`(
	  `cartridgeId` INT
	, `single` TINYINT
) RETURNS int(11)
    READS SQL DATA
    SQL SECURITY INVOKER
BEGIN
    DECLARE uc, lc DECIMAL(5,2);
    DECLARE atc, da, counter_use, counter_last INT;
    DECLARE last_data_luc DATETIME;
    DECLARE addition INT;
    
    SELECT
        IF(cfci.atc_field = 0, 1000, cfci.atc_field) atc
      , IF(cfci.life_coefficient_field = 0, 1, cfci.life_coefficient_field) lc
      , CASE cfci.plugin_fields_cartridgeitemtypedropdowns_id 
          WHEN 2 THEN IF (cfp.uc_cyan_field = 0, 1, cfp.uc_cyan_field)
          WHEN 3 THEN IF(cfp.uc_magenta_field = 0, 1, cfp.uc_magenta_field)
          WHEN 4 THEN IF(cfp.uc_yellow_field = 0, 1, cfp.uc_yellow_field)
          ELSE IF(cfp.uc_bk_field = 0, 1, cfp.uc_bk_field)
        END uc
      , CASE cfci.plugin_fields_cartridgeitemtypedropdowns_id 
          WHEN 2 THEN IF(cfp.daily_color_average_field = 0, 180, cfp.daily_color_average_field)
          WHEN 3 THEN IF(cfp.daily_color_average_field = 0, 180, cfp.daily_color_average_field)
          WHEN 4 THEN IF(cfp.daily_color_average_field = 0, 180, cfp.daily_color_average_field)
          ELSE IF(cfp.daily_bk_average_field = 0, 180, cfp.daily_bk_average_field)
        END da
      , CASE cfci.plugin_fields_cartridgeitemtypedropdowns_id 
          WHEN 2 THEN cfp.total2_color -- plct.total2_color_field
          WHEN 3 THEN cfp.total2_color -- plct.total2_color_field
          WHEN 4 THEN cfp.total2_color -- plct.total2_color_field
          ELSE cfp.total2_black -- plct.total2_black_field
        END counter_last
      , CASE cfci.plugin_fields_cartridgeitemtypedropdowns_id 
          WHEN 2 THEN t.total2_color_field
          WHEN 3 THEN t.total2_color_field
          WHEN 4 THEN t.total2_color_field
          ELSE t.total2_black_field
        END counter_use
      , cfp.data_luc -- plct.effective_date_field
    INTO atc, lc, uc, da, counter_last, counter_use, last_data_luc
    FROM glpi_plugin_iservice_cartridges c
    LEFT JOIN glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci ON cfci.itemtype = 'CartridgeItem' AND cfci.items_id = c.cartridgeitems_id
    LEFT JOIN glpi_plugin_iservice_printers p ON p.id = c.printers_id
    -- LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plct ON plct.printers_id = p.id
    LEFT JOIN glpi_plugin_fields_printercustomfields cfp ON cfp.itemtype = 'Printer' AND cfp.items_id = c.printers_id
    LEFT JOIN glpi_plugin_iservice_tickets t on t.id = c.tickets_id_use_field
    WHERE c.id = cartridgeId;
              
    SET addition = 
        CASE single
        WHEN 1 THEN 0
        ELSE getCartridgeChangeableCartridgeCount(cartridgeId) * atc * lc * uc / (getCartridgeCompatiblePrinterCount(cartridgeId) * da)
        END;
                  
    RETURN ROUND(coalesce((atc * lc * uc - (counter_last-counter_use)) / da, 180) - DATEDIFF(NOW(), last_data_luc)) + addition;
END//
DELIMITER ;

-- Dumping structure for function glpi.getCartridgePercentage
DELIMITER //
CREATE FUNCTION `getCartridgePercentage` (
    `cartridgeId` INT
  , `single` TINYINT
) RETURNS decimal(5,4)
    READS SQL DATA
    SQL SECURITY INVOKER
BEGIN
    DECLARE percentage DECIMAL(5,4);
    SELECT IF(single = 1, 0, getCartridgeChangeableCartridgeCount(c.id) / IF(COALESCE(getCartridgeCompatiblePrinterCount(c.id), 0) = 0, 1, getCartridgeCompatiblePrinterCount(c.id))) + 1 - DATEDIFF(NOW(), COALESCE(c.date_use, NOW())) / getCartridgeDaysToEmpty(c.id) INTO percentage
    FROM glpi_cartridges c
    WHERE c.id = cartridgeId;
    RETURN percentage;
END//
DELIMITER ;

-- Dumping structure for function glpi.getPrinterCounterEstimate
DELIMITER //
CREATE FUNCTION `getPrinterCounterEstimate`(
	  `printerId` INT
  ,	`color` TINYINT
) RETURNS int(11)
    READS SQL DATA
    SQL SECURITY INVOKER
BEGIN
    DECLARE lastCounter, dailyAverage INT;
    DECLARE lastDataLuc DATETIME;
    
    SELECT
        cfp.data_luc -- plct.effective_date_field
      , CASE color WHEN 1 THEN cfp.total2_color /* plct.total2_color_field */ ELSE cfp.total2_black /* plct.total2_black_field */ END
      , CASE color WHEN 1 THEN cfp.daily_color_average_field ELSE cfp.daily_bk_average_field END
    INTO lastDataLuc, lastCounter, dailyAverage
    FROM glpi_plugin_iservice_printers p
    -- LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plct ON plct.printers_id = p.id
    JOIN glpi_plugin_fields_printerprintercustomfields cfp ON cfp.itemtype = 'Printer' AND  cfp.items_id = p.id
    WHERE p.id = printerId;

    RETURN lastCounter + DATEDIFF(NOW(), lastDataLuc) * dailyAverage;
END//
DELIMITER ;

-- Dumping structure for function glpi.getCartridgePercentageEstimate
DELIMITER //
CREATE FUNCTION `getCartridgePercentageEstimate`(
  	`cartridgeId` INT
  ,	`single` TINYINT
) RETURNS decimal(5,2)
    READS SQL DATA
    SQL SECURITY INVOKER
BEGIN
    DECLARE percentage DECIMAL(5,2);
    DECLARE addition, compatiblePrinterCount, installedCounter INT;
    DECLARE color TINYINT;
    
    SET compatiblePrinterCount = IF(COALESCE(getCartridgeCompatiblePrinterCount(cartridgeId), 0) = 0, 1, getCartridgeCompatiblePrinterCount(cartridgeId));
    SET addition =
      CASE single
        WHEN 1 THEN 0
        ELSE getCartridgeChangeableCartridgeCount(cartridgeId) / compatiblePrinterCount
      END;
    
    SELECT
        CASE cfci.plugin_fields_cartridgeitemtypedropdowns_id 
          WHEN 2 THEN 1
          WHEN 3 THEN 1
          WHEN 4 THEN 1
          ELSE 0
        END
      , CASE cfci.plugin_fields_cartridgeitemtypedropdowns_id 
          WHEN 2 THEN t.total2_color_field
          WHEN 3 THEN t.total2_color_field
          WHEN 4 THEN t.total2_color_field
          ELSE t.total2_black_field
        END
    INTO color, installedCounter
    FROM glpi_plugin_iservice_cartridges c
    JOIN glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci ON cfci.items_id = c.cartridgeitems_id and cfci.itemtype = 'CartridgeItem'
    JOIN glpi_plugin_iservice_tickets t on t.id = c.tickets_id_use_field
    WHERE c.id = cartridgeId;
    
    SELECT 100 *(addition + 1 - (getPrinterCounterEstimate(c.printers_id, color) - installedCounter) / ci.atc_field)
    INTO percentage
    FROM glpi_cartridges c
    JOIN glpi_plugin_iservice_cartridge_items ci on ci.id = c.cartridgeitems_id
    WHERE c.id = cartridgeId;
    
    RETURN percentage;
END//
DELIMITER ;

-- Dumping structure for procedure glpi.getPrinterDailyAverageCalculation
DELIMITER //
CREATE PROCEDURE `getPrinterDailyAverageCalculation`(
	  IN `printerId` INT
	, IN `color` TINYINT
  ,	OUT `dailyAverage` INT
  ,	OUT `ticketCount` INT
  , OUT `minCounter` INT
  ,	OUT `maxCounter` INT
  ,	OUT `minDataLuc` DATETIME
  ,	OUT `maxDataLuc` DATETIME
)
    READS SQL DATA
    DETERMINISTIC
    SQL SECURITY INVOKER
this_proc: BEGIN
  DECLARE minDayCount INT DEFAULT 60;
  DECLARE minTicketCount INT DEFAULT 3;
  
  DECLARE countFirstDays INT DEFAULT 0;
  
  SELECT
      COUNT(t.id)
    , MIN(CASE color WHEN 1 THEN t.total2_color_field ELSE t.total2_black_field + t.total2_color_field END)
    , MAX(CASE color WHEN 1 THEN t.total2_color_field ELSE t.total2_black_field + t.total2_black_field END)
    , MIN(t.effective_date_field)
    , MAX(t.effective_date_field)
  INTO
      ticketCount
    , minCounter
    , maxCounter
    , minDataLuc
    , maxDataLuc
  FROM glpi_plugin_iservice_tickets t
  JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer' AND it.items_id = printerId
  WHERE t.is_deleted = 0 AND t.`status` = 6;
  
  -- If there are less then 2 tickets or there is no counter difference
  IF ticketCount < 2 OR minCounter = maxCounter THEN
    -- the data is not enough
    SET dailyAverage = null;
    LEAVE this_proc;
  END IF;
  
  -- If there is less difference between the first and last tickets then minDayCount
  IF DATEDIFF(maxDataLuc, minDataLuc) < minDayCount THEN
    -- and we don't have minTicketCount tickets
    IF ticketCount < minTicketCount THEN
      -- the data is not enough
      SET dailyAverage = null;
      LEAVE this_proc;
    -- but if we have minTicketCount tickets
    ELSE
      -- we calculate the average daily count
      SELECT MIN(CASE color WHEN 1 THEN t.total2_color_field ELSE t.total2 END), MAX(CASE color WHEN 1 THEN t.total2_color_field ELSE t.total2 END), MIN(t.effective_date_field), MAX(t.effective_date_field)
      INTO minCounter, maxCounter, minDataLuc, maxDataLuc
      FROM (
        SELECT t.total2_black_field + t.total2_color_field total2, t.total2_color_field, t.effective_date_field
        FROM glpi_plugin_iservice_tickets t
        JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer' AND it.items_id = printerId
        WHERE t.is_deleted = 0 AND t.`status` = 6
        ORDER BY t.effective_date_field DESC
        LIMIT ticketCount
      ) t;
      SET dailyAverage = GREATEST(1, ROUND((maxCounter - minCounter) / DATEDIFF(maxDataLuc, minDataLuc)));
      LEAVE this_proc;
    END IF;
  END IF;
   
  
  -- We calculate how many tickets were in the last minDayCount
  SELECT count(t.id)
  INTO countFirstDays
  FROM glpi_plugin_iservice_tickets t
  JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer' AND it.items_id = printerId
  WHERE t.is_deleted = 0 AND t.`status` = 6
    AND DATEDIFF(maxDataLuc, t.effective_date_field) < minDayCount;

  REPEAT
    SET countFirstDays = countFirstDays + 1;
    SELECT MIN(CASE color WHEN 1 THEN t.total2_color_field ELSE t.total2 END), MAX(CASE color WHEN 1 THEN t.total2_color_field ELSE t.total2 END), MIN(t.effective_date_field), MAX(t.effective_date_field)
    INTO minCounter, maxCounter, minDataLuc, maxDataLuc
    FROM (
      SELECT t.total2_black_field + t.total2_color_field total2, t.total2_color_field, t.effective_date_field
      FROM glpi_plugin_iservice_tickets t
      JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer' AND it.items_id = printerId
      WHERE t.is_deleted = 0 AND t.`status` = 6
      ORDER BY t.effective_date_field DESC
      LIMIT countFirstDays
    ) t;
    IF maxCounter > minCounter THEN
      SET ticketCount = countFirstDays;
      SET dailyAverage = GREATEST(1, ROUND((maxCounter - minCounter) / DATEDIFF(maxDataLuc, minDataLuc)));
      LEAVE this_proc;
    END IF;
  UNTIL countFirstDays > ticketCount
  END REPEAT;

  SET dailyAverage = 0;
END//
DELIMITER ;

-- Dumping structure for function glpi.getPrinterDailyAverage
DELIMITER //
CREATE FUNCTION `getPrinterDailyAverage`(
  	`printerId` INT
  ,	`color` TINYINT
) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
    SQL SECURITY INVOKER
BEGIN
    DECLARE dailyAverage, ticketCount, minCounter, maxCounter INT DEFAULT 0;
    DECLARE minDataLuc, maxDataLuc DATETIME;
    
    CALL getPrinterDailyAverageCalculation(printerId, color, dailyAverage, ticketCount, minCounter, maxCounter, minDataLuc, maxDataLuc);
    
    RETURN dailyAverage;
END//
DELIMITER ;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
