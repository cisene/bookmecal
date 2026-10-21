<?php

class Constants {

  private const LOCALES = array(

    'se' => array(
      'date' => 'YYYY-MM-DD',
      'time' => 'HH:mm',
      'currency' => 'SEK',
    ),
    'no' => array(
      'date' => 'DD.MM.YYYY',
      'time' => 'HH:mm',
      'currency' => 'NOK',
    ),
    'dk' => array(
      'date' => 'DD.MM.YYYY',
      'time' => 'HH:mm',
      'currency' => 'DKK',
    ),
    'fi' => array(
      'date' => 'DD.MM.YYYY',
      'time' => 'HH.mm',
      'currency' => 'EUR',
    ),
    'de' => array(
      'date' => 'YYYY-MM-DD',
      'time' => 'HH:mm',
      'currency' => 'EUR',
    ),
    'fr' => array(
      'date' => 'DD/MM/YYYY',
      'time' => 'HHhmm',
      'currency' => 'EUR',
    ),
    'es' => array(
      'date' => 'DD/MM/YYYY',
      'time' => 'HH:mm h',
      'currency' => 'EUR',
    ),
    'ee' => array(
      'date' => 'DD.MM.YYYY',
      'time' => 'HH:mm',
      'currency' => 'EUR',
    ),

  );



  public function __destruct() {}
  public function __construct() {}

  public function getLocaleForCountry($countryCode) {
    return self::LOCALES[$countryCode] ?? null;
  }


}
