<?php

namespace Leads\Classes;

use Illuminate\Http\Request;
use Session;

class AuthHandler
{
    function updateUserData(){

        $sessionData = Session::get('users-resource');
        $authToken = $sessionData['authToken'];
        $params = [
            'authToken' => $authToken
        ];
        // Get user information from API
        $result = GetAPI('users-resource','GET' ,'users', $params, $authToken);
        $response = handleResponse($result);
        // Include user data in session

        $sessionData = array_merge($sessionData, collect($response['response']->data)->toArray());

        /**
         * Adding Account Settings and Logo in User Session Data
         */
        if (!empty($sessionData['company'])) {
            $companyData = collect($sessionData['company']);

            $params['company_id'] = $companyData->get('id');
            $params['organization_id'] = $companyData->get('organization_id');
            $settings_result = GetAPI('account', 'GET', 'getSettings', $params, $authToken);

            if (!empty($settings_result['statusCode']) && $settings_result['statusCode'] == 200) {
                $settings_response = json_decode(json_encode($settings_result['response']->data), true);

                $settings_data = [
                    'allow_more_accounts' => array_get_value_by_key_value($settings_response, 'key', 'allow_more_accounts'),
                    'allow_self_enrollments' => array_get_value_by_key_value($settings_response, 'key', 'allow_self_enrollments')
                ];
                $sessionData['account_settings'] = implode(":", array_values($settings_data));
                $sessionData['account_logo'] = array_get_value_by_key_value($settings_response, 'key', 'logo');
                $sessionData['default_admin'] = array_get_value_by_key_value($settings_response, 'key', 'default_admin');

                // commented  for CCP-2308
                //$sessionData['account_logo_url'] = array_get_value_by_key_value($settings_response, 'key', 'logo', 'url');
            }
        }

        Session::put('users-resource', $sessionData);
    }

    public function login(Request $request)
    {
        $authToken = $request->input('auth_token');
        $params = [
            'authToken' => $authToken
        ];
        // Get user information from API
        $result = GetAPI('users-resource','GET' ,'users', $params, $authToken);
        $response = handleResponse($result);

        // in case api token invalid or expired.
        if($response['statusCode'] != 200)
        {
            $redirectTo = $request->input('redirect_to', $this->getHomePageRoute());
            return redirect($redirectTo);
        }

        $sessionData = [];
        $sessionData['authToken'] = $authToken;
        $sessionData['sessionStartTime'] = time();
        $sessionData['rights'] = [];
        // Include user data in session
        $sessionData += collect($response['response']->data)->toArray();


        // Get user permissions information from API
        $permission_result = GetAPI('users-resource', 'GET', 'permissions', ['userId' => $sessionData['id']],$authToken);
        if ($permission_result['statusCode'] == 200) {
            $sessionData['rights'] = $permission_result['response']->data;
        }
        if($request->assume_role == 1){
            $sessionData['managerAdminName'] = $request->manager_admin_name;
            $sessionData['assumeRole'] = $request->assume_role;
            $sessionData['managerAdminEmail'] = $request->manager_admin_email;
        }

        /**
         * Adding Account Settings and Logo in User Session Data
         */
        if (!empty($sessionData['company'])) {
            $companyData = collect($sessionData['company']);

            $params['company_id'] = $companyData->get('id');
            $params['organization_id'] = $companyData->get('organization_id');
            $settings_result = GetAPI('account', 'GET', 'getSettings', $params, $authToken);
            if (!empty($settings_result['statusCode']) && $settings_result['statusCode'] == 200) {
                $settings_response = json_decode(json_encode($settings_result['response']->data), true);
                $settings_data = [
                    'allow_more_accounts' => array_get_value_by_key_value($settings_response, 'key', 'allow_more_accounts'),
                    'allow_self_enrollments' => array_get_value_by_key_value($settings_response, 'key', 'allow_self_enrollments')
                ];
                $sessionData['account_settings'] = implode(":", array_values($settings_data));
                $sessionData['account_logo'] = array_get_value_by_key_value($settings_response, 'key', 'logo');
                $sessionData['default_admin'] = array_get_value_by_key_value($settings_response, 'key', 'default_admin');
                // commented  for CCP-2308
                //$sessionData['account_logo_url'] = array_get_value_by_key_value($settings_response, 'key', 'logo', 'url');
            }
            //code to get gtm setting only
            $global_settings = get_global_settings(null, $authToken);
            $sessionData['gtm'] = $global_settings['gtm'];
            $sessionData['sales_ops_email'] = $global_settings['sales_ops_email'];
            $sessionData['email_code_expiry'] = $global_settings['email_code_expiry'];
        }

        Session::put('users-resource', $sessionData);
        // Get user redirect to link after login
        $homePageUrl = $this->getHomePageRoute();
        // Check if redirect to is present in query string
        $redirectTo = $request->input('redirect_to', $homePageUrl);

        // Updating AB Data from API
        $data['user_id'] = $result['response']->data->id;
        $data['request_type'] = ['parent','billing'];
        GetAPI('account','POST' ,'updateABData',$data);

        return redirect($redirectTo);
    }

