<pre>
<?
require_once('include/bittorrent.php');
INIT();
$sysopssql = $REL_DB->query("select id from users where class=6");
while (list($id) = mysql_fetch_array($sysopssql)) {
$sysops[] = $id;
}
$sysops = implode(',',$sysops);

$adminssql = $REL_DB->query("select id from users where class=5");
while (list($id) = mysql_fetch_array($adminssql)) {
$admins[] = $id;
}
$admins = implode(',',$admins);

$modssql = $REL_DB->query("select id from users where class=4");
while (list($id) = mysql_fetch_array($modssql)) {
$mods[] = $id;
}
$mods = implode(',',$mods);

$uplssql = $REL_DB->query("select id from users where class=3");
while (list($id) = mysql_fetch_array($uplssql)) {
$upls[] = $id;
}
$upls = implode(',',$upls);

$vipssql = $REL_DB->query("select id from users where class=2");
while (list($id) = mysql_fetch_array($vipsssql)) {
$vips[] = $id;
}
$vips = implode(',',$vips);

$REL_DB->query("UPDATE users SET class=7 where class in (0,1)");
$REL_DB->query("UPDATE users SET class=1 where id in ($sysops)");
$REL_DB->query("UPDATE users SET class=2 where id in ($admins)");
$REL_DB->query("UPDATE users SET class=3 where id in ($mods)");
$REL_DB->query("UPDATE users SET class=4 where id in ($upls)");
$REL_DB->query("UPDATE users SET class=5 where id in ($vips)");
?>