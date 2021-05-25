<?php
namespace Titus\Beatle\Facades;

use Illuminate\Support\Facades\Facade;

class AuthHandlerFacade extends Facade{
	protected static function getFacadeAccessor() { 
		return 'AuthHandler';
	} 
}