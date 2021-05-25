<?php
namespace Titus\Beatle\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthHandler
{
 
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
        return Session::has('users-resource');
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
