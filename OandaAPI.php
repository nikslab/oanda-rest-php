<?php

/*
 * oanda-rest-php
 *
 * PHP Class to talk to Oanda REST API
 * See http://developer.oanda.com/rest-live/introduction/ for details
 *
 * Most but not all calls are implemented but should be easy to add
 * All API call methods start with an "api"
 * Flow: construct with creds -> api call with params -> curlCall -> results converted to array
 * Time format is set to UNIX.
 *
 * History:
 * By Nik Stankovic, 2016-10-12, Version 1.0: only price API
 *                   2016-10-13, Version 1.1: account info, new order, etc CURL incl POST, DELETE
 *                   2016-10-14, Version 1.2: added most api calls including transaction
 */

class OandaAPI {

    private $api;                           // API base URL
    private $token;                         // Oanda Personal Access Token
    private $account;                       // Account to be used for API calls
    private $headers = [                    // Standard headers
        "Content-Type: application/x-www-form-urlencoded",
        "X-Accept-Datetime-Format: UNIX",   // Take this out if you want RFC3339 format
    ];

    /**
     * Sets up an Oanda API client object
     *
     * @author  Nik Stankovic
     *
     * @param array $credentials API credentials, should include api, token, account
     *
     * Will throw an exception if a credential missing
     *
     */
    public function __construct($credentials)
    {

        try {
            $this->api = $credentials['api'];
            $this->token = $credentials['token'];
            $this->account = $credentials['account'];
        } catch (Exception $e) {
            print $e->getMessage() . "\n";
        }

        $this->headers[] = "Authorization: Bearer " . $this->token; // Authentication header

    }

    /**
     * Makes an actual call to the API using Curl
     * Dumb.  Does no checking.
     *
     * @author  Nik Stankovic
     *
     * @param string $method "GET", "POST" or "DELETE"
     * @param array $headers Headers to tack on for the call, including Auth
     * @param string $URL Full URL
     * @param string $post optional data to post, passed on directly so prepare yourself
     *
     * @return JSON decoded API response as an array
     *
     */
    public function curlCall($method, $headers, $URL, $post = "")
    {
        // Curl call
        $ch = curl_init();

        // For all request types
        curl_setopt_array($ch, array(
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ));

        // Particular cases
        switch ($method) {
            case "GET":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                curl_setopt($ch, CURLOPT_POST, false);
                break;
            case "POST":
                $post_data = http_build_query($post);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                break;
            case "DELETE":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_POST, false);
                break;
        }

        $ccc = curl_exec($ch);
        $result = json_decode($ccc, true); // as array!

