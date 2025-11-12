<?php

use PHPUnit\Framework\TestCase;

final class LogProgressTest extends TestCase
{
    private string $tmpJournal;
    private string $tmpJson;

    protected function setUp(): void
    {
        $this->tmpJournal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pj_test_journal.md';
        $this->tmpJson = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pj_test_entries.jsonl';
        // ensure files are clean
        @unlink($this->tmpJournal);
        @unlink($this->tmpJson);
        file_put_contents($this->tmpJournal, "# Test Journal\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpJournal);
        @unlink($this->tmpJson);
    }

    public function testStartWritesJsonLine(): void
    {
        $cmd = sprintf('php "%s" start --id=UT.1 --phase="UT" --title="Unit test start" --owner="ci" --desc="desc" --format=json --out-json="%s" --out-journal="%s"',
            __DIR__ . '/../../bin/log_progress.php',
            $this->tmpJson,
            $this->tmpJournal
        );

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);
        $this->assertSame(0, $ret, "Script should exit 0");

        $this->assertFileExists($this->tmpJson);
        $lines = file($this->tmpJson, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(1, $lines);
        $obj = json_decode($lines[0], true);
        $this->assertSame('UT.1', $obj['id']);
        $this->assertSame('in-progress', $obj['status']);
        $this->assertSame('ci', $obj['owner']);
    }
}
