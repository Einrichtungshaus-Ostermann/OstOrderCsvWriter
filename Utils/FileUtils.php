<?php declare(strict_types=1);

namespace OstOrderCsvWriter\Utils;

class FileUtils
{
    public static function arrayToCSVFiles($csvPrefix, $filePath, $dataArray, $filenameAdditionBefore = ''): int
    {
        $exportedFiles = 0;
        $time = gettimeofday()['sec'] . gettimeofday()['usec'];
        foreach ($dataArray as $filename => $content) {
            if (\count($content) > 0) {
                $file = fopen($filePath . $csvPrefix . '_' . $time . ($filenameAdditionBefore !== '' ? ('_' . $filenameAdditionBefore) : '') . '_' . $filename . '.csv', 'cb+');
                fwrite($file, iconv('UTF-8', 'Windows-1252', implode(array_keys($content[0]), ';')) . "\n");
                foreach ($content as $row) {
                    if ($row === []) {
                        continue;
                    }
                    fwrite($file, iconv('UTF-8', 'Windows-1252', implode($row, ';')) . "\n");
                }
                fclose($file);
                ++$exportedFiles;
            }
        }

        return $exportedFiles;
    }
}
