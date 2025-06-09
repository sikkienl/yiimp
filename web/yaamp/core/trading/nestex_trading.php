<?php
function doNestexCancelOrder($OrderID = false) {
	if (!$OrderID) return;

	$res = nestex_api_user('orders/'.$OrderID, [], 'DELETE', 'array');

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

	$balances = nestex_api_user('wallets/balances', [], 'GET', 'array');
	if (!is_array($balances)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	foreach ($balances as $balance) {
		if (strtoupper($balance['currency']) == 'USDT') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance['available'];
				$savebalance->onsell = $balance['locked'];
				$savebalance->save();
			}
			continue;
		}
		if ($updatebalances) {
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol' => strtoupper($balance['currency']))
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid' => $coin->id));
				if (!$market) continue;
				$market->balance = $balance['available'];
				$market->ontrade = $balance['locked'];
				$market->balancetime = time();
				$market->save();
			}
		}
	}
	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;
	if ($quick) $flushall = false;

	$min_trade = 0.1; // USDT
	$sell_ask_pct = 1.01;
	$cancel_ask_pct = 1.20;

	foreach ($balances as $balance) {
		$symbol = strtoupper($balance['currency']);
		if ($symbol == 'USDT') continue;

		if ($balance['available'] == 0 && $balance['locked'] == 0) continue;

		$pair = $symbol . '-USDT';

		$order_result = nestex_api_user("orders?market=$pair", [], 'GET', 'array');
		if (!is_array($order_result)) continue;

		$book = nestex_api_user("markets/$pair/order-book", [], 'GET', 'array');
		if (!is_array($book)) continue;

		$bid = isset($book['bids'][0]['price']) ? $book['bids'][0]['price'] : 0;
		$ask = isset($book['asks'][0]['price']) ? $book['asks'][0]['price'] : 0;

		if (!$bid || !$ask) continue;

		$coin = getdbosql('db_coins', "symbol=:symbol AND dontsell=0", array(':symbol' => $symbol));
		if (!$coin) continue;

		$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid' => $coin->id));
		if (!$market) continue;

		if (!isset($order_result['orders']) || !is_array($order_result['orders'])) continue;

		foreach ($order_result['orders'] as $order) {
			if (strtolower($order['side']) != 'sell') continue;

			$sellprice = $order['price'];

			if ($sellprice > $ask * $cancel_ask_pct || $flushall) {
				debuglog("nestex: cancel order $symbol at $sellprice, ask is now $ask");
				doNestexCancelOrder($order['id']);
			} else {
				$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
					':market' => 'nestex', ':uuid' => $order['id']
				));
				if ($db_order) continue;

				// Optional: store current valid order
			}
		}
	}
}
