<?php

class Languages {
  private const LANG = array(
    'de' => 'German',
    'el' => 'Greek',
    'en' => 'English',
    'es' => 'Spanish',
    'fr' => 'French',
    'it' => 'Italian',
    'ja' => 'Japanese',
    'ko' => 'Korean',
    'pt' => 'Portuguese',
    'sv' => 'Swedish',
    'th' => 'Thai',
    'tl' => 'Tagalog',
    'vi' => 'Vietnamese',
    'zh-CN' => 'Mandarin',
  );

  public function __destruct() {}

  public function __construct() {}

  public function ListLanguages() {
    return self::LANG;
  }


}



