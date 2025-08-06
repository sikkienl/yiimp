<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use app\models\Hashrate;

/* Graph shown in Pool Tab, Last 24 Hours algo hashrate */

$percent = 16;
$algo = Yii::$app->session->get('yaamp-algo');
$factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo); // 1000 sha (GH/s), 1 for normal MH/s

$step = 15*60;
$t = time() - 24*60*60;
$t = intval($t / $step) * $step;
//$stats = getdbolist('db_hashrate', "time >= $t AND algo=:algo ORDER BY time", array(':algo'=>$algo));
$stats = Hashrate::find()
		->where(['algo' => $algo])
		->andWhere(['>=','time',$t])
		->orderBy('time')
		->all();


$tfirst = empty($stats) ? $t : $stats[0]->time;
$averages = array();

for($i = 0; $i < 95-count($stats); $i++) {
	$d = date('Y-m-d H:i:s', $t);
	$averages[] = array($d, 0);
	$t += $step;
	if ($t >= $tfirst) break;
}

foreach($stats as $n)
{
	$r = $n->hashrate/1000000;
	$m = round($r / $factor, 3);

	$d = date('Y-m-d H:i:s', $n->time);

	$averages[] = array($d, $m);
}

if (count($averages) == 1) { $averages[1] = $averages[0]; }

$avg2 = array();
$average = $averages[0][1];
foreach($averages as $n) {
	$average = ($average*(100-$percent) + $n[1]*$percent) / 100;
	$m = round($average, 3);
	$avg2[] = array($n[0], $m);
}

echo '['.json_encode($averages).",\n".json_encode($avg2).']';
