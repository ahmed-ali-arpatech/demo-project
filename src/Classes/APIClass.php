<?php
namespace Titus\Beatle; 


use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Hash;
use \Cache;
use Illuminate\Http\UploadedFile;
 
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

class APIClass
{  
    public function sumArray($argum) {
        return array_sum($argum);
    } 

}