<?php
/**
 * CRONJOB cleanup script
 * @license GNU GPLv3 http://opensource.org/licenses/gpl-3.0.html
 * @package Kinokpk.com releaser
 * @author ZonD80 <admin@kinokpk.com>
 * @copyright (C) 2008-now, ZonD80, Germany, TorrentsBook.com, hander
 * @link http://dev.kinokpk.com
 */
header("Content-Type: image/gif");

@set_time_limit(0);
@ignore_user_abort(1);
date_default_timezone_set('UTC');

define ("IN_TRACKER",true);
define ("ROOT_PATH",dirname(__FILE__).'/');
require_once(ROOT_PATH.'include/secrets.php');
require_once(ROOT_PATH.'include/classes.php');
require_once(ROOT_PATH.'include/functions.php');
$time = time();

// connection closed
/* @var database object */
require_once(ROOT_PATH . 'classes/database/database.class.php');
$REL_DB = new REL_DB($db);
unset($db);

$REL_CONFIGrow = $REL_DB->query("SELECT * FROM cache_stats WHERE cache_name IN ('sitename','defaultbaseurl','siteemail','default_language','smtptype')");

while ($REL_CONFIGres = mysql_fetch_assoc($REL_CONFIGrow)) $REL_CONFIG[$REL_CONFIGres['cache_name']] = $REL_CONFIGres['cache_value'];
$REL_CONFIG['lang'] = $REL_CONFIG['default_language'];

/* @var object general cache object */
require_once(ROOT_PATH . 'classes/cache/cache.class.php');
$REL_CACHE=new Cache();
if (REL_CACHEDRIVER=='native') {
	require_once(ROOT_PATH .  'classes/cache/fileCacheDriver.class.php');
	$REL_CACHE->addDriver(NULL, new FileCacheDriver());
}
elseif (REL_CACHEDRIVER=='memcached') {
	require_once(ROOT_PATH .  'classes/cache/MemCacheDriver.class.php');
	$REL_CACHE->addDriver(NULL, new MemCacheDriver());
}

/* @var object links parser/adder/changer for seo */
require_once(ROOT_PATH . 'classes/seo/seo.class.php');
$REL_SEO = new REL_SEO();

/* @var object language system */
require_once(ROOT_PATH . 'classes/lang/lang.class.php');
$REL_LANG = new REL_LANG($REL_CONFIG);

$cronrow = $REL_DB->query("SELECT * FROM cron WHERE cron_name IN ('in_cleanup','last_cleanup','autoclean_interval','max_dead_torrent_time','pm_delete_sys_days','pm_delete_user_days','signup_timeout','ttl_days','delete_votes','rating_freetime','rating_enabled','rating_perleech','rating_perseed','rating_checktime','rating_dislimit','promote_rating','rating_max')") or sqlerr(__FILE__,__LINE__);

while ($cronres = mysql_fetch_assoc($cronrow)) $REL_CRON[$cronres['cron_name']] = $cronres['cron_value'];

if ($REL_CRON['in_cleanup']) die('Cleanup already running');
$REL_DB->query("UPDATE cron SET cron_value=".time()." WHERE cron_name='last_cleanup'") or sqlerr(__FILE__,__LINE__);

$REL_DB->query("UPDATE cron SET cron_value=1 WHERE cron_name='in_cleanup'") or sqlerr(__FILE__,__LINE__);

$torrents = array();
$res = $REL_DB->query('SELECT fid,seeders,leechers,mtime FROM xbt_files') or sqlerr(__FILE__,__LINE__);
while ($row = mysql_fetch_assoc($res)) {
	$torrents[$row['fid']] = $row;
}

if ($torrents) {
	foreach ($torrents AS $id=>$torrent) {
		$REL_DB->query("UPDATE trackers SET seeders = ".(int)$torrent['seeders'].", leechers = ".(int)$torrent['leechers'].", lastchecked = {$torrent['mtime']} WHERE torrent = $id AND tracker='localhost'") or sqlerr(__FILE__,__LINE__);
		$ids[] = $id;
	}
}

$REL_DB->query("UPDATE trackers SET seeders=0, leechers=0, lastchecked=$time WHERE tracker='localhost'".($ids?" AND torrent NOT IN (".implode(',',$ids).")":'')) or sqlerr(__FILE__,__LINE__);

