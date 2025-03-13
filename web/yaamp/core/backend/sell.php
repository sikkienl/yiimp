<?php

///////////////////////////////////////////////////////////////////////////////////////////////////////////

function TradingSellCoins()
{
//	debuglog(__FUNCTION__);

	$coins = getdbolist('db_coins', "enable and balance>0 and symbol!='BTC'");
	foreach($coins as $coin) sellCoinToExchange($coin);
}

function sellCoinToExchange($coin)
{
	if($coin->dontsell) return;

	$remote = new WalletRPC($coin);

	$wallet_zaddress = $coin->wallet_zaddress;
	$zbalance = false;
	if (!is_null($wallet_zaddress) && ($wallet_zaddress != '')) {
	    $zbalance = $remote->z_getbalance($wallet_zaddress);
	}

	$info = $remote->getinfo();
	if(!$info || (!$info['balance'] && !$zbalance)) return false;

	if(!empty($coin->symbol2))
	{
		$coin2 = getdbosql('db_coins', "symbol='$coin->symbol2'");
		if(!$coin2) return;

		$amount = $info['balance'] - $info['paytxfee'];
		$amount *= 0.9;

//		debuglog("sending $amount $coin->symbol to main wallet");

		$tx = $remote->sendtoaddress($coin2->master_wallet, $amount);
//		if(!$tx) debuglog($remote->error);

		return;
	}

	$market = getBestMarket($coin);
	if(!$market) return;

	if($market->lastsent != null && $market->lastsent > $market->lasttraded)
	{
//		debuglog("*** not sending $coin->name to $market->name. last tx is late ***");
		return;
	}

	$deposit_address = $market->deposit_address;
	$marketname = $market->name;

	if(empty($deposit_address)) return false;
	$reserved1 = dboscalar("select sum(balance) from accounts where coinid=$coin->id");
	$reserved2 = dboscalar("select sum(amount*price) from earnings
		where status!=2 and userid in (select id from accounts where coinid=$coin->id)");

	if (!isset($info['paytxfee'])) $info['paytxfee'] = 0;

	$reserved = ($reserved1 + $reserved2) * 10;
	$amount = $info['balance'] - $info['paytxfee'] - $reserved;

	if (!is_null($wallet_zaddress) && ($wallet_zaddress != '')) {
	    // move coinbase-balance to z-address
	    if ($amount > $coin->sellthreshold) {
    	    $result = $remote->z_shieldcoinbase('*',$wallet_zaddress);
    	    if (!$result) return;
	    }

		$zamount = $zbalance - $info['paytxfee'] - $reserved;

	    if ($zamount < $coin->sellthreshold)
	    {
	        debuglog("not enough $coin->symbol (zbalance) to sell $zamount < $coin->sellthreshold");
	        return false;
	    }
	    
        $txfee =  0.0001;
        $zaddresses = [['address' => $deposit_address , 'amount' => round(($zamount - $txfee), 8)]];
	    
	    $tx = $remote->z_sendmany($wallet_zaddress, $zaddresses);
	    if(!$tx)
	    {
	        debuglog("sending $zamount $coin->symbol to $deposit_address");
	        debuglog($remote->error);
	        return;
	    }
	    
	}
	else {
	    if ($amount < $coin->sellthreshold)
	    {
	        // debuglog("not enough $coin->symbol to sell $amount < $coin->sellthreshold");
	        return false;
	    }
	    
	    if (($amount > $coin->sellthreshold) && ($amount < $coin->reward/4))
	    {
	        // debuglog("not enough $coin->symbol to sell $amount < $coin->reward /4");
	        return false;
	    }
	}

	$deposit_info = $remote->validateaddress($deposit_address);
	if(!$deposit_info || !isset($deposit_info['isvalid']) || !$deposit_info['isvalid'])
	{
		debuglog("sell invalid address $deposit_address");
		return;
	}

	$amount = round($amount, 8);
//	debuglog("sending $amount $coin->symbol to $marketname, $deposit_address");

//	sleep(1);

	$tx = $remote->sendtoaddress($deposit_address, $amount);
	if(!$tx)
	{
	//	debuglog($remote->error);

		if($coin->symbol == 'DIME')
			$amount = min($amount, 10000000);
		else if($coin->symbol == 'CNOTE')
			$amount = min($amount, 10000);
		else if($coin->symbol == 'SRC')
			$amount = min($amount, 500);
		else
			$amount = round($amount * 0.99, 8);

//		debuglog("sending $amount $coin->symbol to $deposit_address");
		sleep(1);

		$tx = $remote->sendtoaddress($deposit_address, $amount);
		if(!$tx)
		{
			debuglog("sending $amount $coin->symbol to $deposit_address");
			debuglog($remote->error);
			return;
		}
	}
	
	if($tx)
	{
		$market->lastsent = time();
		$market->save();
	}

	 $exchange_deposit = new db_exchange_deposit;
	 $exchange_deposit->market = $marketname;
	 $exchange_deposit->coinid = $coin->id;
	 $exchange_deposit->send_time = time();
	 $exchange_deposit->quantity = $amount;
	 $exchange_deposit->price_estimate = $coin->price;
	 $exchange_deposit->status = 'waiting';
	 $exchange_deposit->tx = $tx;
	 $exchange_deposit->save();

	return;
}


