<?php

class SaveRates {
    private $OpenExchangeRates_apiKey;
    private $AirTable_apiKey;
    private $AirTable_baseId;
    private $AirTable_table = 'Rates';
    private $divide_rates = ['BTC', 'EUR', 'KYD', 'XAG', 'XAU', 'XPT'];

    public function __construct($OpenExchangeRates_apiKey, $AirTable_apiKey, $AirTable_baseId)
    {
        $this->OpenExchangeRates_apiKey = $OpenExchangeRates_apiKey;
        $this->AirTable_apiKey = $AirTable_apiKey;
        $this->AirTable_baseId = $AirTable_baseId;
    }

    private function getRates()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://openexchangerates.org/api/latest.json?app_id='.$this->OpenExchangeRates_apiKey.'&base=USD&symbols=XAG,XAU,XPT,CAD,KYD,EUR,XCD,BTC');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);

        if (!curl_errno($ch)) {
            switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                case 200:
                    break;
                default:
                    return 'Unexpected HTTP code: ' . $http_code;
            }
        }

        curl_close($ch);
        return $response;
    }

    private function divideRates()
    {
        $api_response = $this->getRates();
        if (is_string($api_response) && is_array(json_decode($api_response, true)) == true) {
            $rates = json_decode($api_response, true)['rates'];
            foreach ($this->divide_rates as $d) {
                $rates[$d] = 1/$rates[$d];
            }
            return $rates;
        } else {
            return 'Incorrect answer from OpenExchangeRates API';
        }
    }

    private function getTable()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.airtable.com/v0/'.$this->AirTable_baseId.'/Rates');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$this->AirTable_apiKey, 'Content-Type: application/json'));
        $response = curl_exec($ch);

        if (!curl_errno($ch)) {
            switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                case 200:
                    break;
                default:
                    return 'Unexpected HTTP code: ' . $http_code;
            }
        }

        curl_close($ch);
        return $response;
    }

    public function sendRates()
    {
        $api_response = $this->getTable();
        if (is_string($api_response) && is_array(json_decode($api_response, true)) == true) {
            $rates = json_decode($api_response, true)['records'];
            $new_rates = $this->divideRates();
            foreach ($rates as $k => $api_rate) {
                $rates[$k]['fields']['Rate'] = $new_rates[$api_rate['fields']['Name']];
                unset($rates[$k]['createdTime']);
            }
            $records = ["records" => $rates];
        } else {
            return 'Incorrect answer from AirTable API';
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://api.airtable.com/v0/'.$this->AirTable_baseId.'/'.$this->AirTable_table);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($records));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$this->AirTable_apiKey, 'Content-Type: application/json'));
        $response = curl_exec($curl);

        if (!curl_errno($curl)) {
            switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                case 200:
                    break;
                default:
                    return 'Unexpected HTTP code: ' . $http_code;
            }
        }

        curl_close($curl);
        return 'ok';
    }
}

$test = new SaveRates('OpenExchangeRates_apiKey', 'AirTable_apiKey', 'baseID');

echo $test->sendRates();



