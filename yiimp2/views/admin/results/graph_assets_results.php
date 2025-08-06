<?php

use app\models\Stats;

$percent = 16;

$step = 15*60;
$t = time() - 24*60*60;
$stats = Stats::find()->where(['>','time',$t])->orderBy('time')->all();

echo '[[';
foreach($stats as $i=>$n)
{
	$m = round($n->margin - $n->renters, 8);
	if($i) echo ',';

	$d = date('Y-m-d H:i:s', $n->time);
	echo "[\"$d\",$m]";
}

echo '],[';
foreach($stats as $i=>$n)
{
//	$m = round($n->margin+$n->balances, 8);
	$m = round($n->balances, 8);
	if($i) echo ',';

	$d = date('Y-m-d H:i:s', $n->time);
	echo "[\"$d\",$m]";
}

echo '],[';
foreach($stats as $i=>$n)
{
//	$m = round($n->margin+$n->balances+$n->onsell, 8);
	$m = round($n->onsell, 8);
	if($i) echo ',';

	$d = date('Y-m-d H:i:s', $n->time);
	echo "[\"$d\",$m]";
}

echo '],[';
foreach($stats as $i=>$n)
{
//	$m = round($n->margin+$n->balances+$n->onsell, 8);
	$m = round($n->wallets, 8);
	if($i) echo ',';

	$d = date('Y-m-d H:i:s', $n->time);
	echo "[\"$d\",$m]";
}

echo ']]';
