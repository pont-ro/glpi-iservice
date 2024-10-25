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

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
