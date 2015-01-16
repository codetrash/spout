<?php

namespace Box\Spout\Reader\XLSX;

use Box\Spout\Common\Type;
use Box\Spout\TestUsingResource;
use Box\Spout\Reader\ReaderFactory;

/**
 * Class ReaderPerfTest
 * Performance tests for XLSX Reader
 *
 * @package Box\Spout\Reader\XLSX
 */
class ReaderPerfTest extends \PHPUnit_Framework_TestCase
{
    use TestUsingResource;

    /**
     * @return array
     */
    public function dataProviderForTestPerfWhenReadingTwoMillionRowsXLSX()
    {
        return [
            [$shouldUseInlineStrings = true, $expectedMaxExecutionTime = 2100], // 35 minutes in seconds
            [$shouldUseInlineStrings = false, $expectedMaxExecutionTime = 4200], // 70 minutes in seconds
        ];
    }

    /**
     * 2 million rows (each row containing 3 cells) should be read
     * in less than 35 minutes for inline strings, 70 minutes for
     * shared strings and the execution should not require
     * more than 10MB of memory
     *
     * @dataProvider dataProviderForTestPerfWhenReadingTwoMillionRowsXLSX
     * @group perf-test
     *
     * @param bool $shouldUseInlineStrings
     * @param int $expectedMaxExecutionTime
     * @return void
     */
    public function testPerfWhenReadingTwoMillionRowsXLSX($shouldUseInlineStrings, $expectedMaxExecutionTime)
    {
        $expectedMaxMemoryPeakUsage = 10 * 1024 * 1024; // 10MB in bytes
        $startTime = time();

        $fileName = ($shouldUseInlineStrings) ? 'xlsx_with_two_million_rows_and_inline_strings.xlsx' : 'xlsx_with_two_million_rows_and_shared_strings.xlsx';
        $resourcePath = $this->getResourcePath($fileName);

        $reader = ReaderFactory::create(Type::XLSX);
        $reader->open($resourcePath);

        $numReadRows = 0;

        /** @var Sheet $sheet */
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $numReadRows++;
            }
        }

        $reader->close();

        $expectedNumRows = 2000000;
        $this->assertEquals($expectedNumRows, $numReadRows, "$expectedNumRows rows should have been read");

        $executionTime = time() - $startTime;
        $this->assertTrue($executionTime < $expectedMaxExecutionTime, "Reading 2 million rows should take less than $expectedMaxExecutionTime seconds (took $executionTime seconds)");

        $memoryPeakUsage = memory_get_peak_usage(true);
        $this->assertTrue($memoryPeakUsage < $expectedMaxMemoryPeakUsage, 'Reading 2 million rows should require less than ' . ($expectedMaxMemoryPeakUsage / 1024 / 1024) . ' MB of memory (required ' . ($memoryPeakUsage / 1024 / 1024) . ' MB)');
    }
}
