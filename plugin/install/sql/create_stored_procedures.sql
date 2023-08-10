/* Imported from iService2, needs refactoring. */
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
    FROM glpi_cartridges c1
    LEFT JOIN glpi_locations l1 ON l1.id = c1.FK_location
    JOIN glpi_infocoms ic on ic.items_id = c1.printers_id and ic.itemtype = 'Printer'
    JOIN glpi_plugin_fields_suppliercustomfields cfs ON cfs.items_id = ic.suppliers_id AND cfs.itemtype = 'Supplier'
    JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc1 ON cfc1.items_id = c1.cartridgeitems_id AND cfc1.itemtype = 'CartridgeItem'
    JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc2 ON FIND_IN_SET(cfc2.mercurycodefield, replace(cfc1.mercurycodesfield, "'", "")) and cfc2.itemtype = 'CartridgeItem'
    JOIN glpi_cartridges c2 ON c2.cartridgeitems_id = cfc2.items_id
    LEFT JOIN glpi_locations l2 ON l2.id = c2.FK_location
    WHERE c1.id = cartridgeId
      AND c2.date_use IS NULL AND c2.date_out IS NULL
      AND FIND_IN_SET (c2.FK_enterprise, cfs.groupfield)
      AND COALESCE(c2.printers_id, 0) = 0
      AND (c1.plugin_fields_typefielddropdowns_id = c2.plugin_fields_typefielddropdowns_id or COALESCE(c2.plugin_fields_typefielddropdowns_id, 0) = 0)
      AND (c2.FK_location = c1.FK_location OR COALESCE(l1.locations_id, 0) = COALESCE(l2.locations_id, 0))
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
    FROM glpi_cartridges c
    LEFT JOIN glpi_locations l1 on l1.id = c.FK_location
    LEFT JOIN glpi_plugin_fields_suppliercustomfields cfs on cfs.items_id = c.FK_enterprise and cfs.itemtype = 'Supplier'
    JOIN glpi_cartridgeitems_printermodels cp ON cp.cartridgeitems_id = c.cartridgeitems_id
    JOIN glpi_printers p ON p.printermodels_id = cp.printermodels_id
    LEFT JOIN glpi_locations l2 on l2.id = p.locations_id
    JOIN glpi_infocoms ic ON ic.itemtype = 'Printer'
                         AND ic.items_id = p.id
                         AND FIND_IN_SET (ic.suppliers_id, cfs.groupfield)
    WHERE c.id = cartridgeId
      AND (c.FK_location = p.locations_id OR COALESCE(l1.locations_id, 0) = COALESCE(l2.locations_id, 0))
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
    SELECT ROUND(IF(cfc.atcfield = 0, 1000, cfc.atcfield) * IF(cfc.lcfield = 0, 1000, cfc.lcfield) * CASE c.plugin_fields_typefielddropdowns_id 
           WHEN 2 THEN IF (cfp.uccfield = 0, 1, cfp.uccfield) / IF(cfp.dailycoloraveragefield = 0, 180, cfp.dailycoloraveragefield)
           WHEN 3 THEN IF(cfp.ucmfield = 0, 1, cfp.ucmfield) / IF(cfp.dailycoloraveragefield = 0, 180, cfp.dailycoloraveragefield)
           WHEN 4 THEN IF(cfp.ucyfield = 0, 1, cfp.ucyfield) / IF(cfp.dailycoloraveragefield = 0, 180, cfp.dailycoloraveragefield)
           ELSE IF(cfp.ucbkfield = 0, 1, cfp.ucbkfield) / IF(cfp.dailybkaveragefield = 0, 180, cfp.dailybkaveragefield)
         END) INTO days
    FROM glpi_cartridges c
    LEFT JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.itemtype = 'CartridgeItem' AND cfc.items_id = c.cartridgeitems_id
    LEFT JOIN glpi_plugin_fields_printercustomfields cfp ON cfp.itemtype = 'Printer' AND cfp.items_id = c.printers_id
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
        IF(cfc.atcfield = 0, 1000, cfc.atcfield) atc
      , IF(cfc.lcfield = 0, 1, cfc.lcfield) lc
      , CASE cfc.plugin_fields_typefielddropdowns_id 
          WHEN 2 THEN IF (cfp.uccfield = 0, 1, cfp.uccfield)
          WHEN 3 THEN IF(cfp.ucmfield = 0, 1, cfp.ucmfield)
          WHEN 4 THEN IF(cfp.ucyfield = 0, 1, cfp.ucyfield)
          ELSE IF(cfp.ucbkfield = 0, 1, cfp.ucbkfield)
        END uc
      , CASE cfc.plugin_fields_typefielddropdowns_id 
          WHEN 2 THEN IF(cfp.dailycoloraveragefield = 0, 180, cfp.dailycoloraveragefield)
          WHEN 3 THEN IF(cfp.dailycoloraveragefield = 0, 180, cfp.dailycoloraveragefield)
          WHEN 4 THEN IF(cfp.dailycoloraveragefield = 0, 180, cfp.dailycoloraveragefield)
          ELSE IF(cfp.dailybkaveragefield = 0, 180, cfp.dailybkaveragefield)
        END da
      , CASE cfc.plugin_fields_typefielddropdowns_id 
          WHEN 2 THEN cfp.total2_color -- plct.total2_color
          WHEN 3 THEN cfp.total2_color -- plct.total2_color
          WHEN 4 THEN cfp.total2_color -- plct.total2_color
          ELSE cfp.total2_black -- plct.total2_black
        END counter_last
      , CASE cfc.plugin_fields_typefielddropdowns_id 
          WHEN 2 THEN t.total2_color
          WHEN 3 THEN t.total2_color
          WHEN 4 THEN t.total2_color
          ELSE t.total2_black
        END counter_use
      , cfp.data_luc -- plct.data_luc
    INTO atc, lc, uc, da, counter_last, counter_use, last_data_luc
    FROM glpi_cartridges c
    LEFT JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.itemtype = 'CartridgeItem' AND cfc.items_id = c.cartridgeitems_id
    LEFT JOIN glpi_plugin_iservice_printers p ON p.id = c.printers_id
    -- LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plct ON plct.printers_id = p.id
    LEFT JOIN glpi_plugin_fields_printercustomfields cfp ON cfp.itemtype = 'Printer' AND cfp.items_id = c.printers_id
    LEFT JOIN glpi_tickets t on t.id = c.tickets_id_use
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
        cfp.data_luc -- plct.data_luc
      , CASE color WHEN 1 THEN cfp.total2_color /* plct.total2_color */ ELSE cfp.total2_black /* plct.total2_black */ END
      , CASE color WHEN 1 THEN cfp.dailycoloraveragefield ELSE cfp.dailybkaveragefield END
    INTO lastDataLuc, lastCounter, dailyAverage
    FROM glpi_plugin_iservice_printers p
    -- LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plct ON plct.printers_id = p.id
    JOIN glpi_plugin_fields_printercustomfields cfp ON cfp.itemtype = 'Printer' AND  cfp.items_id = p.id
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
        CASE cfc.plugin_fields_typefielddropdowns_id 
          WHEN 2 THEN 1
          WHEN 3 THEN 1
          WHEN 4 THEN 1
          ELSE 0
        END
      , CASE cfc.plugin_fields_typefielddropdowns_id 
          WHEN 2 THEN t.total2_color
          WHEN 3 THEN t.total2_color
          WHEN 4 THEN t.total2_color
          ELSE t.total2_black
        END
    INTO color, installedCounter
    FROM glpi_cartridges c
    JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.items_id = c.cartridgeitems_id and cfc.itemtype = 'CartridgeItem'
    JOIN glpi_tickets t on t.id = c.tickets_id_use
    WHERE c.id = cartridgeId;
    
    SELECT 100 *(addition + 1 - (getPrinterCounterEstimate(c.printers_id, color) - installedCounter) / cfc.atcfield)
    INTO percentage
    FROM glpi_cartridges c
    JOIN glpi_cartridgeitems ci on ci.id = c.cartridgeitems_id
    JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.items_id = c.cartridgeitems_id and cfc.itemtype = 'CartridgeItem'
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
    , MIN(CASE color WHEN 1 THEN t.total2_color ELSE t.total2_black + t.total2_color END)
    , MAX(CASE color WHEN 1 THEN t.total2_color ELSE t.total2_black + t.total2_black END)
    , MIN(t.data_luc)
    , MAX(t.data_luc)
  INTO
      ticketCount
    , minCounter
    , maxCounter
    , minDataLuc
    , maxDataLuc
  FROM glpi_tickets t
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
      SELECT MIN(CASE color WHEN 1 THEN t.total2_color ELSE t.total2 END), MAX(CASE color WHEN 1 THEN t.total2_color ELSE t.total2 END), MIN(t.data_luc), MAX(t.data_luc)
      INTO minCounter, maxCounter, minDataLuc, maxDataLuc
      FROM (
        SELECT t.total2_black + t.total2_color total2, t.total2_color, t.data_luc
        FROM glpi_tickets t
        JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer' AND it.items_id = printerId
        WHERE t.is_deleted = 0 AND t.`status` = 6
        ORDER BY t.data_luc DESC
        LIMIT ticketCount
      ) t;
      SET dailyAverage = GREATEST(1, ROUND((maxCounter - minCounter) / DATEDIFF(maxDataLuc, minDataLuc)));
      LEAVE this_proc;
    END IF;
  END IF;
   
  
  -- We calculate how many tickets were in the last minDayCount
  SELECT count(t.id)
  INTO countFirstDays
  FROM glpi_tickets t
  JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer' AND it.items_id = printerId
  WHERE t.is_deleted = 0 AND t.`status` = 6
    AND DATEDIFF(maxDataLuc, t.data_luc) < minDayCount;

  REPEAT
    SET countFirstDays = countFirstDays + 1;
    SELECT MIN(CASE color WHEN 1 THEN t.total2_color ELSE t.total2 END), MAX(CASE color WHEN 1 THEN t.total2_color ELSE t.total2 END), MIN(t.data_luc), MAX(t.data_luc)
    INTO minCounter, maxCounter, minDataLuc, maxDataLuc
    FROM (
      SELECT t.total2_black + t.total2_color total2, t.total2_color, t.data_luc
      FROM glpi_tickets t
      JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer' AND it.items_id = printerId
      WHERE t.is_deleted = 0 AND t.`status` = 6
      ORDER BY t.data_luc DESC
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
