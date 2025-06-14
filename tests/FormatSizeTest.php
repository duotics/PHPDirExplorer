<?php
use PHPUnit\Framework\TestCase;

class FormatSizeTest extends TestCase
{
    /**
     * @dataProvider sizeProvider
     */
    public function testFormatSize(int $bytes, string $expected): void
    {
        $this->assertSame($expected, formatSize($bytes));
    }

    public static function sizeProvider(): array
    {
        return [
            [0, '0 B'],
            [1, '1 B'],
            [1023, '1023 B'],
            [1024, '1 KB'],
            [1536, '1.5 KB'],
            [1048576, '1 MB'],
            [1073741824, '1 GB'],
            [1099511627776, '1 TB'],
        ];
    }
}
?>
