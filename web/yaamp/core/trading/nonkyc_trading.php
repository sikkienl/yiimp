<?php
function doNonkycCancelOrder($OrderID = false) {
	if(!$OrderID) return;

	$params = json_encode([ 'id' => $OrderID ]);

	$res = nonkyc_api_user('cancelorder', $params, 'POST' ,'array');

	if(is_array($res)) {
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market'=>'nonkyc', ':uuid'=>$OrderID
		));
		if($db_order) $db_order->delete();
	}
}

function doNonkycTrading($quick=false) {

	$exchange = 'nonkyc';
	$updatebalances = true;
	if (exchange_get($exchange, 'disabled')) return;

	$balances = nonkyc_api_user('balances','','GET','array');
	//debuglog("nonkyc ".var_export($balances,true));
	if (!is_array($balances)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	foreach($balances as $balance) {
		if (strtoupper($balance['asset']) == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance['available'];
				$savebalance->onsell = $balance['held'];
				$savebalance->save();
			}
			continue;
		}
		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>strtoupper($balance['asset']))
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance['available'];
				$market->ontrade = $balance['held'];
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
	
	$marketprices = nonkyc_api_query('tickers','','array');
	if (!is_array($marketprices)) return;

	foreach($marketprices as $singlemarket) {
		if (isset($singlemarket['ticker_id']))	{
			$newmarketprices[$singlemarket['ticker_id']] = $singlemarket;
		}
	}
	$marketprices = $newmarketprices;

	// auto trade
	foreach ($balances as $balance) {
		if (strtoupper($balance['asset']) == 'BTC') continue;
		if (($balance['available'] == 0) && ($balance['held'] == 0)) continue;
		
		$marketsummary = null;
		$tickersymbol = strtoupper($balance['asset'].'_btc');
		if (isset($marketprices[$tickersymbol])) {
			$marketsummary = $marketprices[$tickersymbol];
		}
		if (!is_array($marketsummary)) continue;

		$heldForTrades = $balance['held'];
	
		$coin = getdbosql('db_coins', "symbol=:symbol AND dontsell=0", array(':symbol'=>strtoupper($balance['asset'])));
		if(!$coin) continue;
		$symbol = $coin->symbol;
		if (!empty($coin->symbol2)) $symbol = $coin->symbol2;

		$market = getdbosql('db_markets', "coinid=:coinid AND name='nonkyc'", array(':coinid'=>$coin->id));
		if(!$market) continue;
		$market->balance = $heldForTrades;
	
		$orders = NULL;
		if ($heldForTrades > 0) {
			$params = [
						'symbol' => strtoupper($symbol.'_btc'),
						'status'=> 'active',
						'limit'=> 20,
						'skip'=> 0,
					  ];
			$orders = nonkyc_api_user('getorders', $params, 'GET', 'array' );
		}

		// debuglog("nonkyc ".var_export($orders,true));

		if(is_array($orders) && !empty($orders)) {
			foreach($orders as $order) {
				$tmpsymbol = strtoupper($symbol); $tmpbase = strtoupper('btc');
				if ($tmpsymbol != $symbol) continue;
				if ($tmpbase != 'BTC') continue;
				
				// ignore buy orders
				if(stripos($order['side'], 'sell') === false) continue;
	
				$ask = bitcoinvaluetoa($marketsummary['ask']);
				$sellprice = bitcoinvaluetoa($order['price']);

				// cancel orders not on the wanted ask range
				if($sellprice > $ask*$cancel_ask_pct || $flushall) {
					debuglog("nonkyc: cancel order ".$symbol." at $sellprice, ask price is now $ask");
					doNonkycCancelOrder($order['id']);
				}
				// store existing orders
				else
				{
					$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
							':market'=>'nonkyc', ':uuid'=>$order['id']
					));
					if($db_order) continue;
	
					// debuglog("nonkyc: store order of {$order->Amount} {$symbol} at $sellprice BTC");
					$db_order = new db_orders;
					$db_order->market = 'nonkyc';
					$db_order->coinid = $coin->id;
					$db_order->amount = $order['quantity'];
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
		$list = getdbolist('db_orders', "coinid={$coin->id} AND market='nonkyc'");
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
				// debuglog("nonkyc: delete db order {$db_order->amount} {$coin->symbol} at {$db_order->price} BTC");
				$db_order->delete();
			}
		}
	
		if($coin->dontsell) continue;

		$market->lasttraded = time();
		$market->save();

		// new orders
		//$amount = floatval($balance->available) - 0.00000001;
		$amount = floatval($balance['available']);
		if(!$amount) continue;
	
		if($amount*$coin->price < $min_btc_trade) continue;

		// fetch market configuration
		$query_parameters = 'market/getbysymbol/'.strtoupper($symbol.'_btc');
		$market_configuration = nonkyc_api_query($query_parameters, '', 'array');

		if(!is_array($market_configuration) || empty($market_configuration)) continue;
		$order_precision = $market_configuration['priceDecimals'];

		$orderparameters = 'ticker_id='.strtoupper($symbol.'_btc').'&depth=10';
		$data = nonkyc_api_query('orderbook', $orderparameters, 'array');

		//debuglog("nonkyc ".var_export($data,true));

		if(!is_array($data) || empty($data)) continue;
		if($coin->sellonbid) {
			for($i = 0; ($i < 5) && ($amount >= 0); $i++) {
				if(!isset($data['bids'][$i])) break;
	
				$nextbuy = $data['bids'][$i]; // 0: price , 1: volume
				if($amount*1.1 < $nextbuy[1]) break;
	
				$sellprice = bitcoinvaluetoa($nextbuy[0],$order_precision);
				$sellamount = min($amount, $nextbuy[1]);

				if($sellamount*$sellprice < $min_btc_trade) continue;

				debuglog("nonkyc: selling on bid $sellamount $symbol at $sellprice");
				$orderparameters = [ 'symbol' => strtoupper($balance['asset']).'_btc' ,
								 'price' => number_format($sellprice,10) ,
								 'side' => 'sell' ,
								 'quantity' => "$amount" ];
				$res = nonkyc_api_user('createorder', json_encode($orderparameters) , 'POST', 'array');
	
				if(!is_array($res)) {
					debuglog("nonkyc SubmitTrade err: ".json_encode($res));
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

			debuglog("nonkyc: selling $amount $symbol at $sellprice");

			$orderparameters = [ 'symbol' => strtoupper($balance['asset']).'_btc' ,
								 'price' => number_format($sellprice,10) ,
								 'side' => 'sell' ,
								 'quantity' => "$amount" ];
			$res = nonkyc_api_user('createorder', json_encode($orderparameters) , 'POST', 'array');

			if(!is_array($res)) {
				debuglog("nonkyc SubmitTrade err: ".json_encode($res));
				continue;
			}

			$db_order = new db_orders;
			$db_order->market = 'nonkyc';
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