<?php declare(strict_types=1);

/**
 * Einrichtungshaus Ostermann GmbH & Co. KG - Order Csv Writer
 *
 * @package   OstOrderCsvWriter
 *
 * @author    Tim Windelschmidt <tim.windelschmidt@fionera.de>
 * @copyright 2018 Einrichtungshaus Ostermann GmbH & Co. KG
 * @license   proprietary
 */

namespace OstOrderCsvWriter\Services;

class CsvWriterService
{
    /**
     * ...
     *
     * @param array $data
     * @param string $directory     csv/order-export
     * @param string $filePrefix    moebel-shop-20190101-ab42c5f9
     *
     * @return void
     */
    public function write(array $data, $directory, $filePrefix)
    {
        // we need valid data
        if (!isset($data['auftraege']) || !is_array($data['auftraege']) || count($data['auftraege']) === 0) {
            // nothing to do
            return;
        }

        // we need a sub array for the check to make it worth with our foreach
        $data['check'] = array($data['check']);

        // loop every aspect
        foreach ($data as $key => $content) {
            // open the file
            $file = fopen($directory . '/' . $filePrefix .  '-' . $key . '.csv', 'cb+');

            // write the header
            fwrite($file, iconv('UTF-8', 'Windows-1252', implode(array_keys($content[0]), ';')) . "\n");

            // loop every row
            foreach ($content as $row) {
                // invalid data?
                if ($row === []) {
                    // ignore it
                    continue;
                }

                // write the row
                fwrite($file, iconv('UTF-8', 'Windows-1252', implode($row, ';')) . "\n");
            }

            // close the file
            fclose($file);
        }
    }
}
