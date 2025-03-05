<?php
function doTradeogreCancelOrder($OrderID = false) {
	if(!$OrderID) return;

	$params = [ 'uuid' => $OrderID ];

	$res = tradeogre_api_user('order/cancel', $params, 'POST' ,'array');
	if(is_array($res)) {
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market'=>'tradeogre', ':uuid'=>$OrderID
		));
		if($db_order) $db_order->delete();
	}
}

function doTradeogreTrading($quick=false) {

	$exchange = 'tradeogre';
	$updatebalances = true;
	if (exchange_get($exchange, 'disabled')) return;

	$balances = tradeogre_api_user('account/balances','','GET','array');
	//debuglog("tradeogre ".var_export($balances,true));
	if (!is_array($balances)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	foreach($balances['balances'] as $symbol => $balance) {
		if (strtoupper($symbol) == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance;
				$savebalance->onsell = 0;
				$savebalance->save();
			}
			continue;
		}
		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>strtoupper($symbol))
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance;
				$market->ontrade = 0;
				$market->balancetime = time();
				$market->save();
			}
		}
	}
	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;
	if($quick) $flushall = false;
	
	$min_btc_trade = 0.000001000; // minimum allowed by the exchange
	$sell_ask_pct = 1.01;        // sell on ask price + 5%
	$cancel_ask_pct = 1.20;      // cancel order if our price is more than ask price + 20%
	
	$marketprices = tradeogre_api_query('markets','','array');
	if (!is_array($marketprices)) return;

	// auto trade
	foreach ($balances['balances'] as $balance_symbol => $balance) {
		if (strtoupper($balance_symbol) == 'BTC') continue;

		if ($balance == 0) continue;

		// fetch balance available
		$params = [
			'currency' => strtoupper($balance_symbol),
		  ];
		$balance_result = tradeogre_api_user('account/balance', $params, 'POST', 'array' );

		if (!is_array($marketprices)) continue;
		$balance = $balance_result['available'];

		$marketsummary = null;
		$tickersymbol = strtoupper($balance_symbol.'-BTC');

		foreach($marketprices as $singlemarket) {
			if (isset($singlemarket[$tickersymbol])) {
				$marketsummary = $singlemarket[$tickersymbol];
			}
		}
		if (!is_array($marketsummary)) continue;

		$coin = getdbosql('db_coins', "symbol=:symbol AND dontsell=0", array(':symbol'=>strtoupper($balance_symbol)));
		if(!$coin) continue;
		$symbol = $coin->symbol;
		if (!empty($coin->symbol2)) $symbol = $coin->symbol2;

		$market = getdbosql('db_markets', "coinid=:coinid AND name='tradeogre'", array(':coinid'=>$coin->id));
		if(!$market) continue;
	
		$params = [
					 'market' => strtoupper($symbol.'-BTC'),
	 			  ];
		$orders = tradeogre_api_user('account/orders', $params, 'POST', 'array' );

		if(is_array($orders) && !empty($orders)) {
			foreach($orders as $order) {
				$tmpsymbol = strtoupper($symbol); $tmpbase = strtoupper('btc');
				if ($tmpsymbol != $symbol) continue;
				if ($tmpbase != 'BTC') continue;
				
				// ignore buy orders
				if(stripos($order['type'], 'sell') === false) continue;
	
				$ask = bitcoinvaluetoa($marketsummary['ask']);
				$sellprice = bitcoinvaluetoa($order['price']);

				// cancel orders not on the wanted ask range
				if($sellprice > $ask*$cancel_ask_pct || $flushall) {
					debuglog("tradeogre: cancel order ".$symbol." at $sellprice, ask price is now $ask");
					doTradeogreCancelOrder($order['uuid']);
				}
				// store existing orders
				else
				{
					$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
							':market'=>'tradeogre', ':uuid'=>$order['uuid']
					));
					if($db_order) continue;
	
					// debuglog("tradeogre: store order of {$order->Amount} {$symbol} at $sellprice BTC");
					$db_order = new db_orders;
					$db_order->market = 'tradeogre';
					$db_order->coinid = $coin->id;
					$db_order->amount = $order['quantity'];
					$db_order->price = $sellprice;
					$db_order->ask = $marketsummary['ask'];
					$db_order->bid = $marketsummary['bid'];
					$db_order->uuid = $order['uuid'];
					$db_order->created = time(); // $order->TimeStamp 2016-03-07T20:04:05.3947572"
					$db_order->save();
				}
			}
		}

		// drop obsolete orders
		$list = getdbolist('db_orders', "coinid={$coin->id} AND market='tradeogre'");
		foreach($list as $db_order)
		{
			$found = false;
			if(is_array($orders) && !empty($orders)) {
				foreach($orders as $order) {
					if(stripos($order['type'], 'sell') === false) continue;
					if($order['uuid'] == $db_order->uuid) {
						$found = true;
						break;
					}
				}
			}
	
			if(!$found) {
				// debuglog("tradeogre: delete db order {$db_order->amount} {$coin->symbol} at {$db_order->price} BTC");
				$db_order->delete();
			}
		}
	
		//if($coin->dontsell) continue;

		$market->lasttraded = time();
		$market->save();

		// new orders
		//$amount = floatval($balance->available) - 0.00000001;
		$amount = floatval($balance);
		if(!$amount) continue;
	
		if($amount*$coin->price < $min_btc_trade) continue;

		$order_precision = 8;

		$orderparameters = 'orders/'.strtoupper($symbol.'-btc');
		$data = tradeogre_api_query($orderparameters, '', 'array');

		if(!is_array($data) || empty($data)) continue;
		if($coin->sellonbid) {
			for($i = 0; ($i < 5) && ($amount >= 0); $i++) {
				if(!isset($data['buy'][$i])) break;
	
				$nextbuy = $data['buy'][$i]; // 0: price , 1: volume
				if($amount*1.1 < $nextbuy[1]) break;
	
				$sellprice = bitcoinvaluetoa($nextbuy[0],$order_precision);
				$sellamount = min($amount, $nextbuy[1]);

				if($sellamount*$sellprice < $min_btc_trade) continue;

				debuglog("tradeogre: selling on bid $sellamount $symbol at $sellprice");
				$orderparameters = [ 'market' => strtoupper($symbol).'-btc' ,
								 'price' => number_format($sellprice,10) ,
								 'duration' => 'GTC' ,
								 'quantity' => "$amount" ];
				$res = tradeogre_api_user('order/sell', json_encode($orderparameters) , 'POST', 'array');
	
				$res = false;
				if(!is_array($res)) {
					debuglog("tradeogre SubmitTrade err: ".json_encode($res));
					continue;
				}
	
				$amount -= $sellamount;
			}
		}
	
		if($amount <= 0) continue;
 
		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa(($marketsummary['bid']),$order_precision);
		else
			$sellprice = bitcoinvaluetoa(($marketsummary['ask']  - pow(10,(-$order_precision))),$order_precision); // set below lowest ask price

			if($amount * $sellprice < $min_btc_trade) continue;

			debuglog("tradeogre: selling $amount $symbol at $sellprice");

			$orderparameters = [ 'market' => strtoupper($symbol).'-btc' ,
								 'price' => number_format($sellprice,10) ,
								 'duration' => 'GTC' ,
								 'quantity' => "$amount" ];
			$res = tradeogre_api_user('order/sell', $orderparameters , 'POST', 'array');

			if(!is_array($res) || ($res['success'] === false)) {
				debuglog("tradeogre SubmitTrade err: ".json_encode($res));
				continue;
			}

			$db_order = new db_orders;
			$db_order->market = 'tradeogre';
			$db_order->coinid = $coin->id;
			$db_order->amount = $amount;
			$db_order->price = $sellprice;
			$db_order->ask = $marketsummary['ask'];
			$db_order->bid = $marketsummary['bid'];
			$db_order->uuid = $res['uuid'];
			$db_order->created = time();
			$db_order->save();
	}
	
	return;
	
}