$res = $REL_DB->query("SELECT torrent, SUM(seeders) AS seeders, SUM(leechers) AS leechers FROM trackers GROUP BY torrent") or sqlerr(__FILE__,__LINE__);

while ($row = mysql_fetch_assoc($res)) {
	$REL_DB->query("UPDATE torrents SET seeders={$row['seeders']}, leechers={$row['leechers']} WHERE id={$row['torrent']}") or sqlerr(__FILE__,__LINE__);
}
/*	//delete inactive user accounts
 $secs = 31*86400;
 $dt = time() - $secs;
 $maxclass = UC_POWER_USER;
 $res = $REL_DB->query("SELECT id,avatar FROM users WHERE confirmed=1 AND class <= $maxclass AND last_access < $dt AND last_access <> 0") or sqlerr(__FILE__,__LINE__);
 while ($arr = mysql_fetch_assoc($res)) {
 $avatar = $arr['avatar'];
 delete_user($arr['id']);
 @unlink(ROOT_PATH.$avatar);
 }

 */

//Удаляем системные прочтенные сообщения старше n дней
$secs_system = $REL_CRON['pm_delete_sys_days']*86400; // Количество дней
$dt_system = time() - $secs_system; // Сегодня минус количество дней
$REL_DB->query("DELETE FROM messages WHERE sender = 0 AND archived = 0 AND archived_receiver = 0 AND unread = 0 AND added < $dt_system") or sqlerr(__FILE__, __LINE__);
//Удаляем ВСЕ прочтенные сообщения старше n дней
$secs_all = $REL_CRON['pm_delete_user_days']*86400; // Количество дней
$dt_all = time() - $secs_all; // Сегодня минус количество дней
$REL_DB->query("DELETE FROM messages WHERE unread = 0 AND archived = 0 AND archived_receiver = 0 AND added < $dt_all") or sqlerr(__FILE__, __LINE__);


// delete unconfirmed users if timeout.
$deadtime = time() - ($REL_CRON['signup_timeout']*86400);
$res = $REL_DB->query("SELECT id FROM users WHERE confirmed=0 AND last_access < $deadtime") or sqlerr(__FILE__,__LINE__);
if (mysql_num_rows($res) > 0) {
	while ($arr = mysql_fetch_array($res)) {
		delete_user($arr['id']);


	}
}
//отключение предупрежденных пользователей (у тех у кого 5 звезд)
/*$res = $REL_DB->query("SELECT id, username, modcomment FROM users WHERE num_warned > 4 AND enabled = 1 ") or sqlerr(__FILE__,__LINE__);
 $num = mysql_num_rows($res);
 while ($arr = mysql_fetch_assoc($res)) {
 $modcom = sqlesc(date("Y-m-d") . " - Отключен системой (5 и более предупреждений) " . "\n". $arr[modcomment]);
 $REL_DB->query("UPDATE users SET enabled = 0, dis_reason = 'Отключен системой (5 и более предупреждений)' WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
 $REL_DB->query("UPDATE users SET modcomment = $modcom WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
 write_log("Пользователь $arr[username] был отключен системой (5 и более предупреждений)","tracker");
 }
 */

//
//RATING SYSTEM (XBT compatibility by hander)
//
//$users - select applicable users from db
//constants init - set initial parameters for right functionathing
//$xpeers - select only seeding users ($seeding)
//$active - checks whether user is connected to xbt, else rating is not set for him
//$dpeers - select only downloaded releases ($downloaded)
//$per_time - counts what part of full unit will be used (from last cleanup)
//$units - how many units user grep
//$rateup - multiplying $unit and $units we'll get actual rating earning
//
//To meet high accuracy use crontab instead
//

