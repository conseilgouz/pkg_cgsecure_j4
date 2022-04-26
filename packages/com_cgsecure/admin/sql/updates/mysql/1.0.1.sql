CREATE TABLE IF NOT EXISTS `#__cgsecure_config` (
  `name` varchar(255) NOT NULL,
  `params` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Store any configuration in key => params maps';
CREATE TABLE IF NOT EXISTS `#__cg_rejected_ip` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key', 
	`ip` varchar(255),
	`country` varchar(15),
	`action` varchar(255), 
	`attempt_date` varchar(255),
	PRIMARY KEY(`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
