<?php

namespace Box\Spout\Writer\XLSX;

use Box\Spout\Common\Type;
use Box\Spout\TestUsingResource;
use Box\Spout\Writer\WriterFactory;

/**
 * Class WriterPerfTest
 * Performance tests for XLSX Writer
 *
 * @package Box\Spout\Writer\XLSX
 */
class WriterPerfTest extends \PHPUnit_Framework_TestCase
{
    use TestUsingResource;

    /**
     * @return array
     */
    public function dataProviderForTestPerfWhenWritingTwoMillionRowsXLSX()
    {
        return [
            [$shouldUseInlineStrings = true],
            [$shouldUseInlineStrings = false],
        ];
    }

    /**
     * 2 million rows (each row containing 3 cells) should be written
     * in less than 12 minutes and the execution should not require
     * more than 10MB of memory
     *
     * @dataProvider dataProviderForTestPerfWhenWritingTwoMillionRowsXLSX
     * @group perf-test
     *
     * @param bool $shouldUseInlineStrings
     * @return void
     */
    public function testPerfWhenWritingTwoMillionRowsXLSX($shouldUseInlineStrings)
    {
        $numRows = 2000000;
        $expectedMaxExecutionTime = 720; // 12 minutes in seconds
        $expectedMaxMemoryPeakUsage = 10 * 1024 * 1024; // 10MB in bytes
        $startTime = time();

        $fileName = ($shouldUseInlineStrings) ? 'xlsx_with_two_million_rows_and_inline_strings.xlsx' : 'xlsx_with_two_million_rows_and_shared_strings.xlsx';
        $this->createGeneratedFolderIfNeeded($fileName);
        $resourcePath = $this->getGeneratedResourcePath($fileName);

        /** @var Writer $writer */
        $writer = WriterFactory::create(Type::XLSX);
        $writer->setShouldUseInlineStrings($shouldUseInlineStrings);
        $writer->setShouldCreateNewSheetsAutomatically(true);

        $writer->openToFile($resourcePath);

        for ($i = 1; $i <= $numRows; $i++) {
            $writer->addRow(["xlsx--{$i}-1", "xlsx--{$i}-2", "xlsx--{$i}-3"]);
        }

        $writer->close();

        if ($shouldUseInlineStrings) {
            $numSheets = count($writer->getSheets());
            $this->assertEquals($numRows, $this->getNumWrittenRowsUsingInlineStrings($resourcePath, $numSheets), "The created XLSX ($fileName) should contain $numRows rows");
        } else {
            $this->assertEquals($numRows, $this->getNumWrittenRowsUsingSharedStrings($resourcePath), "The created XLSX ($fileName) should contain $numRows rows");
        }

        $executionTime = time() - $startTime;
        $this->assertTrue($executionTime < $expectedMaxExecutionTime, "Writing 2 million rows should take less than $expectedMaxExecutionTime seconds (took $executionTime seconds)");

        $memoryPeakUsage = memory_get_peak_usage(true);
        $this->assertTrue($memoryPeakUsage < $expectedMaxMemoryPeakUsage, 'Writing 2 million rows should require less than ' . ($expectedMaxMemoryPeakUsage / 1024 / 1024) . ' MB of memory (required ' . ($memoryPeakUsage / 1024 / 1024) . ' MB)');
    }

    /**
     * @param string $resourcePath
     * @param int $numSheets
     * @return int
     */
    private function getNumWrittenRowsUsingInlineStrings($resourcePath, $numSheets)
    {
        $pathToLastSheetFile = 'zip://' . $resourcePath . '#xl/worksheets/sheet' . $numSheets . '.xml';
        return $this->getLasRowNumberForFile($pathToLastSheetFile);
    }

    /**
     * @param string $resourcePath
     * @return int
     */
    private function getNumWrittenRowsUsingSharedStrings($resourcePath)
    {
        $pathToSharedStringsFile = 'zip://' . $resourcePath . '#xl/sharedStrings.xml';
        return $this->getLasRowNumberForFile($pathToSharedStringsFile);
    }

    /**
     * @param string $filePath
     * @return string
     */
    private function getLasRowNumberForFile($filePath)
    {
        $lastRowNumber = 0;

        // to avoid executing the regex of the entire file to get the last row number,
        // we only retrieve the last 200 characters of the shared strings file, as the cell value
        // contains the row number.
        $lastCharactersOfFile = $this->getLastCharactersOfFile($filePath, 200);

        // in sharedStrings.xml and sheetN.xml, the cell value will look like this:
        // <t>xlsx--[ROW_NUMBER]-[CELL_NUMBER]</t> or <t xml:space="preserve">xlsx--[ROW_NUMBER]-[CELL_NUMBER]</t>
        if (preg_match_all('/<t.*>xlsx--(\d+)-\d+<\/t>/', $lastCharactersOfFile, $matches)) {
            $lastMatch = array_pop($matches);
            $lastRowNumber = intval(array_pop($lastMatch));
        }

        return $lastRowNumber;
    }

    /**
     * @param string $filePath
     * @param int $numCharacters
     * @return string
     */
    private function getLastCharactersOfFile($filePath, $numCharacters)
    {
        // since we cannot execute "tail" on a file inside a zip, we need to copy it outside first
        $tmpFile = sys_get_temp_dir() . '/getLastCharacters.xml';
        copy($filePath, $tmpFile);

        // Get the last 200 characters
        $lastCharacters = `tail -c $numCharacters $tmpFile`;

        // remove the temporary file
        unlink($tmpFile);

        return $lastCharacters;
    }
}
