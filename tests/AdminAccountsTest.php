<?php
/**
 * tests/AdminAccountsTest.php
 * Admin accounts in .htpasswd: hash formats, upgrade to bcrypt, password policy.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/AdminAccounts.php';

class AdminAccountsTest extends TestCase {
    private string $file;

    protected function setUp(): void {
        $this->file = sys_get_temp_dir() . '/bookmecal-htpasswd-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void {
        @unlink($this->file);
    }

    public function testApr1MatchesApacheHtpasswd() {
        // Vectors from `openssl passwd -apr1 -salt <salt> <password>`
        $this->assertSame('$apr1$Ab3dEf9h$KvgEgLS07bOKpScKWCY8S.', AdminAccounts::apr1('correct horse', 'Ab3dEf9h'));
        $this->assertSame('$apr1$x$C0T353z9qY7h5AzMIi/421', AdminAccounts::apr1('å', 'x'));
        $this->assertSame('$apr1$12345678$ZB1vyDV5V.sOKjJ6rQyTE1', AdminAccounts::apr1(str_repeat('a', 40), '12345678'));
    }

    public function testVerifiesApr1AndUpgradesItToBcrypt() {
        file_put_contents($this->file, "anna:\$apr1\$Ab3dEf9h\$KvgEgLS07bOKpScKWCY8S.\n");
        $accounts = new AdminAccounts($this->file);

        $this->assertFalse($accounts->verify('anna', 'wrong horse'));
        $this->assertStringStartsWith('$apr1$', $accounts->all()['anna']);

        $this->assertTrue($accounts->verify('anna', 'correct horse'));
        $this->assertStringStartsWith('$2y$', $accounts->all()['anna']);
        $this->assertTrue($accounts->verify('anna', 'correct horse'));
    }

    public function testVerifiesShaCryptHashes() {
        file_put_contents($this->file, 'bo:' . crypt('sha-password', '$6$' . bin2hex(random_bytes(8)) . '$') . "\n");
        $accounts = new AdminAccounts($this->file);
        $this->assertTrue($accounts->verify('bo', 'sha-password'));
        $this->assertFalse($accounts->verify('bo', 'sha-passwor'));
    }

    public function testUnknownUsersAndEmptyPasswordsFail() {
        $accounts = new AdminAccounts($this->file);
        $this->assertTrue($accounts->setPassword('anna', 'long enough password'));
        $this->assertFalse($accounts->verify('nobody', 'long enough password'));
        $this->assertFalse($accounts->verify('anna', ''));
        $this->assertFalse($accounts->verify('ANNA', 'long enough password'));
    }

    public function testSetPasswordRejectsInvalidUsernames() {
        $accounts = new AdminAccounts($this->file);
        $this->assertFalse($accounts->setPassword("evil\nroot", 'long enough password'));
        $this->assertFalse($accounts->setPassword('a:b', 'long enough password'));
        $this->assertSame([], $accounts->all());
    }

    public function testFingerprintChangesWithThePassword() {
        $accounts = new AdminAccounts($this->file);
        $accounts->setPassword('anna', 'first long password');
        $before = $accounts->fingerprint('anna');
        $accounts->setPassword('anna', 'second long password');
        $this->assertNotSame($before, $accounts->fingerprint('anna'));
        $accounts->delete('anna');
        $this->assertNull($accounts->fingerprint('anna'));
    }

    public function testPasswordPolicy() {
        $this->assertNotNull(AdminAccounts::passwordProblem('short'));
        $this->assertNotNull(AdminAccounts::passwordProblem(str_repeat('å', 40)));
        $this->assertNull(AdminAccounts::passwordProblem('ten chars!'));
    }
}
