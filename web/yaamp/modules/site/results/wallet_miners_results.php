<?php

$user = getuserparam(getparam('address'));
if(!$user) return;

$userid = intval($user->id);

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Miners: {$user->username}</div>";
echo "<div class='main-left-inner'>";

echo '<table class="dataGrid2">';
echo "<thead>";
echo "<tr>";
echo "<th></th>";
echo "<th>Summary</th>";
echo "<th align=right width=80>Workers</br>(Shared)</th>";
echo "<th align=right>Shares</th>";
echo "<th align=right width=80>Hashrate*</br>(Shared )</th>";
echo "<th align=right width=60>TTF</br>(Shared)</th>";
echo "<th align=right width=80>Workers</br>(Solo)</th>";
echo "<th align=right width=80>Hashrate*</br>(Solo)</th>";
echo "<th align=right width=60>TTF</br>(Solo)</th>";
echo "</tr>";
echo "</thead>";

foreach(yaamp_get_algos() as $algo)
{
	$list = getdbolist('db_coins', "id IN (SELECT DISTINCT coinid FROM shares WHERE userid=$userid AND algo=:algo)", array(':algo' => $algo));
	foreach ($list as $coin)
	{
		if (!YAAMP_ALLOW_EXCHANGE && isset($coin) && $coin->algo != $algo) continue;
		$coinid = $coin->id;

		$name = substr($coin->name, 0, 20);

		$user_shared_rate = yaamp_user_coin_shared_rate($userid, $coinid);
		$user_solo_rate = yaamp_user_coin_solo_rate($userid, $coinid);

		$pool_shared_hash = yaamp_coin_shared_rate($coinid);
		$user_shared_ttf  = $user_shared_rate ? $coin->difficulty * 0x100000000 / $pool_shared_hash : 0;
		$user_shared_ttf  = $user_shared_ttf ? sectoa2($user_shared_ttf) : '';

		$pool_solo_hash = yaamp_coin_solo_rate($coinid);
		$user_solo_ttf  = $user_solo_rate ? $coin->difficulty * 0x100000000 / $pool_solo_hash : 0;
		$user_solo_ttf  = $user_solo_ttf ? sectoa2($user_solo_ttf) : '-';


		$user_shared_rate = $user_shared_rate? Itoa2($user_shared_rate).'h/s': '-';
		$user_solo_rate = $user_solo_rate? Itoa2($user_solo_rate).'h/s': '-';

		$shared_minercount = getdbocount('db_workers', "userid=$userid AND algo=:algo and password not like '%m=solo%'", array(':algo'=>$algo));
		$solo_minercount = getdbocount('db_workers',"algo=:algo and userid=$userid and password like '%m=solo%'",array(':algo'=>$algo));

		$user_shared_shares = controller()->memcache->get_database_scalar("wallet_user_shared_shares-$algo-$coinid-$userid",
			"SELECT SUM(difficulty) FROM shares WHERE valid AND userid=$userid AND coinid=$coinid AND algo=:algo AND solo=0", array(':algo'=>$algo));
		if(!$user_shared_shares) continue;

		$total_shared_shares = controller()->memcache->get_database_scalar("wallet_coin_shared_shares-$coinid",
			"SELECT SUM(difficulty) FROM shares WHERE valid AND coinid=$coinid AND algo=:algo AND solo=0", array(':algo'=>$algo));

		if(!$total_shared_shares) continue;
		$percent_shared_shares = round($user_shared_shares * 100 / $total_shared_shares, 2);
		$percent_shared_shares = $percent_shared_shares? Itoa2($percent_shared_shares).'%': '-';

		echo '<tr class="ssrow">';
		echo '<td width="18px"><img width="16px" src="'.$coin->image.'"></td>';
		echo '<td class="row"><b class="row">'.$name.'</b> ('.$algo.')</td>';
		echo '<td align="right" >'.$shared_minercount.'</td>';
		echo '<td align="right" width="100">'.$percent_shared_shares.'</td>';
		echo '<td align="right" width="100"><b>'.$user_shared_rate.'</b></td>';
		echo '<td align="right" width="100">'.$user_shared_ttf.'</td>';
		echo '<td align="right" >'.$solo_minercount.'</td>';
		echo '<td align="right" width="100"><b>'.$user_solo_rate.'</b></td>';
		echo '<td align="right" width="100">'.$user_solo_ttf.'</td>';
		echo '</tr>';
	}
}

echo "</table>";
////////////////////////////////////////////////////////////////////////////////

$workers = getdbolist('db_workers', "userid=$user->id order by password");
if(count($workers))
{
	echo "<br>";
	echo "<table  class='dataGrid2'>";
	echo "<thead>";
	echo "<tr>";
	echo "<th align=left>Details</th>";
	if ($this->admin) echo "<th>IP</th>";
	echo "<th align=left>Password</th>";
	echo "<th align=left>Algo</th>";
	echo "<th align=right>Diff</th>";
	echo "<th align=right title='extranonce.subscribe'>ES**</th>";
	echo "<th align=right width=80>Hashrate*</th>";
	echo "<th align=right width=60>Shares/Min*</th>";
	echo "</tr>";
	echo "</thead>";

	foreach($workers as $worker)
	{
		$user_rate1 = yaamp_worker_rate($worker->id, $worker->algo);
		
		$user_rate1 = $user_rate1? Itoa2($user_rate1).'h/s': '';

		$version = substr($worker->version, 0, 25);
		$password = substr($worker->password, 0, 35);
		
		$name = $worker->worker;

		$subscribe = Booltoa($worker->subscribe);

		$t = time() - 60;
		$shares_per_minute = getdbocount('db_shares',"algo=:algo and userid=$user->id and workerid=$worker->id and time>=$t",array(':algo'=>$worker->algo));

		echo '<tr class="ssrow">';
		echo '<td title="'.$worker->version.'"><b>Version:</b> '.$version.' <br> <b>Worker Name:</b> '.$name.' </td>';
		if ($this->admin) echo "<td>{$worker->ip}</td>";
		echo '<td title="'.$worker->password.'">'.$password.'</td>';
		echo '<td>'.$worker->algo.'</td>';
		echo '<td align="right">'.$worker->difficulty.'</td>';
		echo '<td align="right">'.$subscribe.'</td>';
		echo '<td align="right">'.$user_rate1.'</td>';
		echo '<td align="center" title="">'.$shares_per_minute.'</td>';
		echo '</tr>';
	}

	echo "</table>";
}

echo "</div>";

echo "<p style='font-size: .8em'>
		&nbsp;* approximate from the last 5 minutes submitted shares<br>
		&nbsp;** extranonce.subscribe<br>
		</p>";

echo "</div><br>";