$users = $REL_DB->query("SELECT id, discount, ratingsum, last_checked FROM users WHERE (".time()."-added)>".($REL_CRON['rating_freetime']*86400)." AND users.class<> '5' AND enabled=1");	//Отключение обработки рейтинга для опред пользователей (например отключенных,VIP).
if($users['ratingsum']<$REL_CRON['rating_max']){
	while ($id_users = mysql_fetch_assoc($users)){
		//fix it 'users.class<> ".UC_VIP."'
		//$xpeers = $REL_DB->query("SELECT `active`,`left` FROM xbt_files_users WHERE uid = ".$id_users['id']." ORDER BY active DESC");
		$xpeers = $REL_DB->query("SELECT `left` FROM xbt_files_users WHERE `active`=1 AND uid = ".$id_users['id']." ORDER BY `left` DESC");

		//constants init
		$seeding = '0';
		$downloaded = '0';
		$active = '0';
		unset($die);

		while($xprow = mysql_fetch_assoc($xpeers) and empty($die)){
			$active = '1';			//Определение подключения клиента к системе
			if($xprow['left']=='0'){
				$seeding++;
			} else $die = '1';		// Начисление рейтинга только когда пользователь подключен (DESC, empty($die),$active).
		}

		if($active == '1'){
			$dpeers = $REL_DB->query("SELECT COUNT(1) AS downloaded FROM xbt_files_users LEFT JOIN torrents ON xbt_files_users.fid=torrents.id WHERE torrents.free=0 AND NOT FIND_IN_SET(torrents.freefor,uid) AND uid IN (".$id_users['id'].") AND torrents.owner<>xbt_files_users.uid AND `left`=0 GROUP BY uid");
				while($dprow = mysql_fetch_assoc($dpeers)){
					$downloaded = $dprow['downloaded'];
				}

			//Запись промежуточных данных в базу.
			$REL_DB->query("INSERT INTO users (id,msn,aim) VALUES ({$id_users['id']},{$seeding},{$downloaded}) ON DUPLICATE KEY UPDATE msn = $seeding, aim = $downloaded");

			//Функция вычисления рейтинга
			$per_time = (time() - $REL_CRON['last_cleanup'])/10800;		//Коэффициент на который умножается колличесво баллов c учетом трех часов (прибавляется за время последней очистки).
			$units = ($seeding + $id_users['discount']) / ($downloaded!=0 ? $downloaded : 1);	//Колличество баллов подлежащее прибавлению.
			$units = ($units>=1 ? $units : -$REL_CRON['rating_perleech']);	//Уменьшение рейтинга при надобности.
			$rateup = $REL_CRON['rating_perseed']*$units*$per_time;		//Результат для записи в базу.

			//Change type of ratingsum column from `int` to `DECIMAL (6,3)`.
			$REL_DB->query("UPDATE LOW_PRIORITY users SET ratingsum = CASE WHEN ((ratingsum+$rateup>{$REL_CRON['rating_max']}) AND $rateup>0 AND ratingsum<{$REL_CRON['rating_max']}) THEN {$REL_CRON['rating_max']} WHEN ($rateup>0 AND ratingsum>{$REL_CRON['rating_max']}) THEN ratingsum ELSE ratingsum+$rateup END, last_checked=".time()." WHERE id=".$id_users['id']);

		}

	}
}

	//Manage 'chill' users
	$REL_DB->query("UPDATE users SET enabled=0, dis_reason='Your rating was too low.' WHERE enabled=1 AND ratingsum<".$REL_CRON['rating_dislimit']);
	$REL_DB->query("UPDATE users SET enabled=1, dis_reason='' WHERE enabled=0 AND dis_reason='Your rating was too low.' AND ratingsum>=".$REL_CRON['rating_dislimit']);	

//end rating system
//

//CleanUp 'xbt_announce_log'
$m_time = $time - 300;
$REL_DB->query("DELETE FROM xbt_announce_log WHERE mtime < $m_time");
//end
//
 
 
//remove expired warnings
$now = time();
$modcomment = sqlesc(date("Y-m-d") . " - Предупреждение снято системой по таймауту.\n");
$msg = sqlesc("Ваше предупреждение снято по таймауту. Постарайтесь больше не получать предупреждений и следовать правилам.\n");
$REL_DB->query("INSERT INTO messages (sender, receiver, added, msg, poster) SELECT 0, id, $now, $msg, 0 FROM users WHERE warned=1 AND warneduntil < ".time()." AND warneduntil <> 0") or sqlerr(__FILE__,__LINE__);
$REL_DB->query("UPDATE users SET warned=0, warneduntil = 0, modcomment = CONCAT($modcomment, modcomment) WHERE warned=1 AND warneduntil < ".time()." AND warneduntil <> 0") or sqlerr(__FILE__,__LINE__);

