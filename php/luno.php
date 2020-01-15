<?php

namespace ccxt;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use \ccxt\ExchangeError;
use \ccxt\ArgumentsRequired;

class luno extends Exchange {

    public function describe () {
        return array_replace_recursive(parent::describe (), array(
            'id' => 'luno',
            'name' => 'luno',
            'countries' => array( 'GB', 'SG', 'ZA' ),
            'rateLimit' => 1000,
            'version' => '1',
            'has' => array(
                'CORS' => false,
                'fetchTickers' => true,
                'fetchOrder' => true,
                'fetchOrders' => true,
                'fetchOpenOrders' => true,
                'fetchClosedOrders' => true,
                'fetchMyTrades' => true,
                'fetchTradingFee' => true,
                'fetchTradingFees' => true,
            ),
            'urls' => array(
                'referral' => 'https://www.luno.com/invite/44893A',
                'logo' => 'https://user-images.githubusercontent.com/1294454/27766607-8c1a69d8-5ede-11e7-930c-540b5eb9be24.jpg',
                'api' => 'https://api.mybitx.com/api',
                'www' => 'https://www.luno.com',
                'doc' => array(
                    'https://www.luno.com/en/api',
                    'https://npmjs.org/package/bitx',
                    'https://github.com/bausmeier/node-bitx',
                ),
            ),
            'api' => array(
                'public' => array(
                    'get' => array(
                        'orderbook',
                        'orderbook_top',
                        'ticker',
                        'tickers',
                        'trades',
                    ),
                ),
                'private' => array(
                    'get' => array(
                        'accounts/{id}/pending',
                        'accounts/{id}/transactions',
                        'balance',
                        'fee_info',
                        'funding_address',
                        'listorders',
                        'listtrades',
                        'orders/{id}',
                        'quotes/{id}',
                        'withdrawals',
                        'withdrawals/{id}',
                    ),
                    'post' => array(
                        'accounts',
                        'postorder',
                        'marketorder',
                        'stoporder',
                        'funding_address',
                        'withdrawals',
                        'send',
                        'quotes',
                        'oauth2/grant',
                    ),
                    'put' => array(
                        'quotes/{id}',
                    ),
                    'delete' => array(
                        'quotes/{id}',
                        'withdrawals/{id}',
                    ),
                ),
            ),
        ));
    }

    public function fetch_markets ($params = array ()) {
        $response = $this->publicGetTickers ($params);
        $result = array();
        for ($i = 0; $i < count($response['tickers']); $i++) {
            $market = $response['tickers'][$i];
            $id = $market['pair'];
            $baseId = mb_substr($id, 0, 3 - 0);
            $quoteId = mb_substr($id, 3, 6 - 3);
            $base = $this->safe_currency_code($baseId);
            $quote = $this->safe_currency_code($quoteId);
            $symbol = $base . '/' . $quote;
            $result[] = array(
                'id' => $id,
                'symbol' => $symbol,
                'base' => $base,
                'quote' => $quote,
                'baseId' => $baseId,
                'quoteId' => $quoteId,
                'info' => $market,
            );
        }
        return $result;
    }

