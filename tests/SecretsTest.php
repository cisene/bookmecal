<?php
/**
 * tests/SecretsTest.php
 * Signing keys in secrets.json: created once, kept, separate per name.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/Secrets.php';

class SecretsTest extends TestCase {
    private string $file;

    protected function setUp(): void {
        $this->file = sys_get_temp_dir() . '/bookmecal-secrets-' . bin2hex(random_bytes(4)) . '.json';
        Secrets::setPath($this->file);
    }

    protected function tearDown(): void {
        Secrets::setPath(null);
        @unlink($this->file);
    }

    public function testKeysAreCreatedOnceAndKept() {
        $form = Secrets::key('form_secret');
        $cancel = Secrets::key('cancel_secret');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $form);
        $this->assertNotSame($form, $cancel);

        Secrets::setPath($this->file); // forget the cache, read from disk again
        $this->assertSame($form, Secrets::key('form_secret'));
        $this->assertSame(['form_secret' => $form, 'cancel_secret' => $cancel], json_decode(file_get_contents($this->file), true));
    }

    public function testTooShortKeysAreReplaced() {
        file_put_contents($this->file, json_encode(['form_secret' => 'short', 'other' => 'kept']));
        $this->assertSame(64, strlen(Secrets::key('form_secret')));
        $this->assertSame('kept', json_decode(file_get_contents($this->file), true)['other']);
    }
}
