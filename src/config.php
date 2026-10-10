<?PHP

$config = array(

  "database" => array(
    "driver"   => 'mysql', // 'mysql', 'sqlite', eller 'pgsql'
    "host"     => '127.0.0.1',
    "dbname"   => 'calendar_db',
    "user"     => 'user',
    "password" => 'password',
    "path"     => __DIR__ . '/database/calendar.sqlite',
  ),

  "locale" => array(
    "timezone" => 'Europe/Stockholm',
  ),

  "calendar" => array(
    "owner"        => '',
    "open"         => '08:00',
    "close"        => '17:00',
    "slotDuration" => '00:30',
    "lunch"        => array(
      "start"    => '12:00',
      "duration" => 30,
    )
  ),

  "google_calendar" => array(
    "calendar_id"      => 'primary',
    "application_name" => 'Booking Calendar System',
    "scopes"           => array(
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events'
    ),
    "token_file"       => __DIR__ . '/token.json',
    "credentials_file" => __DIR__ . '/credentials.json',
    "redirect_uri"     => 'http://localhost/gcal_callback.php',
    "cache_ttl_minutes" => 60,
  )

);