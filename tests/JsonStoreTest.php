<?php
/**
 * tests/JsonStoreTest.php
 * Locked JSON reads and writes.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/classes/JsonStore.php';

class JsonStoreTest extends TestCase {
    private string $file;

    protected function setUp(): void {
        $this->file = sys_get_temp_dir() . '/bookmecal-store-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void {
        @unlink($this->file);
    }

    public function testReadWriteUpdate() {
        $this->assertNull(JsonStore::read($this->file));
        $this->assertTrue(JsonStore::write($this->file, ['a' => 'å']));
        $this->assertTrue(JsonStore::update($this->file, fn (array $d) => $d + ['b' => 2]));
        $this->assertSame(['a' => 'å', 'b' => 2], JsonStore::read($this->file));
        $this->assertTrue(JsonStore::delete($this->file));
        $this->assertNull(JsonStore::read($this->file));
    }

    public function testReaderNeverSeesAHalfWrittenFile() {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('needs the pcntl extension');
        }
        JsonStore::write($this->file, ['n' => 0, 'pad' => str_repeat('x', 200000)]);
        $pid = pcntl_fork();
        if ($pid === 0) {
            for ($i = 1; $i <= 200; $i++) {
                JsonStore::update($this->file, fn () => ['n' => $i, 'pad' => str_repeat('x', 200000 + $i)]);
            }
            exit(0);
        }
        $broken = 0;
        while (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
            $data = JsonStore::read($this->file);
            $broken += isset($data['n']) ? 0 : 1;
        }
        $this->assertSame(0, $broken);
        $this->assertSame(200, JsonStore::read($this->file)['n']);
    }
}
