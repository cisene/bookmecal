<?php
global $config;
global $lang;

ini_set( 'error_reporting', E_ALL );
ini_set( 'display_errors', 'On' );

// Load config
include_once "config.php";

// Load LM
include_once "class.LanguageManager.php";

$lm = new LanguageManager( $config );

// Start a Session, You might start this somewhere else already.
session_start ();


// Include active language
include( 'lang/lang.' . $_SESSION['lang'] . '.php' );

