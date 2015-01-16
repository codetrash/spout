<?php

namespace Box\Spout\Writer\CSV;

use Box\Spout\Common\Type;
use Box\Spout\TestUsingResource;
use Box\Spout\Writer\WriterFactory;

/**
 * Class WriterPerfTest
 * Performance tests for CSV Writer
 *
 * @package Box\Spout\Writer\CSV
 */
class WriterPerfTest extends \PHPUnit_Framework_TestCase
{
    use TestUsingResource;

    /**
     * 2 million rows (each row containing 3 cells) should be written
     * in less than 60 seconds and the execution should not require
     * more than 10MB of memory
     *
     * @group perf-test
     *
     * @return void
     */
    public function testPerfWhenWritingTwoMillionRowsCSV()
    {
        $numRows = 2000000;
        $expectedMaxExecutionTime = 90; // 1.5 minutes in seconds
        $expectedMaxMemoryPeakUsage = 10 * 1024 * 1024; // 10MB in bytes
        $startTime = time();

        $fileName = 'csv_with_two_million_rows.csv';
        $this->createGeneratedFolderIfNeeded($fileName);
        $resourcePath = $this->getGeneratedResourcePath($fileName);

        $writer = WriterFactory::create(Type::CSV);
        $writer->openToFile($resourcePath);

        for ($i = 1; $i <= $numRows; $i++) {
            $writer->addRow(["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"]);
        }

        $writer->close();

        $this->assertEquals($numRows, $this->getNumWrittenRows($resourcePath), "The created CSV should contain $numRows rows");

        $executionTime = time() - $startTime;
        $this->assertTrue($executionTime < $expectedMaxExecutionTime, "Writing 2 million rows should take less than $expectedMaxExecutionTime seconds (took $executionTime seconds)");

        $memoryPeakUsage = memory_get_peak_usage(true);
        $this->assertTrue($memoryPeakUsage < $expectedMaxMemoryPeakUsage, 'Writing 2 million rows should require less than ' . ($expectedMaxMemoryPeakUsage / 1024 / 1024) . ' MB of memory (required ' . round($memoryPeakUsage / 1024 / 1024, 2) . ' MB)');
    }

    /**
     * @param string $resourcePath
     * @return int
     */
    private function getNumWrittenRows($resourcePath)
    {
        $lineCountResult = `wc -l $resourcePath`;
        return intval($lineCountResult);
    }
}