    public function fetch_balance ($params = array ()) {
        $this->load_markets();
        $response = $this->privateGetBalance ($params);
        $wallets = $this->safe_value($response, 'balance', array());
        $result = array( 'info' => $response );
        for ($i = 0; $i < count($wallets); $i++) {
            $wallet = $wallets[$i];
            $currencyId = $this->safe_string($wallet, 'asset');
            $code = $this->safe_currency_code($currencyId);
            $reserved = $this->safe_float($wallet, 'reserved');
            $unconfirmed = $this->safe_float($wallet, 'unconfirmed');
            $balance = $this->safe_float($wallet, 'balance');
            $account = $this->account ();
            $account['used'] = $this->sum ($reserved, $unconfirmed);
            $account['total'] = $this->sum ($balance, $unconfirmed);
            $result[$code] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $method = 'publicGetOrderbook';
        if ($limit !== null) {
            if ($limit <= 100) {
                $method .= 'Top'; // get just the top of the orderbook when $limit is low
            }
        }
        $request = array(
            'pair' => $this->market_id($symbol),
        );
        $response = $this->$method (array_merge($request, $params));
        $timestamp = $this->safe_integer($response, 'timestamp');
        return $this->parse_order_book($response, $timestamp, 'bids', 'asks', 'price', 'volume');
    }

    public function parse_order ($order, $market = null) {
        $timestamp = $this->safe_integer($order, 'creation_timestamp');
        $status = ($order['state'] === 'PENDING') ? 'open' : 'closed';
        $side = ($order['type'] === 'ASK') ? 'sell' : 'buy';
        $marketId = $this->safe_string($order, 'pair');
        $symbol = null;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
        }
        if ($market !== null) {
            $symbol = $market['symbol'];
        }
        $price = $this->safe_float($order, 'limit_price');
        $amount = $this->safe_float($order, 'limit_volume');
        $quoteFee = $this->safe_float($order, 'fee_counter');
        $baseFee = $this->safe_float($order, 'fee_base');
        $filled = $this->safe_float($order, 'base');
        $cost = $this->safe_float($order, 'counter');
        $remaining = null;
        if ($amount !== null) {
            if ($filled !== null) {
                $remaining = max (0, $amount - $filled);
            }
        }
        $fee = array( 'currency' => null );
        if ($quoteFee) {
            $fee['cost'] = $quoteFee;
            if ($market !== null) {
                $fee['currency'] = $market['quote'];
            }
        } else {
            $fee['cost'] = $baseFee;
            if ($market !== null) {
                $fee['currency'] = $market['base'];
            }
        }
        $id = $this->safe_string($order, 'order_id');
        return array(
            'id' => $id,
            'datetime' => $this->iso8601 ($timestamp),
            'timestamp' => $timestamp,
            'lastTradeTimestamp' => null,
            'status' => $status,
            'symbol' => $symbol,
            'type' => null,
            'side' => $side,
            'price' => $price,
            'amount' => $amount,
            'filled' => $filled,
            'cost' => $cost,
            'remaining' => $remaining,
            'trades' => null,
            'fee' => $fee,
            'info' => $order,
        );
    }

