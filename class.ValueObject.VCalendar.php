<?php

class vcalObject {

  // BEGIN:VCALENDAR
  // VERSION:2.0
  // METHOD:PUBLISH
  // CALSCALE:GREGORIAN
  // PRODID:-//WordPress - MECv6.5.6//EN
  // X-ORIGINAL-URL:https://domain.local/
  // X-WR-CALNAME:Calendar Name
  // X-WR-CALDESC:Calendar Description
  // REFRESH-INTERVAL;VALUE=DURATION:PT1H
  // X-PUBLISHED-TTL:PT1H
  // X-MS-OLK-FORCEINSPECTOROPEN:TRUE

    // { vevents }

  // END:VCALENDAR

  public string $version                    = '2.0';
  public string $method                     = 'PUBLISH';
  public string $calscale                   = 'GREGORIAN';
  public string $prodid                     = '-//Something//EN';
  public string $refreshinterval            = 'DURATION:PT1H';

  // X-prefixed
  public string $xoriginalurl               = 'http://domain.local/';
  public string $xpublishedttl              = 'PT1H';
  public string $xmsolkforceinspectoropen   = 'TRUE';
  public string $xwrcalname                 = 'calname';
  public string $xwrcaldesc                 = 'caldesc';

  public function __destruct () {

  }

  public function __construct () {

  }

  public function asVCal () {

  }

}

class veventObject {

  // BEGIN:VEVENT
  // CLASS:PUBLIC
  // UID:MEC-3dc91c9313038ce8c97966f7f4194aac@domain.local
  // DTSTART:20240219T130000Z
  // DTEND:20240219T160000Z
  // DTSTAMP:20240124T141700Z
  // CREATED:20240124
  // LAST-MODIFIED:20240124
  // PRIORITY:5
  // TRANSP:OPAQUE
  // SUMMARY: Event Summary
  // DESCRIPTION: Event Description
  // URL:https://domain.local/calendar/event-uri/
  // CATEGORIES:Café
  // ATTACH;FMTTYPE=image/jpeg:https://domain.local/uploads/picture.jpg
  // END:VEVENT

  public string $class        = 'PUBLIC';

  public string $uid          = '';

  public string $dtstart      = '';
  public string $dtend        = '';
  public string $created      = '';
  public string $lastmodified = '';

  public int    $priority     = 5;

  public string $transp       = 'OPAQUE';

  public string $summary      = '';
  public string $description  = '';
  public string $url          = '';
  public string $categories   = '';
  public string $attach       = '';

  public function __destruct () {
    
  }

  public function __construct () {

  }

  public function asVEvent () {

  }

}