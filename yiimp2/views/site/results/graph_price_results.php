<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use app\models\Hashrate;

/* Graph shown in Pool tab, last 24h algo estimates */

$percent = 16;
$algo = Yii::$app->session->get('yaamp-algo');

$step = 15*60;
$t = time() - 24*60*60;
$t = intval($t / $step) * $step;
$stats = Hashrate::find()
			->where(['algo' => $algo])
			->andWhere(['>=','time',$t])
			->orderBy('time')
			->all();

$tfirst = empty($stats) ? $t : $stats[0]->time;
$pfirst = empty($stats) ? 0.0 : (double) Yii::$app->ConversionUtils->altcoinvaluetoa($stats[0]->price);
$averages = array();

for($i = 0; $i < 95-count($stats); $i++) {
	$d = date('Y-m-d H:i:s', $t);
	$averages[] = array($d, $pfirst);
	$t += $step;
	if ($t >= $tfirst) break;
}

foreach($stats as $n) {
	$m = (double) Yii::$app->ConversionUtils->altcoinvaluetoa($n->price);
	$d = date('Y-m-d H:i:s', $n->time);
	$averages[] = array($d, $m);
}

$avg2 = array();
$average = $averages[0][1];
foreach($averages as $n) {
	$average = ($average*(100-$percent) + $n[1]*$percent) / 100;
	$m = round($average, 5);

	$avg2[] = array($n[0], $m);
}

echo '['.json_encode($averages).",\n".json_encode($avg2).']';
