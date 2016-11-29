## oanda-rest-php

Oanda (http://oanda.com) is a leading forex broker enabling you to trade over 90 currency pairs, metals, and CFDs.  

Once you sign-up, you can use Oanda though the web interface or through an API. Using the API you can get real time pricing for all currency pairs, make orders and find out what happened to them, as well get your balance and other account information.  Full API documentaion is here: http://developer.oanda.com/rest-live/introduction/

Per Oanda blurb, using the API you can also create services such as:

* Automated trading strategies in any programming language
* Provide exchange rates for eCommerce companies
* Hedge currency risks for other companies
* Implement high frequency trading algorithms that make money while you sleep
* Build a “Chart Chat” service that combines our chart data with the StockTwits API
* Download Trading Account History to generate performance reports and trading analytics

This library is a PHP class that allows you to easily connect to Oanda REST API.  (They also have a live streaming API, but this class does not work with it).  

The author has no affiliation with Oanda and this software is provided as-is with no claim or guarantee that it is suitable for any particular purpose.  Please read the License.

You will need to replace your credentials in the credentials.json in order to be able to get responses from the API.  You need a Personal Access Token.  In order to do this, once you create and account, login to Oanda web site and go to your Account Management Portal (AMP) on fxTrade and select “Manage API Access” under “Other Actions”.

