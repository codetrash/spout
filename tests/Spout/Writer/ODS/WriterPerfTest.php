<?php

namespace Box\Spout\Writer\ODS;

use Box\Spout\Common\Type;
use Box\Spout\TestUsingResource;
use Box\Spout\Writer\WriterFactory;

/**
 * Class WriterPerfTest
 * Performance tests for ODS Writer
 *
 * @package Box\Spout\Writer\ODS
 */
class WriterPerfTest extends \PHPUnit_Framework_TestCase
{
    use TestUsingResource;

    /**
     * 2 million rows (each row containing 3 cells) should be written
     * in less than 8 minutes and the execution should not require
     * more than 10MB of memory
     *
     * @group perf-test
     *
     * @return void
     */
    public function testPerfWhenWritingTwoMillionRowsODS()
    {
        $numRows = 2000000;
        $expectedMaxExecutionTime = 480; // 8 minutes in seconds
        $expectedMaxMemoryPeakUsage = 10 * 1024 * 1024; // 10MB in bytes
        $startTime = time();

        $fileName = 'ods_with_two_million_rows.ods';
        $this->createGeneratedFolderIfNeeded($fileName);
        $resourcePath = $this->getGeneratedResourcePath($fileName);

        /** @var Writer $writer */
        $writer = WriterFactory::create(Type::ODS);
        $writer->setShouldCreateNewSheetsAutomatically(true);

        $writer->openToFile($resourcePath);

        for ($i = 1; $i <= $numRows; $i++) {
            $writer->addRow(["ods--{$i}-1", "ods--{$i}-2", "ods--{$i}-3"]);
        }

        $writer->close();

        $this->assertEquals($numRows, $this->getNumWrittenRows($resourcePath), "The created ODS ($fileName) should contain $numRows rows");

        $executionTime = time() - $startTime;
        $this->assertTrue($executionTime < $expectedMaxExecutionTime, "Writing 2 million rows should take less than $expectedMaxExecutionTime seconds (took $executionTime seconds)");

        $memoryPeakUsage = memory_get_peak_usage(true);
        $this->assertTrue($memoryPeakUsage < $expectedMaxMemoryPeakUsage, 'Writing 2 million rows should require less than ' . ($expectedMaxMemoryPeakUsage / 1024 / 1024) . ' MB of memory (required ' . ($memoryPeakUsage / 1024 / 1024) . ' MB)');
    }

    /**
     * @param string $resourcePath
     * @return int
     */
    private function getNumWrittenRows($resourcePath)
    {
        $numWrittenRows = 0;
        // to avoid executing the regex of the entire file to get the last row number, we only retrieve the last 10 lines
        $endingContentXmlContents = $this->getLastCharactersOfContentXmlFile($resourcePath);

        if (preg_match_all('/<text:p>ods--(\d+)-\d<\/text:p>/', $endingContentXmlContents, $matches)) {
            $lastMatch = array_pop($matches);
            $numWrittenRows = intval(array_pop($lastMatch));
        }

        return $numWrittenRows;
    }

    /**
     * @param string $resourcePath
     * @return string
     */
    private function getLastCharactersOfContentXmlFile($resourcePath)
    {
        $pathToContentXmlFile = 'zip://' . $resourcePath . '#content.xml';

        // since we cannot execute "tail" on a file inside a zip, we need to copy it outside first
        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'get_last_characters.xml';
        copy($pathToContentXmlFile, $tmpFile);

        // Get the last 200 characters
        $lastCharacters = `tail -c 200 $tmpFile`;

        // remove the temporary file
        unlink($tmpFile);

        return $lastCharacters;
    }
}
