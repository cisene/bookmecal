<?php
/**
 * tests/ContactCardTest.php
 * Contact cards (vCard) for the venue and the employee, coordinates and plus codes.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/ContactCard.php';

class ContactCardTest extends TestCase {
    private const VENUE = [
        'website' => 'https://salong.example.se/', 'phone' => '+46 8 123 45 67', 'email' => 'info@salong.example.se',
        'street' => 'Storgatan 1', 'postal_code' => '111 22', 'city' => 'Stockholm', 'country' => 'Sverige',
        'latitude' => '59.334591', 'longitude' => '18.06324', 'plus_code' => '9FFW83PH+X9',
    ];

    private const LYNN = ['givenName' => 'Lynn', 'familyName' => 'Isene', 'jobTitle' => 'Fotvårdare',
        'mobileNumber' => '070-123 45 67', 'email' => 'lynn@example.se', 'shareContact' => true];

    public function testVenueCardHoldsAllDetails() {
        $card = ContactCard::venue(self::VENUE, 'Salong; Anna');
        $this->assertStringStartsWith("BEGIN:VCARD\r\nVERSION:3.0\r\n", $card);
        $this->assertStringEndsWith("END:VCARD\r\n", $card);
        $this->assertStringContainsString("FN:Salong\; Anna\r\n", $card);
        $this->assertStringContainsString("TEL;TYPE=WORK,VOICE:+46 8 123 45 67\r\n", $card);
        $this->assertStringContainsString("ADR;TYPE=WORK:;;Storgatan 1;Stockholm;;111 22;Sverige\r\n", $card);
        $this->assertStringContainsString("GEO:59.334591;18.06324\r\n", $card);
        $this->assertStringContainsString('Plus Code: 9FFW83PH+X9', $card);
        $this->assertStringContainsString("URL:https://salong.example.se/\r\n", $card);
    }

    public function testVenueCardNeedsANameAndAWayToReachIt() {
        $this->assertNull(ContactCard::venue(self::VENUE, ''));
        $this->assertNull(ContactCard::venue([], 'Salong'));
        $this->assertStringContainsString('URL:https://boka.example.se/', ContactCard::venue([], 'Salong', 'https://boka.example.se/'));
    }

    public function testEmployeeCardOnlyWithConsent() {
        $card = ContactCard::employee(self::LYNN, 'Salong');
        $this->assertStringContainsString("N:Isene;Lynn;;;\r\n", $card);
        $this->assertStringContainsString("TEL;TYPE=CELL,VOICE:070-123 45 67\r\n", $card);
        $this->assertStringContainsString("TITLE:Fotvårdare\r\n", $card);
        $this->assertNull(ContactCard::employee(['shareContact' => false] + self::LYNN, 'Salong'));
        $this->assertNull(ContactCard::employee(['givenName' => '', 'familyName' => ''] + self::LYNN, 'Salong'));
        // Without phone or email the card still says who will treat the customer
        $this->assertStringContainsString('FN:Lynn Isene', ContactCard::employee(['mobileNumber' => '', 'email' => ''] + self::LYNN, 'Salong'));
    }

    public function testEmployeeCardMapsEveryProfileField() {
        $card = ContactCard::employee(self::LYNN + [
            'additionalName' => 'Maria', 'honorificPrefix' => 'Leg.', 'honorificSuffix' => 'MSc', 'nickname' => 'Lynnie',
            'role' => 'Ansvarig', 'phoneNumber' => '08-123 45', 'website' => 'https://lynn.example.se/',
            'avatar' => 'https://lynn.example.se/lynn.jpg', 'socialProfiles' => ['https://www.instagram.com/lynn', 'https://example.org/lynn'],
            'qualifications' => ['Diplomerad fotvårdare', 'Medicinsk fotvård'], 'languages' => ['svenska', 'English'],
            'bio' => 'Fotvård sedan 2010.', 'organisationNumber' => '820114-1382', 'swishPaymentNumber' => '1231234567',
            'businessAttributes' => ['department' => 'Fotvård', 'showPhoto' => true],
        ], 'Salong');
        foreach ([
            "N:Isene;Lynn;Maria;Leg.;MSc\r\n", "NICKNAME:Lynnie\r\n", "ORG:Salong;Fotvård\r\n", "ROLE:Ansvarig\r\n",
            "TEL;TYPE=WORK,VOICE:08-123 45\r\n", "EMAIL;TYPE=INTERNET,WORK:lynn@example.se\r\n", "URL:https://lynn.example.se/\r\n",
            "PHOTO;VALUE=URI:https://lynn.example.se/lynn.jpg\r\n", "X-SOCIALPROFILE;TYPE=instagram:https://www.instagram.com/lynn\r\n",
            "X-SOCIALPROFILE:https://example.org/lynn\r\n", "CATEGORIES:Diplomerad fotvårdare,Medicinsk fotvård\r\n",
            'NOTE:Fotvård sedan 2010.\\n\\nLanguages: svenska\\, English',
        ] as $expected) {
            $this->assertStringContainsString($expected, $card);
        }
        // Payment and company registration details are not contact details
        $this->assertStringNotContainsString('1231234567', $card);
        $this->assertStringNotContainsString('820114', $card);
        // The photo only when the employee shows it
        $this->assertStringNotContainsString('PHOTO', ContactCard::employee(self::LYNN + ['avatar' => 'https://lynn.example.se/lynn.jpg'], 'Salong'));
    }

    public function testModeDecidesWhatIsSent() {
        $settings = ['business_name' => 'Salong', 'venue' => self::VENUE];
        $this->assertNull(ContactCard::forBooking($settings, self::LYNN));
        $this->assertSame(1, substr_count(ContactCard::forBooking(['vcard' => 'venue'] + $settings, self::LYNN), 'BEGIN:VCARD'));
        $this->assertSame(2, substr_count(ContactCard::forBooking(['vcard' => 'staff'] + $settings, self::LYNN), 'BEGIN:VCARD'));
        $this->assertSame(1, substr_count(ContactCard::forBooking(['vcard' => 'staff'] + $settings, null), 'BEGIN:VCARD'));
        $this->assertNull(ContactCard::forBooking(['vcard' => 'bogus'] + $settings, self::LYNN));
    }

    public function testCustomerTextCannotAddProperties() {
        $card = ContactCard::venue(['street' => "Gatan 1\r\nEMAIL:evil@example.com"], 'Salong');
        $this->assertStringNotContainsString("\r\nEMAIL:", $card);
    }

    public function testCoordinatesAndPlusCodes() {
        $this->assertSame('59.33', ContactCard::coordinate('59,330', 90));
        $this->assertSame('-18.5', ContactCard::coordinate('-18.5', 180));
        $this->assertNull(ContactCard::coordinate('91', 90));
        $this->assertNull(ContactCard::coordinate('59°20′N', 90));
        $this->assertSame('9FFW83PH+X9', ContactCard::plusCode(' 9ffw83ph+x9 '));
        $this->assertSame('83PH+X9 Stockholm', ContactCard::plusCode('83ph+x9  Stockholm'));
        $this->assertNull(ContactCard::plusCode('9FFW83PH'));
        $this->assertNull(ContactCard::plusCode('ABCD+12'));
    }

    public function testLocationForTheCalendarFile() {
        $this->assertSame(['text' => 'Storgatan 1, 111 22, Stockholm', 'geo' => ['59.334591', '18.06324']], ContactCard::location(['venue' => self::VENUE]));
        $this->assertNull(ContactCard::location([]));
    }
}
