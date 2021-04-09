CREATE TABLE IF NOT EXISTS `guestbook` (
  `id` int(11) NOT NULL auto_increment,
  `user_ip` int(10) unsigned NOT NULL,
  `user_email` varchar(50) NOT NULL,
  `addtime` int(11) NOT NULL,
  `name` varchar(15) NOT NULL,
  `text` text NOT NULL,
  `home_page` varchar(50),
  `user_browser` varchar(20) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;