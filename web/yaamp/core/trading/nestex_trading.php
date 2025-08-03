<?php
function doNestexCancelOrder($OrderID = false) {
	if (!$OrderID) return;

	$res = nestex_api_user('cancelorder', [ 'order_id' => $OrderID], 'POST', 'array');

	if (is_array($res)) {
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market' => 'nestex', ':uuid' => $OrderID
		));
		if ($db_order) $db_order->delete();
	}
}

function doNestexTrading($quick = false) {

	$exchange = 'nestex';
	$updatebalances = true;
	if (exchange_get($exchange, 'disabled')) return;

	$balances = nestex_api_user('balances', [], 'POST', 'array');
	if ((!is_array($balances)) || (!isset($balances['balances']))) return;

	$marketprices = nestex_api_query('cg/tickers','','array');
	if (!is_array($marketprices)) return;

	$marketsummary = []; $usdt_price = 0;
	foreach($marketprices as $singlemarket) {
		if (isset($singlemarket['target_currency']) && isset($singlemarket['base_currency']) &&
			($singlemarket['target_currency'] == 'USDT'))	{
			$marketsummary[$singlemarket['base_currency']] = $singlemarket;
			if ($singlemarket['base_currency']== 'BTC') {
				$usdt_price = $singlemarket['last_price'];
			}
		}
	}

	$btc_balance = 0; $btc_locked_balance = 0;
	foreach ($balances['balances'] as $currency => $balance) {
		$locked_balance = isset($balances['locked'][$currency])? $balances['locked'][$currency] : 0 ;
		/* balances stored as btc */
		if (strtoupper($currency) == 'BTC') {
			$btc_balance += ($balance - $locked_balance);
			$btc_locked_balance += $locked_balance;
			continue;
		}
		/* as there are only usdt markets on nestex treaded usdt like btc */
		if (strtoupper($currency) == 'USDT') {
			$btc_balance += ($balance - $locked_balance) / $usdt_price;
			$btc_locked_balance += $locked_balance / $usdt_price;
		}

		if ($updatebalances) {
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol' => strtoupper($currency))
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name LIKE '$exchange%'", array(':coinid' => $coin->id));
				if (!$market) continue;
				$market->balance = $balance - $locked_balance;
				$market->ontrade = $locked_balance;
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	if (is_object($savebalance)) {
		$savebalance->balance = $btc_balance;
		$savebalance->onsell = $btc_locked_balance;
		$savebalance->save();
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$ordersbook = nestex_api_user('orders', [], 'POST', 'array' );
	$flushall = rand(0, 8) == 0;
	if ($quick) $flushall = false;

	$min_trade = 0.1; // USDT
	$sell_ask_pct = 1.01;
	$cancel_ask_pct = 1.20;

	foreach ($balances['balances'] as $currency => $balance) {
		$symbol = strtoupper($currency);
		if ($symbol == 'USDT') continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>strtoupper($currency)));
		if(!$coin) continue;

		$market = getdbosql('db_markets', "coinid=:coinid AND name='nestex USDT'", array(':coinid'=>$coin->id));
		if(!$market) continue;
		
		$locked_balance = isset($balances['locked'][$currency])? $balances['locked'][$currency] : 0 ;
		// handle existing orders, only USDT pairs used
		if ((is_array($ordersbook)) && (is_array($ordersbook['orders']))) {
			foreach($ordersbook['orders'] as $order) {
				$ordersymbol = strtoupper($order['cur']);
				if ($ordersymbol != $symbol) continue;
				if (!isset($marketsummary[$symbol])) continue;
				
				// ignore buy orders
				if(stripos($order['order_type'], 'SELL') === false) continue;

				$ask = bitcoinvaluetoa($marketsummary[$symbol]['ask']);
				$sellprice = bitcoinvaluetoa($order['price']);

				// cancel orders not on the wanted ask range
				if($sellprice > $ask*$cancel_ask_pct || $flushall) {
					debuglog("nestex: cancel order ".$symbol." at $sellprice, ask price is now $ask");
					doNestexCancelOrder($order['order_id']);
				}
				// store existing orders
				else 
				{
					$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
							':market'=>'nestex', ':uuid'=>$order['order_id']
					));
					if($db_order) continue;

					// debuglog("nestex: store order of {$order->Amount} {$symbol} at $sellprice BTC");
					$db_order = new db_orders;
					$db_order->market = 'nestex';
					$db_order->coinid = $coin->id;
					$db_order->amount = $order['quantity'];
					$db_order->price = $sellprice;
					$db_order->ask = $marketsummary[$symbol]['ask'];
					$db_order->bid = $marketsummary[$symbol]['bid'];
					$db_order->uuid = $order['order_id'];
					$db_order->created = time(); // $order->TimeStamp 2016-03-07T20:04:05.3947572"
					$db_order->save();
				}
			}
		}

		// drop obsolete orders
		$list = getdbolist('db_orders', "coinid={$coin->id} AND market='nestex'");
		foreach($list as $db_order)
		{
			$found = false;
			if(is_array($ordersbook) && !empty($ordersbook['orders'])) {
				foreach($ordersbook['orders'] as $order) {
					if(stripos($order['order_type'], 'SELL') === false) continue;
					if($order['order_id'] == $db_order->uuid) {
						$found = true;
						break;
					}
				}
			}
	
			if(!$found) {
				// debuglog("nestex: delete db order {$db_order->amount} {$coin->symbol} at {$db_order->price} BTC");
				$db_order->delete();
			}
		}
	
		if($coin->dontsell) continue;

		$market->lasttraded = time();
		$market->save();

		// new orders
		$order_precision = 10;

		$amount = floatval($balance - $locked_balance);
		if(!$amount) continue;
	
		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa(($marketsummary[$symbol]['bid']),$order_precision);
		else
			$sellprice = bitcoinvaluetoa(($marketsummary[$symbol]['ask']  - pow(10,(-$order_precision))),$order_precision); // set below lowest ask price

		if($amount * $sellprice < $min_trade) continue;

		debuglog("nestex: selling $amount $symbol at $sellprice");

		$orderparameters = [ 'cur' => strtoupper($symbol) ,
							 'price' => number_format($sellprice,10) ,
							 'side' => 'SELL' ,
							 'qty' => "$amount" ];
		$res = nestex_api_user('placelimitorder', $orderparameters , 'POST', 'array');

		if ((!is_array($res)) || (isset($res['error']))) {
			debuglog("nestex SubmitTrade err: ".json_encode($res));
			continue;
		}

		$db_order = new db_orders;
		$db_order->market = 'nestex';
		$db_order->coinid = $coin->id;
		$db_order->amount = $amount;
		$db_order->price = $sellprice;
		$db_order->ask = $marketsummary[$symbol]['ask'];
		$db_order->bid = $marketsummary[$symbol]['bid'];
		$db_order->uuid = $res['order_id'];
		$db_order->created = time();
		$db_order->save();
	}
}
