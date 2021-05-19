<?php

use Leads\Classes\APIHandler;
use Leads\Classes\AuthHandler;

function GetAPI($group, $method, $index, $data, $authToken = NULL){

        return APIHandler::get($group, $method, $index, $data, $authToken);
    }

    function handleResponse($response, $callback = NULL){

        return $response;
    }


    function checkFunction($arg){ 
        return $arg;
    }


    // helper function user for user can access resource or not
    function userCan($permission)
    {
        return AuthHandler::can($permission);
    }

    // set variables in sendgrid template and call send email function
    if (! function_exists('sendEmailWithTemplate'))
    {
        /**
         *
         * @param $params
         * @param string $replacementOpen
         * @param string $replacementClose
         * @return bool|string
         */
        function sendEmailWithTemplate($params, $replacementOpen = "{{", $replacementClose = "}}")
        {
            if(empty($params['to']['email'])) {
                return "Required Email.";
            }

            if(empty($params['subject']['title'])) {
                return "Required Email Subject.";
            }

            if(empty($params['template']['id'])) {
                return "Required Template ID.";
            }

            $data['params'] = $params;

            $data['replacement_open'] = $replacementOpen;
            $data['replacement_close'] = $replacementClose;

            $response = GetAPI('communication', 'POST', 'sendEmail', $data);
            return ((int) collect($response)->get('statusCode') == 200);
        }
}

    // set variables in sendgrid template and call send email function
    if (! function_exists('sendNotificationAlert'))
    {
        /**
         *
         * @param $params
         * @param string $replacementOpen
         * @param string $replacementClose
         * @return bool|string
         */
        function sendNotificationAlert($notificationData)
        {
            if(!isset($notificationData['permission'])) {
                $notificationData['permission'] = ['notification_administrator'];
            }

            if(!isset($notificationData['type'])) {
                $notificationData['type'] = 'user';
            }

            if(!isset($notificationData['created_at'])) {
                $notificationData['created_at'] = date('Y-m-d H:i:s');
            }

            if(!isset($notificationData['updated_at'])) {
                $notificationData['updated_at'] = date('Y-m-d H:i:s');
            }

            $response = GetAPI('communication', 'POST', 'sendAlert', $notificationData);
            return ((int) collect($response)->get('statusCode') == 200);
        }
}

    // set data as per send grid template required.
    function asJSON($data)
    {
        $json = json_encode($data);
        $json = preg_replace('/(["\]}])([,:])(["\[{])/', '$1$2 $3', $json);
        return $json;
    }

    function asString($data)
    {
        $json = asJSON($data);
        return wordwrap($json, 76, "\n   ");
    }

    /**
     * filtering response by encoding
     */
    if (! function_exists('filterResponse')) {
    	function filterResponse($response) {
    		return json_decode(json_encode(optional($response)['response']), true);
    	}
    }


    /**
     * Limit text to specific length only
     */
    if (! function_exists('limitText'))
    {
        function limitText($text, $limit, $append_dots = false)
        {
            if (strlen($text) > $limit)
            {
                $text = substr($text, 0, $limit);
                if ($append_dots)
                {
                    $text = $text . '...';
                }
            }

            return $text;
        }
    }


    /**
     * Compute the start and end date of some fixed o relative quarter in a specific year.
     * @param mixed $quarter  Integer from 1 to 4 or relative string value:
     *                        'this', 'current', 'previous', 'first' or 'last'.
     *                        'this' is equivalent to 'current'. Any other value
     *                        will be ignored and instead current quarter will be used.
     *                        Default value 'current'. Particulary, 'previous' value
     *                        only make sense with current year so if you use it with
     *                        other year like: get_dates_of_quarter('previous', 1990)
     *                        the year will be ignored and instead the current year
     *                        will be used.
     * @param int $year       Year of the quarter. Any wrong value will be ignored and
     *                        instead the current year will be used.
     *                        Default value null (current year).
     * @param string $format  String to format returned dates
     * @return array          Array with two elements (keys): start and end date.
     */
    if (! function_exists('get_dates_of_quarter'))
    {
        function get_dates_of_quarter($quarter = 'current', $year = null, $format = 'Y-m-d H:i:s')
        {
            if ( !is_int($year) ) {
                $year = (new DateTime)->format('Y');
            }
            $current_quarter = ceil((new DateTime)->format('n') / 3);
            switch (  strtolower($quarter) ) {
                case 'this':
                case 'current':
                    $quarter = ceil((new DateTime)->format('n') / 3);
                    break;

                case 'previous':
                    $year = (new DateTime)->format('Y');
                    if ($current_quarter == 1) {
                        $quarter = 4;
                        $year--;
                    } else {
                        $quarter =  $current_quarter - 1;
                    }
                    break;

                case 'first':
                    $quarter = 1;
                    break;

                case 'last':
                    $quarter = 4;
                    break;

                default:
                    $quarter = (!is_int($quarter) || $quarter < 1 || $quarter > 4) ? $current_quarter : $quarter;
                    break;
            }
            if ( $quarter === 'this' ) {
                $quarter = ceil((new DateTime)->format('n') / 3);
            }
            $start = new DateTime($year.'-'.(3*$quarter-2).'-1 00:00:00');
            $end = new DateTime($year.'-'.(3*$quarter).'-'.($quarter == 1 || $quarter == 4 ? 31 : 30) .' 23:59:59');

            return array(
                'start' => $format ? $start->format($format) : $start,
                'end' => $format ? $end->format($format) : $end,
            );
        }
    }

    // show pricing rule as per provided sheet,
    // billfrequency - metering - price (keys name)
    function getPricingRule($productDetail) {

        $e1Response = $productDetail['price'];

        if(!isset($e1Response) || $e1Response['isValid'] != true) {
            return false;
        }

        $result = '';

        $price = $e1Response['price'];
        $billingFrequency = strtolower($productDetail['billfrequency']);
        $metering = strtolower($productDetail['metering']);

        if($billingFrequency == 'monthly')
        {
            if($metering == 'unit') {
                $result = ($price > 0) ? 'Monthly Fee' : 'Price';
            }
            else if($metering == 'usage'){
                $result = ($price > 0) ? 'Monthly Fee' : 'Monthly - Usage Based';
            }
        }
        else if($billingFrequency == 'annual')
        {
            if($metering == 'unit') {
                $result = ($price > 0) ? 'Annual Fee' : 'Price';
            }
            else if($metering == 'usage'){
                $result = ($price > 0) ? 'Annual Fee' : 'Annually - Usage Based';
            }
        }
        else if($billingFrequency == '3 years')
        {
            if($metering == 'unit') {
                $result = ($price > 0) ? 'Every 3 Years' : 'Price';
            }
            else if($metering == 'usage'){
                $result = ($price > 0) ? 'Every 3 Years' : 'Every 3 Years - Usage Based';
            }
        }
        else if($billingFrequency == 'on demand')
        {
            if($metering == 'unit') {
                $result = ($price > 0) ? 'Price' : 'Price';
            }
        }
        else if($billingFrequency == 'one time')
        {
            if($metering == 'unit') {
                $result = 'One Time';
            }
        }

        if($price == 0 && $metering == 'usage') {

            $trial = strtolower($productDetail['trial']);
            $serviceType = strtolower($productDetail['servicetype']);

            if($trial == 'y') {
                $result = 'No Charge';
            }

            if($serviceType == 'consulting,') {
                $result = 'Per Contact';
            }
        }

        return $result;
    }

    function calculateSubscriptionExpireDate($subscription) {

        if($subscription['end_date'] != '')
        {
            $result['expire_date'] = $subscription['end_date'];
        }
        else
        {
            $oneYearOn = "";
            $start_date = $subscription['start_date'];
            $subscriptionDays = $subscription['service']['subscriptionperiodtext'];

            if($subscriptionDays == "1 Month") {
                $timePeriod = "1 month";
                $oneYearOn = date('Y-m-d',strtotime($start_date . " + ". $timePeriod));
            }
            elseif ($subscriptionDays == "1 Year") {
                $timePeriod = "365 day";
                $oneYearOn = date('Y-m-d',strtotime($start_date . " + ". $timePeriod));
            }
            elseif ($subscriptionDays == "2 Years") {
                $timePeriod = "730 day";
                $oneYearOn = date('Y-m-d',strtotime($start_date . " + ". $timePeriod));
            }
            elseif ($subscriptionDays == "3 Years") {
                $timePeriod = "1095 day";
                $oneYearOn = date('Y-m-d',strtotime($start_date . " + ". $timePeriod));
            }

            $result['expire_date'] = $oneYearOn;
        }

        $result['expire_remaining_days'] = round((strtotime($result['expire_date'])-time()) / (60 * 60 * 24));
        $result['expire_alert'] = ($result['expire_remaining_days'] <= 90 && $subscription['service']['renewtype'] != 'None' && $subscription['service']['renewtype'] != 'Auto-Automatic') ? true : false;

        return $result;
    }

    function getSubscriptionStatusText ($status)
    {
        if($status == 0) {
            return 'Pending';
        }
        else if($status == 1) {
            return 'Active';
        }
        else if($status == 2) {
            return 'Suspended';
        }
        else if($status == 3) {
            return 'Terminated';
        }
        else if($status == 4) {
            return 'Expired';
        }
        else if($status == 5) {
            return 'Cancelled';
        }
        else if($status == 6) {
            return 'Frozen';
        }
    }
    function filter_url($url) {
        $url = preg_replace('~[^\\pL0-9_]+~u', '-', $url);
        $url = trim($url, "-");
        $url = strtolower($url);
        $url = preg_replace('~[^-a-z0-9_]+~', '', $url);
        return $url;
    }


    if (! function_exists('get_global_settings')){
        function get_global_settings($setting = null, $auth_token = null, $authenticated = true){
            if($authenticated)
                $all_settings = optional(filter_response(handleResponse(GetAPI('account', 'GET', 'getSettings', ['gtm' => ''], $auth_token))))['data'];
            else
                $all_settings = optional(filter_response(handleResponse(GetAPI('account', 'GET', 'getSalesOpsEmailFromSetting', [], $auth_token))))['data'];
            $gtm                = array_get_value_by_key_value($all_settings, 'key', 'gtm');
            $sales_ops_email    = array_get_value_by_key_value($all_settings, 'key', 'sales_ops_email');
            $email_code_expiry  = array_get_value_by_key_value($all_settings, 'key', 'email_code_expiry');
            $all_settings = [
                'gtm'               => (($gtm!==-1)?$gtm:null),
                'sales_ops_email'   => (($sales_ops_email!==-1) ? $sales_ops_email : null),
                'email_code_expiry' => (($email_code_expiry!==-1) ? $email_code_expiry : null),
            ];
            if($setting !== null)
                return (isset($all_settings[$setting])) ? $all_settings[$setting] : null;
            return $all_settings;
        }
    }

   /**
 * logic for showing credit card or net term or net term pending message in listing
 * @return mixed
 */
    function getActiveNetTerm(){
    $record = [];
    $record['record_exist'] = false;
    $record['show_credit_card'] = true;
    //if net Term is not submitted yet
     $param['account_id'] = \AuthHandler::company_id();
     $records= filter_response(handleResponse(GetAPI('payment', 'GET', 'getNetTermDetail', $param)));
    if (empty($records['data'])) {
        return $record;
    }
    $record['record_exist'] = true;
    $record['pending_status'] = false;
    $record['active_net_term'] = [];
    //status of last record of net term
    $lastRecord = collect($records['data'])->last();
    //if last record is active show net term detail only
    if ($lastRecord['status'] == 1) {
        $record['show_credit_card'] = false;
        $record['active_net_term'] = $lastRecord;
        //if last record is not active find if active record is present in previous records
    } elseif ($lastRecord['status'] == 0) {
        $collection = collect($records['data']);
        $filtered = $collection->where('status', 1);
        $filtered_record = $filtered->all();
        $record['pending_status'] = true;
        //if active  net term found display net term detail only
        if (!empty($filtered_record)) {
            $record['show_credit_card'] = false;
        }
        $record['active_net_term'] = $filtered_record;
    }
    return $record;
}

    use Illuminate\Http\Request;
    use Component\CatalogComponent\App\Http\Controllers\CatalogController;

    function getPriceCall($skuId, $databaseCall = false)
    {
        $oCatalogController = new CatalogController();

        $request = new Request();
        $request->merge(['skus' => (is_array($skuId)) ? $skuId : [$skuId]]);
        $request->merge(['withDatabaseCall' => $databaseCall]);

        $e1Response = $oCatalogController->getPriceCall($request);

        $response = [
            'skuId' => $skuId,
            'price' => 0,
            'retry' => true,
            'isValid' => false,
            'retrying' => false,
            'from' => 'na',
        ];

        if($e1Response['status_code'] == 200)
        {
            $response = $e1Response['data'];

            if(count($response) == 1) {
                $response = [
                    'skuId' => $response[0]['item'],
                    'price' => $response[0][config('main.e1.price_key')],
                    'retry' => $response[0]['retry'],
                    'isValid' => $response[0]['isValid'],
                    'retrying' => false,
                    'from' => $response[0]['from'],
                ];
            }
        }

        return $response;
    }

    if (! function_exists('logInfo')){
        function logInfo($data = null){
            if(null !== $data && '' !== $data){
                if(is_array($data))
                    $data = json_encode($data);
            }
            \Log::info($data);
        }
    }