// promote power users
/* MODIFY TO CLASS SYSTEM & XBT
 if ($REL_CRON['rating_enabled']) {
 $msg = sqlesc("Наши поздравления, вы были авто-повышены до ранга <b>Опытный пользовать</b>.");
 $subject = sqlesc("Вы были повышены");
 $modcomment = sqlesc(date("Y-m-d") . " - Повышен до уровня \"".$REL_LANG->say_by_key("class_power_user")."\" системой.\n");
 $REL_DB->query("UPDATE users SET class = ".UC_POWER_USER.", modcomment = CONCAT($modcomment, modcomment) WHERE class = ".UC_USER." AND ratingsum>={$REL_CRON['promote_rating']}") or sqlerr(__FILE__,__LINE__);
 $REL_DB->query("INSERT INTO messages (sender, receiver, added, msg, poster, subject) SELECT 0, id, $now, $msg, 0, $subject FROM users WHERE class = ".UC_USER." AND ratingsum>={$REL_CRON['promote_rating']}") or sqlerr(__FILE__,__LINE__);

 // demote power users
 $msg = sqlesc("Вы были авто-понижены с ранга <b>Опытный пользователь</b> до ранга <b>Пользователь</b> потому-что ваш рейтинг упал ниже <b>+{$REL_CRON['promote_rating']}</b>.");
 $subject = sqlesc("Вы были понижены");
 $modcomment = sqlesc(date("Y-m-d") . " - Понижен до уровня \"".$REL_LANG->say_by_key("class_user")."\" системой.\n");
 $REL_DB->query("INSERT INTO messages (sender, receiver, added, msg, poster, subject) SELECT 0, id, $now, $msg, 0, $subject FROM users WHERE class = 1 AND ratingsum<{$REL_CRON['promote_rating']}") or sqlerr(__FILE__,__LINE__);
 $REL_DB->query("UPDATE users SET class = ".UC_USER.", modcomment = CONCAT($modcomment, modcomment) WHERE class = ".UC_POWER_USER." AND ratingsum<{$REL_CRON['promote_rating']}") or sqlerr(__FILE__,__LINE__);
 }
 // delete old torrents MODIFY TO XBT!
 /*if ($REL_CRON['use_ttl']) {
 $dt = time() - ($REL_CRON['ttl_days'] * 86400);
 $res = $REL_DB->query("SELECT id, name FROM torrents WHERE last_action < $dt") or sqlerr(__FILE__,__LINE__);
 while ($arr = mysql_fetch_assoc($res))
 {
 deletetorrent($arr['id']);
 write_log("Торрент $arr[id] ($arr[name]) был удален системой (старше чем {$REL_CRON['ttl_days']} дней)","torrent");
 }
 }
 */
// session update moved to include/functions.php
if ($REL_CRON['delete_votes']) {
	$secs = $REL_CRON['delete_votes']*60;
	$dt = time() - $secs;
	$REL_DB->query("DELETE FROM ratings WHERE added < $dt");
}
//$REL_CONFIG['defaultbaseurl'] = mysql_result($REL_DB->query("SELECT cache_value FROM cache_stats WHERE cache_name='defaultbaseurl'"),0);

require_once(ROOT_PATH . "include/createsitemap.php");

// sending emails

$emails = $REL_DB->query("SELECT * FROM cron_emails");

while ($message = mysql_fetch_assoc($emails)) {
	if (strpos(',', $message['emails'])) sent_mail('', $message['subject'].' | '.$REL_CONFIG['sitename'], $REL_CONFIG['siteemail'], $message['subject'], $message['body'],$message['emails']);
	else sent_mail($message['emails'], $message['subject'].' | '.$REL_CONFIG['sitename'], $REL_CONFIG['siteemail'], $message['subject'], $message['body']);

}
$REL_DB->query("TRUNCATE TABLE cron_emails");
// delete expiried relgroups subsribes
$REL_DB->query("DELETE FROM rg_subscribes WHERE valid_until<$time AND valid_until<>0");

$REL_DB->query("UPDATE cron SET cron_value=cron_value+1 WHERE cron_name='num_cleaned'");
$REL_DB->query("UPDATE cron SET cron_value=0 WHERE cron_name='in_cleanup'");
//$REL_CACHE->clearCache('system','cat_tags');
print base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==");

?>