    function getHomePageRoute(){

        $homePageUrl = route(config('main.dashboard_route'));
        $sessionData = Session::get('users-resource');
        if (isset($sessionData['homePageUrl'])){
            switch ($sessionData['homePageUrl']) {
                case '/app/MainPage':
                    $homePageUrl = route(config('main.dashboard_route'));
                    break;
                case '/app/Track':
                    $homePageUrl = route('manage.dashboard');
                    break;
                case '/cbs_order_asp/StandardDisplayCartX.php':
                    $homePageUrl = route('marketplace.standards');
                    break;
            }
        }
        return $homePageUrl;
    }

    /**
     * Get GTM code
     *
     * @return mixed|string
     */

    function getGtm()
    {
        $sessionData = Session::get('users-resource');
       // echo '<pre>';print_r($sessionData);exit();
        $gtmData = collect($sessionData)->get('gtm');

        if($sessionData['authToken']){
            $gtm = (empty(trim($gtmData)) || $gtmData == '-1') ? '' : $gtmData;
        }else{
            $all_settings = optional(filter_response(handleResponse(GetAPI('account', 'GET', 'getGtmSettings', ['gtm' => '']))))['data'];
            $gtm= array_get_value_by_key_value($all_settings, 'key', 'gtm');
        }

        return $gtm;
    }

    function getCompanyLogoUrl()
    {
        $sessionData = Session::get('users-resource');
        $account_logo = collect($sessionData)->get('account_logo');
        $companyLogoUrl = (empty(trim($account_logo)) || $account_logo == '-1') ? '' : $account_logo;

        return $companyLogoUrl;
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $request->session()->invalidate();
        return redirect()->to(config('main.login_page'));
    }

    /**
     * Get session status
     */
    public function check()
    {
        return \Session::has('users-resource');
    }

    /**
     * Expose magic methods
     */
    public function __call($method, $param = [])
    {
    	$session = Session::get('users-resource', []);
        return isset($session[$method]) ? $session[$method] : null;
    }

    function fullName(){
        return $this->first_name() . ' ' . $this->last_name();
    }

    // get latest data from database after update profile
    function nameInitials(){
        return strtoupper($this->first_name()[0] . $this->last_name()[0]);
    }

    /**
     * check user can access resource
     * @param [string]  permission
     * @return  [boolean] user can access or not
     */
    function can($permission)
    {
        $session = Session::get('users-resource');
        $permissions = isset($session['rights']) ? collect(optional(optional(collect($session['rights']))->first())->permissions)->keys()->toArray() : [];
        if(is_array($permissions) && count($permissions) > 0)
            return in_array($permission, $permissions);
        return false;
    }

    function infoUpdateInSession($param) {

        foreach($param as $key => $array) {
            Session::put('users-resource.'.$key, $array);
        }

    }
    /**
     * Get Logged in UserID
     */
    public function userID()
    {
        $session = Session::get('users-resource');
        return isset($session['id']) ? $session['id'] : null;
    }

    /**
     * Get Assume Role Data
     */
    public function getAssumeRole()
    {
        $assume_role_data = [];

        $session = Session::get('users-resource');

        if (!empty($session['assumeRole'])) {
            $assume_role_data['assume_role'] = collect($session)->get('assumeRole');
            $assume_role_data['manager_admin_name'] = collect($session)->get('managerAdminName');
            $assume_role_data['manager_admin_email'] = collect($session)->get('managerAdminEmail');
        }

        return !empty($assume_role_data) ? $assume_role_data : false;
    }

    function getUserRole(){
        $userRole = Session::get('users-resource')['rights'][0];
        return !empty($userRole) ? $userRole->name : '';
    }
    function getDefaultAdminID(){
        $defaultAdmin = Session::get('users-resource');
        return !empty($defaultAdmin) ? $defaultAdmin['default_admin'] : '';
    }
}
