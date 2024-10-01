<?php

// Language Manager

class LanguageManager {

  public function __destruct () {}

  public function __construct ($config) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_start();
    }

    // Check if we have the lang variable already set?
    if (isset($_SESSION['lang']) === false) {

      // Default language is English
      $_SESSION['lang'] = 'en';
    }

    // Check if we got lang variable in query string
    if (isset($_GET['lang'])) {
      if ($_GET['lang'] != '') {
        // TODO: Iterate through languages from $config and match
        // if nothing matches, default to 'en' or what is previously set
      }
    }

  }

}