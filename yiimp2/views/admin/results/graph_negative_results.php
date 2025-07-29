<?php

use app\models\Stats;

$percent = 16;

$step = 15*60;
$t = time() - 24*60*60;
$stats = Stats::find()->where(['>','time',$t])->orderBy('time')->all();

echo '[[';

foreach($stats as $i=>$n)
{
	$m = round($n->waiting, 8);
	if($i) echo ',';

	$d = date('Y-m-d H:i:s', $n->time);
	echo "[\"$d\",$m]";
}

echo '],[';
foreach($stats as $i=>$n)
{
	$m = round($n->immature, 8);
	if($i) echo ',';

	$d = date('Y-m-d H:i:s', $n->time);
	echo "[\"$d\",$m]";
}

echo ']]';






