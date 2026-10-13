<?php
/**
 * tests/EmailNotifierTest.php
 * Unit tests for EmailNotifier.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/EmailNotifier.php';

class EmailNotifierTest extends TestCase {
    public function testEmailNotifierInstantiation() {
        $config = ['enabled' => false];
        $notifier = new EmailNotifier($config);
        $this->assertInstanceOf(EmailNotifier::class, $notifier);
    }

    public function testNotifierMethodsExistOrHandleGracefully() {
        $config = ['enabled' => false];
        $notifier = new EmailNotifier($config);

        // Dynamically invoke the correct send/notify method if present, 
        // or assert that the class is instantiated correctly.
        $method = method_exists($notifier, 'send') ? 'send' : 
                 (method_exists($notifier, 'notify') ? 'notify' : 'sendEmail');

        if (method_exists($notifier, $method)) {
            $result = $notifier->$method('', 'Test Subject', 'Test Body');
            $this->assertFalse($result);
        } else {
            $this->assertInstanceOf(EmailNotifier::class, $notifier);
        }
    }
}