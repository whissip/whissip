<?php
/**
 * This is b2evolution's stats config file.
 *
 * @deprecated TODO: It holds now just things that should be move around due to hitlog refactoring.
 *
 * This file sets how b2evolution will log hits and stats
 * Last significant changes to this file: version 1.6
 *
 * @package conf
 */
if( !defined('EVO_CONFIG_LOADED') ) die( 'Please, do not access this page directly.' );


/**
 * Self referers that should not be considered as "real" referers in stats.
 * This should typically include this site and maybe other subdomains of this site.
 *
 * The following substrings will be looked up in the referer http header
 * in order to identify referers to hide in the logs
 *
 * WARNING: you should *NOT* use a slash at the end of simple domain names, as
 * older Netscape browsers will not send these. For example you should list
 * http://www.example.com instead of http://www.example.com/ .
 *
 * @todo move to admin interface (T_basedomains list editor), but use for upgrading
 * @todo handle multiple blog roots.
 * @todo dh> "://$basehost" also matches images.google.com referrer URLs
 *            (which include the basehost)!
 *        Blacklists for Referrers should probably match at the beginning
 *        of the referrer URL (also for $blackList).
 *
 * @global array
 */
$self_referer_list = array(
	'://'.$basehost,			// This line will match all pages from the host of your $baseurl
	'://www.'.$basehost,	// This line will also match www.you_base_host in case you have no www. on your basehost
	'http://localhost',
	'http://127.0.0.1',
);


/**
 * Blacklist: referrers that should not be considered as "real" referers in stats.
 * This should typically include stat services, online email services, online aggregators, etc.
 *
 * The following substrings will be looked up in the referer http header
 * in order to identify referers to hide in the logs
 *
 * THIS IS NOT FOR SPAM! Use the Antispam features in the admin section to control spam!
 *
 * WARNING: you should *NOT* use a slash at the end of simple domain names, as
 * older Netscape browsers will not send these. For example you should list
 * http://www.example.com instead of http://www.example.com/ .
 *
 * @todo move to admin interface (T_basedomains list editor), but use for upgrading
 *
 * @global array
 */
$blackList = array(
	// webmails
	'.mail.yahoo.com/',
	'//mail.google.com/',
	'//webmail.aol.com/',
	// stat services
	'sitemeter.com/',
	// aggregators
	'bloglines.com/',
	// caches
	'/search?q=cache:',		// Google cache
	// redirectors
	'googlealert.com/',
	// add your own...
);



/**
 * Search engines for statistics
 *
 * The following substrings will be looked up in the referer http header
 * in order to identify search engines
 *
 * @todo move to admin interface (specific list editor), include query params
 *
 * @global array $search_engines
 */
$search_engines = array(
	'//www.google.',
	'ask.com/web', // q=
	'.hotbot.',
	'.altavista.',
	'.excite.',
	'.voila.fr/',
	'http://search',
	'://suche.',
	'search.',
	'search2.',
	'http://recherche',
	'recherche.',
	'recherches.',
	'vachercher.',
	'feedster.com/',
	'alltheweb.com/',
	'daypop.com/',
	'feedster.com/',
	'technorati.com/',
	'weblogs.com/',
	'exalead.com/',
	'killou.com/',
	'buscador.terra.es',
	'web.toile.com',
	'metacrawler.com/',
	'.mamma.com/',
	'.dogpile.com/',
	'search1-1.free.fr',
	'search1-2.free.fr',
	'overture.com',
	'startium.com',
	'2020search.com',
	'bestsearchonearth.info',
	'mysearch.com',
	'popdex.com',
	'64.233.167.104',
	'seek.3721.com',
	'http://netscape.',
	'http://www.netscape.',
	'/searchresults/',
	'/websearch?',
	'http://results.',
	'baidu.com/',
	'reacteur.com/',
	'http://www.lmi.fr/',
	'kartoo.com/',
	'icq.com/search',
	'alexa.com/search',
	'att.net/s/', // string=
	'blingo.com/search',  //q=
	'crawler.com/search/',	// q
	'inbox.com/search/', // q
	'scroogle.org/', // GW=
	'cuil.com/',
	'yandex.ru/yandsearch',
	'go.mail.ru/search'
);


/**
 * UserAgent identifiers for logging/statistics
 *
 * The following substrings will be looked up in the user_agent http header
 *
 * @todo move to admin interface (T_useragents list editor)
 *
 * 'type' aggregator currently gets only used to "translate" user agent strings.
 * An aggregator hit gets detected by accessing the feed.
 *
 * @global array $user_agents
 */
$user_agents = array(
	// Robots:
	array('robot', 'Googlebot/', 'Google (Googlebot)' ),
	array('robot', 'Slurp/', 'Inktomi (Slurp)' ),
	array('robot', 'Yahoo! Slurp;', 'Yahoo (Slurp)' ),
	array('robot', 'msnbot/', 'MSN Search (msnbot)' ),
	array('robot', 'Frontier/',	'Userland (Frontier)' ),
	array('robot', 'ping.blo.gs/', 'blo.gs' ),
	array('robot', 'organica/',	'Organica' ),
	array('robot', 'Blogosphere/', 'Blogosphere' ),
	array('robot', 'blogging ecosystem crawler',	'Blogging ecosystem'),
	array('robot', 'FAST-WebCrawler/', 'Fast' ),			// http://fast.no/support/crawler.asp
	array('robot', 'timboBot/', 'Breaking Blogs (timboBot)' ),
	array('robot', 'NITLE Blog Spider/', 'NITLE' ),
	array('robot', 'The World as a Blog ', 'The World as a Blog' ),
	array('robot', 'daypopbot/ ', 'DayPop' ),
	array('robot', 'Bitacle bot/', 'Bitacle' ),
	array('robot', 'Sphere Scout', 'Sphere Scout' ),
	array('robot', 'Gigabot/', 'Gigablast (Gigabot)' ),
	array('robot', 'Yandex', 'Yandex' ),
	// Unknown robots:
	array('robot', 'psycheclone', 'Psycheclone' ),
	// Aggregators:
	array('aggregator', 'AppleSyndication/', 'Safari RSS (AppleSyndication)' ),
	array('aggregator', 'Feedreader', 'Feedreader' ),
	array('aggregator', 'Syndirella/',	'Syndirella' ),
	array('aggregator', 'rssSearch Harvester/', 'rssSearch Harvester' ),
	array('aggregator', 'Newz Crawler',	'Newz Crawler' ),
	array('aggregator', 'MagpieRSS/', 'Magpie RSS' ),
	array('aggregator', 'CoologFeedSpider', 'CoologFeedSpider' ),
	array('aggregator', 'Pompos/', 'Pompos' ),
	array('aggregator', 'SharpReader/',	'SharpReader'),
	array('aggregator', 'Straw ',	'Straw'),
	array('aggregator', 'YandexBlog', 'YandexBlog'),
);


?>
