<?php
namespace Titus\Beatle\Facades; 

use Illuminate\Support\Facades\Facade;

class APIFacade extends Facade {
	protected static function getFacadeAccessor() { 
		return 'APIHandler';
	} 
}