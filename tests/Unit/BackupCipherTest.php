<?php
namespace Tests\Unit;
use App\Services\BackupCipher;
use PHPUnit\Framework\TestCase;
class BackupCipherTest extends TestCase
{
    private string $dir;
    protected function setUp(): void { parent::setUp(); $this->dir = sys_get_temp_dir().'/cipher-test-'.bin2hex(random_bytes(6)); mkdir($this->dir, 0700); }
    protected function tearDown(): void { foreach (glob($this->dir.'/*') as $file) unlink($file); rmdir($this->dir); parent::tearDown(); }
    public function test_stream_round_trip_including_empty_and_exact_chunk_boundary(): void
    {
        foreach ([0, 1048576, 1048621] as $size) {
            $data = $size ? random_bytes($size) : '';
            $key = random_bytes(32); file_put_contents($this->dir.'/in', $data);
            $cipher = new BackupCipher;
            $cipher->encrypt($this->dir.'/in', $this->dir.'/encrypted', $key);
            $cipher->decrypt($this->dir.'/encrypted', $this->dir.'/out', $key);
            $this->assertSame($data, file_get_contents($this->dir.'/out'));
            $this->assertSame(0600, fileperms($this->dir.'/encrypted') & 0777);
            unlink($this->dir.'/encrypted'); unlink($this->dir.'/out');
        }
    }
    public function test_wrong_key_tampering_truncation_and_trailing_data_are_rejected(): void
    {
        $key = random_bytes(32); file_put_contents($this->dir.'/in', 'Private POS data');
        $cipher = new BackupCipher; $cipher->encrypt($this->dir.'/in', $this->dir.'/encrypted', $key);
        $valid = file_get_contents($this->dir.'/encrypted');
        $tampered = $valid; $tampered[strlen($tampered) - 4] = chr(ord($tampered[strlen($tampered) - 4]) ^ 1);
        foreach ([[$valid, random_bytes(32)], [$tampered, $key], [substr($valid, 0, -1), $key], [$valid.'junk', $key]] as [$bytes, $testKey]) {
            file_put_contents($this->dir.'/broken', $bytes);
            try { $cipher->decrypt($this->dir.'/broken', $this->dir.'/out', $testKey); $this->fail('Damaged backup accepted'); }
            catch (\RuntimeException $e) { $this->assertNotEmpty($e->getMessage()); }
            finally { if (is_file($this->dir.'/out')) unlink($this->dir.'/out'); }
        }
    }
}
