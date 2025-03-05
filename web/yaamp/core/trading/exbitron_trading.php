<?php
function doExbitronCancelOrder($OrderID = false) {
	if(!$OrderID) return;

	$params = [ ];

	$res = exbitron_api_user('order/'.$OrderID.'/cancel', $params, 'GET' ,'array');

	if(is_array($res)) {
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market'=>'exbitron', ':uuid'=>$OrderID
		));
		if($db_order) $db_order->delete();
	}
}

function doExbitronTrading($quick=false) {

	$exchange = 'exbitron';
	$updatebalances = true;
	if (exchange_get($exchange, 'disabled')) return;

	$balances_result = exbitron_api_user('balances',[ 'zero' => 'true'],'GET','array');
	//debuglog("exbitron ".var_export($balances,true));
	if (!is_array($balances_result) || $balances_result['status'] != 'OK') return;

	$balances  = $balances_result['data']['user']['currencies'];
	$savebalance = getdbosql('db_balances', "name='$exchange'");
	foreach($balances as $balance) {
		if (strtoupper($balance['id']) == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance['balance'];
				$savebalance->onsell = $balance['lockedBalance'];
				$savebalance->save();
			}
			continue;
		}
		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>strtoupper($balance['id']))
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance['balance'];
				$market->ontrade = $balance['lockedBalance'];
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
	
	// auto trade
	foreach($balances as $balance) {
		$balance_symbol = $balance['id'];
		if (strtoupper($balance_symbol) == 'BTC') continue;

		if (($balance['balance'] == 0) && ($balance['lockedBalance'] == 0)) continue;

		$market_id = strtoupper($balance_symbol .'-BTC');

		// fetch user orders
		$params = [ 'status' => 'open' ];
		$order_result = exbitron_api_user('order/market/'.$market_id, $params, 'GET', 'array' );
		if (!is_array($order_result) || $order_result['status'] != 'OK') continue;

		// fetch order book for marketsummary
		$params = [ ];
		$order_book = exbitron_api_user('orderbook/'.$market_id, $params, 'GET', 'array' );
		if (!is_array($order_book)) continue;

		$marketsummary = [ 'bid' => 0 , 'ask' => 0];
		// find best bid
		foreach ($order_book['bids'] as $tmpbid) {
			if ($tmpbid[0] > $marketsummary['bid']) $marketsummary['bid'] = $tmpbid[0];
		}
		foreach ($order_book['asks'] as $tmpask) {
			if (($marketsummary['ask'] == 0) || ($marketsummary['ask'] > $tmpask[0]))
				$marketsummary['ask'] = $tmpask[0];
		}
		if (!is_array($marketsummary)) continue;

		$coin = getdbosql('db_coins', "symbol=:symbol AND dontsell=0", array(':symbol'=>strtoupper($balance_symbol)));
		if(!$coin) continue;
		$symbol = $coin->symbol;
		if (!empty($coin->symbol2)) $symbol = $coin->symbol2;

		$market = getdbosql('db_markets', "coinid=:coinid AND name='exbitron'", array(':coinid'=>$coin->id));
		if(!$market) continue;
	
		$orders = $order_result['data']['userOrders']['result'];
		if(is_array($orders) && !empty($orders)) {
			foreach($orders as $order) {
				// ignore buy orders
				if(stripos($order['side'], 'sell') === false) continue;
	
				$ask = bitcoinvaluetoa($marketsummary['ask']);
				$sellprice = bitcoinvaluetoa($order['price']);

				// cancel orders not on the wanted ask range
				if($sellprice > $ask*$cancel_ask_pct || $flushall) {
					debuglog("exbitron: cancel order ".$symbol." at $sellprice, ask price is now $ask");
					doExbitronCancelOrder($order['id']);
				}
				// store existing orders
				else
				{
					$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
							':market'=>'exbitron', ':uuid'=>$order['id']
					));
					if($db_order) continue;
	
					// debuglog("exbitron: store order of {$order->Amount} {$symbol} at $sellprice BTC");
					$db_order = new db_orders;
					$db_order->market = 'exbitron';
					$db_order->coinid = $coin->id;
					$db_order->amount = $order['amount'];
					$db_order->price = $sellprice;
					$db_order->ask = $marketsummary['ask'];
					$db_order->bid = $marketsummary['bid'];
					$db_order->uuid = $order['id'];
					$db_order->created = time(); // $order->TimeStamp 2016-03-07T20:04:05.3947572"
					$db_order->save();
				}
			}
		}

		// drop obsolete orders
		$list = getdbolist('db_orders', "coinid={$coin->id} AND market='exbitron'");
		foreach($list as $db_order)
		{
			$found = false;
			if(is_array($orders) && !empty($orders)) {
				foreach($orders as $order) {
					if(stripos($order['side'], 'sell') === false) continue;
					if($order['id'] == $db_order->uuid) {
						$found = true;
						break;
					}
				}
			}
	
			if(!$found) {
				// debuglog("exbitron: delete db order {$db_order->amount} {$coin->symbol} at {$db_order->price} BTC");
				$db_order->delete();
			}
		}

		if($coin->dontsell) continue;

		$market->lasttraded = time();
		$market->save();

		// new orders
		//$amount = floatval($balance->available) - 0.00000001;
		$amount = floatval($balance['balance']);
		if(!$amount) continue;
	
		if($amount*$coin->price < $min_btc_trade) continue;

		$order_precision = 8;

		if($coin->sellonbid) {
			for($i = 0; ($i < 5) && ($amount >= 0); $i++) {
				if(!isset($order_book['bids'][$i])) break;
	
				$nextbuy = $order_book['bids'][$i]; // 0: price , 1: volume
				if($amount*1.1 < $nextbuy[1]) break;
	
				$sellprice = bitcoinvaluetoa($nextbuy[0],$order_precision);
				$sellamount = min($amount, $nextbuy[1]);

				if($sellamount*$sellprice < $min_btc_trade) continue;

				debuglog("exbitron: selling on bid $sellamount $symbol at $sellprice");
				$orderparameters = [ 'market' => strtoupper($symbol).'-btc' ,
									'price' => number_format($sellprice,10) ,
									'side' => 'sell' ,
									'type' => 'limit',
									'amount' => "$amount" ];
				$res = exbitron_api_user('order', $orderparameters , 'POST', 'array');

				if(!is_array($res)) {
					debuglog("exbitron SubmitTrade err: ".json_encode($res));
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

			debuglog("exbitron: selling $amount $symbol at $sellprice");

			$new_order = new stdClass;
			$new_order->market = strtoupper($symbol).'-BTC';
			$new_order->price = (float)$sellprice;
			$new_order->side = 'sell';
			$new_order->type = 'limit';
			$new_order->amount = $amount;
			$orderparameters = json_encode($new_order);

			$res = exbitron_api_user('order', $orderparameters , 'POST', 'array');

			if(!is_array($res) || ($res['status'] != 'OK')) {
				debuglog("exbitron SubmitTrade err: ".json_encode($res));
				continue;
			}

			$db_order = new db_orders;
			$db_order->market = 'exbitron';
			$db_order->coinid = $coin->id;
			$db_order->amount = $amount;
			$db_order->price = $sellprice;
			$db_order->ask = $marketsummary['ask'];
			$db_order->bid = $marketsummary['bid'];
			$db_order->uuid = $res['id'];
			$db_order->created = time();
			$db_order->save();
	}
	
	return;
	
}