    public function fetch_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array(
            'id' => $id,
        );
        $response = $this->privateGetOrdersId (array_merge($request, $params));
        return $this->parse_order($response);
    }

    public function fetch_orders_by_state ($state = null, $symbol = null, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $request = array();
        $market = null;
        if ($state !== null) {
            $request['state'] = $state;
        }
        if ($symbol !== null) {
            $market = $this->market ($symbol);
            $request['pair'] = $market['id'];
        }
        $response = $this->privateGetListorders (array_merge($request, $params));
        $orders = $this->safe_value($response, 'orders', array());
        return $this->parse_orders($orders, $market, $since, $limit);
    }

    public function fetch_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        return $this->fetch_orders_by_state (null, $symbol, $since, $limit, $params);
    }

    public function fetch_open_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        return $this->fetch_orders_by_state ('PENDING', $symbol, $since, $limit, $params);
    }

    public function fetch_closed_orders ($symbol = null, $since = null, $limit = null, $params = array ()) {
        return $this->fetch_orders_by_state ('COMPLETE', $symbol, $since, $limit, $params);
    }

    public function parse_ticker ($ticker, $market = null) {
        $timestamp = $this->safe_integer($ticker, 'timestamp');
        $symbol = null;
        if ($market) {
            $symbol = $market['symbol'];
        }
        $last = $this->safe_float($ticker, 'last_trade');
        return array(
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => null,
            'low' => null,
            'bid' => $this->safe_float($ticker, 'bid'),
            'bidVolume' => null,
            'ask' => $this->safe_float($ticker, 'ask'),
            'askVolume' => null,
            'vwap' => null,
            'open' => null,
            'close' => $last,
            'last' => $last,
            'previousClose' => null,
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => $this->safe_float($ticker, 'rolling_24_hour_volume'),
            'quoteVolume' => null,
            'info' => $ticker,
        );
    }

    public function fetch_tickers ($symbols = null, $params = array ()) {
        $this->load_markets();
        $response = $this->publicGetTickers ($params);
        $tickers = $this->index_by($response['tickers'], 'pair');
        $ids = is_array($tickers) ? array_keys($tickers) : array();
        $result = array();
        for ($i = 0; $i < count($ids); $i++) {
            $id = $ids[$i];
            $market = $this->markets_by_id[$id];
            $symbol = $market['symbol'];
            $ticker = $tickers[$id];
            $result[$symbol] = $this->parse_ticker($ticker, $market);
        }
        return $result;
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array(
            'pair' => $market['id'],
        );
        $response = $this->publicGetTicker (array_merge($request, $params));
        return $this->parse_ticker($response, $market);
    }

    public function parse_trade ($trade, $market) {
        // For public $trade data (is_buy === True) indicates 'buy' $side but for private $trade data
        // is_buy indicates maker or taker. The value of "type" (ASK/BID) indicate sell/buy $side->
        // Private $trade data includes ID field which public $trade data does not.
        $orderId = $this->safe_string($trade, 'order_id');
        $takerOrMaker = null;
        $side = null;
        if ($orderId !== null) {
            $side = ($trade['type'] === 'ASK') ? 'sell' : 'buy';
            if ($side === 'sell' && $trade['is_buy']) {
                $takerOrMaker = 'maker';
            } else if ($side === 'buy' && !$trade['is_buy']) {
                $takerOrMaker = 'maker';
            } else {
                $takerOrMaker = 'taker';
            }
        } else {
            $side = $trade['is_buy'] ? 'buy' : 'sell';
        }
        $feeBase = $this->safe_float($trade, 'fee_base');
        $feeCounter = $this->safe_float($trade, 'fee_counter');
        $feeCurrency = null;
        $feeCost = null;
        if ($feeBase !== null) {
            if ($feeBase !== 0.0) {
                $feeCurrency = $market['base'];
                $feeCost = $feeBase;
            }
        } else if ($feeCounter !== null) {
            if ($feeCounter !== 0.0) {
                $feeCurrency = $market['quote'];
                $feeCost = $feeCounter;
            }
        }
        $timestamp = $this->safe_integer($trade, 'timestamp');
        return array(
            'info' => $trade,
            'id' => null,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $market['symbol'],
            'order' => $orderId,
            'type' => null,
            'side' => $side,
            'takerOrMaker' => $takerOrMaker,
            'price' => $this->safe_float($trade, 'price'),
            'amount' => $this->safe_float($trade, 'volume'),
            // Does not include potential fee costs
            'cost' => $this->safe_float($trade, 'counter'),
            'fee' => array(
                'cost' => $feeCost,
                'currency' => $feeCurrency,
            ),
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array(
            'pair' => $market['id'],
        );
        if ($since !== null) {
            $request['since'] = $since;
        }
        $response = $this->publicGetTrades (array_merge($request, $params));
        $trades = $this->safe_value($response, 'trades', array());
        return $this->parse_trades($trades, $market, $since, $limit);
    }

    public function fetch_my_trades ($symbol = null, $since = null, $limit = null, $params = array ()) {
        if ($symbol === null) {
            throw new ArgumentsRequired($this->id . ' fetchMyTrades requires a $symbol argument');
        }
        $this->load_markets();
        $market = $this->market ($symbol);
        $request = array(
            'pair' => $market['id'],
        );
        if ($since !== null) {
            $request['since'] = $since;
        }
        if ($limit !== null) {
            $request['limit'] = $limit;
        }
        $response = $this->privateGetListtrades (array_merge($request, $params));
        $trades = $this->safe_value($response, 'trades', array());
        return $this->parse_trades($trades, $market, $since, $limit);
    }

    public function fetch_trading_fees ($params = array ()) {
        $this->load_markets();
        $response = $this->privateGetFeeInfo ($params);
        return array(
            'info' => $response,
            'maker' => $this->safe_float($response, 'maker_fee'),
            'taker' => $this->safe_float($response, 'taker_fee'),
        );
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $this->load_markets();
        $method = 'privatePost';
        $request = array(
            'pair' => $this->market_id($symbol),
        );
        if ($type === 'market') {
            $method .= 'Marketorder';
            $request['type'] = strtoupper($side);
            if ($side === 'buy') {
                $request['counter_volume'] = $amount;
            } else {
                $request['base_volume'] = $amount;
            }
        } else {
            $method .= 'Postorder';
            $request['volume'] = $amount;
            $request['price'] = $price;
            $request['type'] = ($side === 'buy') ? 'BID' : 'ASK';
        }
        $response = $this->$method (array_merge($request, $params));
        return array(
            'info' => $response,
            'id' => $response['order_id'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        $this->load_markets();
        $request = array(
            'order_id' => $id,
        );
        return $this->privatePostStoporder (array_merge($request, $params));
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'] . '/' . $this->version . '/' . $this->implode_params($path, $params);
        $query = $this->omit ($params, $this->extract_params($path));
        if ($query) {
            $url .= '?' . $this->urlencode ($query);
        }
        if ($api === 'private') {
            $this->check_required_credentials();
            $auth = $this->encode ($this->apiKey . ':' . $this->secret);
            $auth = base64_encode($auth);
            $headers = array( 'Authorization' => 'Basic ' . $this->decode ($auth) );
        }
        return array( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (is_array($response) && array_key_exists('error', $response)) {
            throw new ExchangeError($this->id . ' ' . $this->json ($response));
        }
        return $response;
    }
}
