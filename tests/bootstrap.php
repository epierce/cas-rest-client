<?php

error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('UTC');

define('PROJECT_ROOT', realpath(__DIR__.'/..'));
require_once PROJECT_ROOT.'/vendor/autoload.php';
