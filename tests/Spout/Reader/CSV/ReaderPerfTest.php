<?php

namespace Box\Spout\Reader\CSV;

use Box\Spout\Common\Type;
use Box\Spout\TestUsingResource;
use Box\Spout\Reader\ReaderFactory;

/**
 * Class ReaderPerfTest
 * Performance tests for CSV Reader
 *
 * @package Box\Spout\Reader\CSV
 */
class ReaderPerfTest extends \PHPUnit_Framework_TestCase
{
    use TestUsingResource;

    /**
     * 2 million rows (each row containing 3 cells) should be read
     * in less than 240 seconds and the execution should not require
     * more than 10MB of memory
     *
     * @group perf-test
     *
     * @return void
     */
    public function testPerfWhenReadingTwoMillionRowsCSV()
    {
        $expectedMaxExecutionTime = 240; // 4 minutes in seconds
        $expectedMaxMemoryPeakUsage = 10 * 1024 * 1024; // 10MB in bytes
        $startTime = time();

        $fileName = 'csv_with_two_million_rows.csv';
        $resourcePath = $this->getResourcePath($fileName);

        $reader = ReaderFactory::create(Type::CSV);
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
        $this->assertTrue($memoryPeakUsage < $expectedMaxMemoryPeakUsage, 'Reading 2 million rows should require less than ' . ($expectedMaxMemoryPeakUsage / 1024 / 1024) . ' MB of memory (required ' . round($memoryPeakUsage / 1024 / 1024, 2) . ' MB)');
    }
}
