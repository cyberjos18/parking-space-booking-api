/*

Source Server         : Localhost (MariaDB)
Source Server Version : 50505
Source Host           : 127.0.0.1:3307
Source Database       : parkimetertest

Target Server Type    : MYSQL
Target Server Version : 50505
File Encoding         : 65001

Date: 2022-10-30 12:56:48
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for bookings
-- ----------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reserveId` varchar(15) NOT NULL,
  `date_beging_reservation` datetime NOT NULL,
  `date_ending_reservation` datetime NOT NULL,
  `parking_id` int(11) NOT NULL,
  `vehicle_registration_number` varchar(15) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reserveId_UNIQUE` (`reserveId`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of bookings
-- ----------------------------
INSERT INTO `bookings` VALUES ('1', '128', '2022-10-30 11:20:20', '2022-10-30 16:00:00', '15', '6312HTR');