        return $result;
    }


    /**
     * Gets prices for instruments (currency pairs)
     *
     * @author  Nik Stankovic
     *
     * @param array $instruments array of currency pairs in the format XXX_YYY
     *
     * @returns array of instrument prices, sample JSON response that gets converted
     *          to an array:
     *
     *   {
     *     "prices": [
     *       {
     *         "instrument":"EUR_USD",
     *         "time":"2013-06-21T17:41:04.648747Z",  // time in RFC3339 format
     *         "bid":1.31513,
     *         "ask":1.31528
     *       },
     *       {
     *         "instrument":"USD_JPY",
     *         "time":"2013-06-21T17:49:02.475381Z",
     *         "bid":97.618,
     *         "ask":97.633
     *       },
     *       {
     *         "instrument":"EUR_CAD",
     *         "time":"2013-06-21T17:51:38.063560Z",
     *         "bid":1.37489,
     *         "ask":1.37517,
     *         "status": "halted"    // this response parameter will only appear
     *                               // if the instrument is currently halted on
     *                               // the OANDA platform.
     *       }
     *     ]
     *   }
     *
     */
    public function apiPrices($instruments)
    {
        $endPoint = "/v1/prices?instruments=";

        // Generate instrument list for the URL
        $list = "";
        foreach ($instruments as $instrument) {
            $list .= $instrument . "%2C";
        }

        $result = false; // for example, no instrument list

        if ($list) {
            $fullURL = $this->api . $endPoint . $list;
            $result = $this->curlCall("GET", $this->headers, $fullURL);
        }

        return $result;

    }


    /**
     * Gets information about the account
     *
     * @author  Nik Stankovic
     *
     * @returns an array with info about the account, sample JSON response that gets converted:
     *
     *   {
     *     "accountId" : 8954947,
     *     "accountName" : "Primary",
     *     "balance" : 100000,
     *     "unrealizedPl" : 0,
     *     "realizedPl" : 0,
     *     "marginUsed" : 0,
     *     "marginAvail" : 100000,
     *     "openTrades" : 0,
     *     "openOrders" : 0,
     *     "marginRate" : 0.05,
     *     "accountCurrency" : "USD"
     *   }
     *
     */
    public function apiAccountInfo()
    {
        $endPoint = "/v1/accounts/" . $this->account;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("GET", $this->headers, $fullURL);

        return $result;
    }

    /************************************ ORDERS ************************************/

    /**
     * Creates a new buy or sell order on Oanda platform
     * http://developer.oanda.com/rest-live/orders/#createNewOrder
     *
     * @author  Nik Stankovic
     *
     * @param array $order Order data as below:
     *
     * REQUIRED PARAMETERS:
     * instrument: Required Instrument to open the order on.
     * units: Required The number of units to open order for.
     * side: Required Direction of the order, either ‘buy’ or ‘sell’.
     * type: Required The type of the order ‘limit’, ‘stop’, ‘marketIfTouched’ or ‘market’.
     * expiry: Required If order type is ‘limit’, ‘stop’, or ‘marketIfTouched’. The order
     *         expiration time in UTC. The value specified must be in a valid datetime format.
     * price: Required If order type is ‘limit’, ‘stop’, or ‘marketIfTouched’. The price where the *        order is set to trigger at.
     *
     * OPTIONAL PARAMETERS:
     * lowerBound: Optional The minimum execution price.
     * upperBound: Optional The maximum execution price.
     * stopLoss: Optional The stop loss price.
     * takeProfit: Optional The take profit price.
     * trailingStop: Optional The trailing stop distance in pips, up to one decimal place.
     *
     * @returns an array with info about the account, sample JSON response that gets converted:
     *
     *   {
     *     "accountId" : 8954947,
     *     "accountName" : "Primary",
     *     "balance" : 100000,
     *     "unrealizedPl" : 0,
     *     "realizedPl" : 0,
     *     "marginUsed" : 0,
     *     "marginAvail" : 100000,
     *     "openTrades" : 0,
     *     "openOrders" : 0,
     *     "marginRate" : 0.05,
     *     "accountCurrency" : "USD"
     *   }
     *
     */
    public function apiNewOrder($order)
    {
        $endPoint = $endPoint = "/v1/accounts/" . $this->account . "/orders";
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("POST", $this->headers, $fullURL, $order);

        return $result;
    }


    /**
     * List orders
     * http://developer.oanda.com/rest-live/orders/#getOrdersForAnAccount
     *
     * @author  Nik Stankovic
     *
     * @param integer $n optional number of results to return, default is 50, max 500
     * @param string $instrument optional One currency pair in the format XXX_YYY
     *
     * @returns array with list of orders, sample JSON response that gets converted:
     *
     *   {
     *     "orders" : [
     *       {
     *         "id" : 175427639,
     *         "instrument" : "EUR_USD",
     *         "units" : 20,
     *         "side" : "buy",
     *         "type" : "marketIfTouched",
     *         "time" : "2014-02-11T16:22:07Z",
     *         "price" : 1,
     *         "takeProfit" : 0,
     *         "stopLoss" : 0,
     *         "expiry" : "2014-02-15T16:22:07Z",
     *         "upperBound" : 0,
     *         "lowerBound" : 0,
     *         "trailingStop" : 0
     *       },
     *       {
     *         "id" : 175427637,
     *         "instrument" : "EUR_USD",
     *         "units" : 10,
     *         "side" : "sell",
     *         "type" : "marketIfTouched",
     *         "time" : "2014-02-11T16:22:07Z",
     *         "price" : 1,
     *         "takeProfit" : 0,
     *         "stopLoss" : 0,
     *         "expiry" : "2014-02-12T16:22:07Z",
     *         "upperBound" : 0,
     *         "lowerBound" : 0,
     *         "trailingStop" : 0
     *       }
     *     ]
     *   }
     *
     */
    public function apiListOrders($count = 50, $instrument = "")
    {
        if ($count > 500) { $count = 500; } // 500 is max
        $count = "count=$count";
        if ($instrument) { $instrument = "&instrument=$instrument"; }

        $endPoint = "/v1/accounts/" . $this->account . "/orders?$count" . $instrument;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("GET", $this->headers, $fullURL);

        return $result;
    }


    /**
     * Details of an order
     * http://developer.oanda.com/rest-live/orders/#getInformationForAnOrder
     *
     * @author  Nik Stankovic
     *
     * @param string $orderId Oanda Order ID
     *
     * @returns array with info about the order, sample JSON response that gets converted:
     *
     *   {
     *     "id" : 43211,                        // The ID of the order
     *     "instrument" : "EUR_USD",            // The symbol of the instrument of the order
     *     "units" : 5,                         // The number of units in the order
     *     "side" : "buy",                      // The direction of the order
     *     "type" : "limit",                    // The type of the order
     *     "time" : "2013-01-01T00:00:00Z",     // The time of the order (in RFC3339 format)
     *     "price" : 1.45123,                   // The price the order was executed at
     *     "takeProfit" : 1.7,                  // The take-profit associated with the order, if any
     *     "stopLoss" : 1.4,                    // The stop-loss associated with the order, if any
     *     "expiry" : "2013-02-01T00:00:00Z",   // The time the order expires (in RFC3339 format)
     *     "upperBound" : 0,                    // The maximum execution price associated with
     *                                             the  order, if any
     *     "lowerBound" : 0,                    // The minimum execution price associated with
     *                                             the order, if any
     *     "trailingStop" : 10                  // The trailing stop associated with the order,
     *                                             if any
     *   }
     *
     *
     */
    public function apiOrderDetail($orderId)
    {
        $endPoint = "/v1/accounts/" . $this->account . "/orders/" . $orderId;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("GET", $this->headers, $fullURL);

        return $result;
    }


    /**
     * Close an order
     * http://developer.oanda.com/rest-live/orders/#closeOrder
     *
     * @author  Nik Stankovic
     *
     * @param string $orderId Oanda Order ID
     *
     * @returns array with info about the order, sample JSON response that gets converted:
     *
     *   {
     *     "id" : 54332,                   // The ID of the close order transaction
     *     "instrument" : "EUR_USD",       // The symbol of the instrument of the order
     *     "units" : 2,
     *     "side" : "sell",
     *     "price" : 1.30601,              // The price at which the order executed
     *     "time" : "1476456244000000"     // The time at which the order executed
     *   }
     *
     */
    public function apiCloseOrder($orderId)
    {
        $endPoint = "/v1/accounts/" . $this->account . "/orders/" . $orderId;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("DELETE", $this->headers, $fullURL);

        return $result;
    }


    /************************************ TRADES ************************************/


    /**
     * List open trades
     * http://developer.oanda.com/rest-live/trades/#getListOpenTrades
     *
     * @author  Nik Stankovic
     *
     * @param integer $n optional number of results to return, default is 50, max 500
     * @param string $instrument optional One currency pair in the format XXX_YYY
     *
     * @returns array with list of trades, sample JSON response that gets converted to an array:
     *
     *   {
     *    "trades" : [
     *      {
     *              "id" : 175427743,
     *              "units" : 2,
     *              "side" : "sell",
     *              "instrument" : "EUR_USD",
     *              "time" : "1476456244000000",
     *              "price" : 1.36687,
     *              "takeProfit" : 0,
     *              "stopLoss" : 0,
     *              "trailingStop" : 0
     *              "trailingAmount" : 0
     *      },
     *      {
     *              "id" : 175427742,
     *              "units" : 2,
     *              "side" : "sell",
     *              "instrument" : "EUR_USD",
     *              "time" : "1476466244000000",
     *              "price" : 1.36687,
     *              "takeProfit" : 0,
     *              "stopLoss" : 0,
     *              "trailingStop" : 0,
     *              "trailingAmount" : 0,
     *      }
     *    ]
     *  }
     *
     */
    public function apiListOpenTrades($count = 50, $instrument = "")
    {
        if ($count > 500) { $count = 500; } // 500 is max
        $count = "count=$count";
        if ($instrument) { $instrument = "&instrument=$instrument"; }

        $endPoint = "/v1/accounts/" . $this->account . "/trades?$count" . $instrument;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("GET", $this->headers, $fullURL);

        return $result;
    }


    /**
     * Get information on a specific trade
     * http://developer.oanda.com/rest-live/trades/#getInformationSpecificTrade
     *
     * @author  Nik Stankovic
     *
     * @param string $tradeId Oanda trade
     *
     * @returns array with info about the trade, sample JSON response that gets converted to an
     *                array:
     *
     *   {
     *     "id" : 43211,                        // The ID of the order
     *     "instrument" : "EUR_USD",            // The symbol of the instrument of the order
     *     "units" : 5,                         // The number of units in the order
     *     "side" : "buy",                      // The direction of the order
     *     "type" : "limit",                    // The type of the order
     *     "time" : "1476456244000000",         // The time of the order
     *     "price" : 1.45123,                   // The price the order was executed at
     *     "takeProfit" : 1.7,                  // The take-profit associated with the order, if any
     *     "stopLoss" : 1.4,                    // The stop-loss associated with the order, if any
     *     "expiry" : "1476456544000000",       // The time the order expires
     *     "upperBound" : 0,                    // The maximum execution price associated with
     *                                             the  order, if any
     *     "lowerBound" : 0,                    // The minimum execution price associated with
     *                                             the order, if any
     *     "trailingStop" : 10                  // The trailing stop associated with the order,
     *                                             if any
     *   }
     *
     *
     */
    public function apiTradeDetail($tradeId)
    {
        $endPoint = "/v1/accounts/" . $this->account . "/trades/" . $tradeId;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("GET", $this->headers, $fullURL);

        return $result;
    }


    /**
     * Close an open trade
     * http://developer.oanda.com/rest-live/orders/#closeOrder
     *
     * @author  Nik Stankovic
     *
     * @param string $tradeId Oanda trade ID
     *
     * @returns array with info about the trade, sample JSON response that gets converted to an
     *                array:
     *
     *   {
     *     "id" : 54332,                   // The ID of the close trade transaction
     *     "price" : 1.30601,              // The price the trade was closed at
     *     "instrument" : "EUR_USD",       // The symbol of the instrument of the trade
     *     "profit" :  0.005,              // The realized profit of the trade in units of base
     *                                        currency
     *     "side" : "sell"                 // The direction the trade was in
     *     "time" : "1476456544000000"     // The time at which the trade was closed
     *   }
     *
     */
    public function apiCloseTrade($tradeId)
    {
        $endPoint = "/v1/accounts/" . $this->account . "/trades/" . $orderId;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("DELETE", $this->headers, $fullURL);

        return $result;
    }



    /************************************ POSITIONS ************************************/

    /**
     * List open positions, all or for an instrument
     * http://developer.oanda.com/rest-live/positions/
     *
     * @author  Nik Stankovic
     *
     * @param string $instrument one instrument for which listing is wanted, if left blank will
     *                           list positions for all instruments
     *
     * @returns array with info about positions, sample JSON response that gets converted:
     *
     *   {
     *     "positions" : [
     *       {
     *         "instrument" : "EUR_USD",
     *         "units" : 4741,
     *         "side" : "buy",
     *         "avgPrice" : 1.3626
     *       },
     *       {
     *         "instrument" : "USD_CAD",
     *         "units" : 30,
     *         "side" : "sell",
     *         "avgPrice" : 1.11563
     *       },
     *       {
     *         "instrument" : "USD_JPY",
     *         "units" : 88,
     *         "side" : "buy",
     *         "avgPrice" : 102.455
     *       }
     *     ]
     *   }
     *
     */
    public function apiPositions($instrument = "")
    {
        $instrument = "/" . $instrument;
        $endPoint = "/v1/accounts/" . $this->account . "/positions" . $instrument;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("GET", $this->headers, $fullURL);

        return $result;
    }


    /************************************ TRANSACTIONS ************************************/


    /**
     * List transactions
     * http://developer.oanda.com/rest-live/transaction-history/#getTransactionHistory
     *
     * @author  Nik Stankovic
     *
     * @param integer $n optional number of results to return, default is 50, max 500
     * @param string $instrument optional One currency pair in the format XXX_YYY
     *
     * @returns array with list of transactions.  Exact fields will depend on the type of
     *                transaciton.  For details of transaction type, see
     *                http://developer.oanda.com/rest-live/transaction-history/#transactionTypes
     *                Most transaction types though contain at least id, account, time and type.
     *
     *   {
     *     "transactions" : [
     *       {
     *         "id" : 175427739,
     *         "accountId" : 6531071,
     *         "time" : "1476456544000000",
     *         "type" : "ORDER_CANCEL",
     *         "orderId" : 175427728,
     *         "reason" : "CLIENT_REQUEST"
     *       }
     *     ]
     *   }
     */
    public function apiListTransactions($count = 50, $instrument = "")
    {
        if ($count > 500) { $count = 500; } // 500 is max
        $count = "count=$count";
        if ($instrument) { $instrument = "&instrument=$instrument"; }

        $endPoint = "/v1/accounts/" . $this->account . "/transactions?$count" . $instrument;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("GET", $this->headers, $fullURL);

        return $result;
    }


    /**
     * Get information for a specific transaction
     * http://developer.oanda.com/rest-live/transaction-history/#getInformationForTransaction
     *
     * @author  Nik Stankovic
     *
     * @param string $transactionId Oanda transaction ID
     *
     * @returns array with trasaction information.  Exact fields will depend on the type of
     *                transaciton.  For details of transaction type, see
     *                http://developer.oanda.com/rest-live/transaction-history/#transactionTypes
     *                Most transaction types though contain at least id, account, time and type.
     *
     * {
     *   "id" : 176403886,
     *   "accountId" : 6765103,
     *   "time" : "2014-04-07T19:20:05Z",
     *   "type" : "STOP_ORDER_CREATE",
     *   "instrument" : "EUR_USD",
     *   [..]
     *
     *
     *
     */
    public function apiTransactionDetail($transactionId)
    {
        $endPoint = "/v1/accounts/" . $this->account . "/transactions/" . $transactionId;
        $fullURL = $this->api . $endPoint;

        $result = $this->curlCall("GET", $this->headers, $fullURL);

        return $result;
    }

}


?>
