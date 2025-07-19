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
	`errtype` varchar(1) DEFAULT '',
	PRIMARY KEY(`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `#__cgsecure_config`
SET name='config',params='{"id":"0","components":"com_admin,com_users","logging":"0","report":"0","api_key":"","country":"*","keep":"10","testing":"0","debug":"0","selredir":"LOCAL","redir_ext":"https:\/\/www.google.com","password":"","mode":"0","whitelist":"","multi":"0","multisite":"","subdir":"0","subdirsite":"","htaccess":"0","blockip":"0","blockipv6":"0","blockai":"0","blockhotlink":"0","blockcyrillic":"0","blockgreek":"0","logging_ht":"0","security":"0","blockbad":"0","logging_bad":"0"}'
