<?php
/**
 * src/classes/SetupCheck.php
 * Things still missing before customers can use the booking, shown on the admin dashboard.
 */

class SetupCheck {
    /**
     * Open items as a list of ['key' => translation key suffix, 'page' => admin page that fixes it].
     * Pure function of the data files, so it can be tested without a server.
     */
    public static function problems(array $settings, array $config, array $services, array $openHours, bool $https): array {
        $smtp = $config['smtp'] ?? [];
        $emailOn = ($smtp['enabled'] ?? false) === true;
        $problems = [];

        if (!array_filter($services, fn ($s) => is_array($s) && ($s['is_active'] ?? true) !== false)) {
            $problems[] = ['key' => 'noServices', 'page' => 'services.php'];
        }
        if (!array_filter($openHours, fn ($d) => is_array($d) && !empty($d['is_open']))) {
            $problems[] = ['key' => 'noOpenHours', 'page' => 'openhours.php'];
        }
        if (trim((string)($settings['business_name'] ?? '')) === '') {
            $problems[] = ['key' => 'businessName', 'page' => 'config.php'];
        }
        if (!$emailOn) {
            $problems[] = ['key' => 'emailOff', 'page' => 'config.php'];
        } else {
            $from = (string)($smtp['from_email'] ?? '');
            if ($from === '' || str_ends_with(strtolower($from), '@example.com')) {
                $problems[] = ['key' => 'emailSender', 'page' => 'config.php'];
            }
            if (trim((string)($smtp['notification_email'] ?? '')) === '') {
                $problems[] = ['key' => 'emailAlerts', 'page' => 'config.php'];
            }
        }
        if (trim((string)($settings['public_url'] ?? '')) === '') {
            $problems[] = ['key' => 'publicUrl', 'page' => 'config.php'];
        }
        if (!$https) {
            $problems[] = ['key' => 'https', 'page' => null];
        }
        return $problems;
    }
}
