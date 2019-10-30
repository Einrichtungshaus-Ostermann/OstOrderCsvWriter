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

namespace OstOrderCsvWriter\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Enlight_Components_Db_Adapter_Pdo_Mysql as Db;
use Shopware\Components\Model\ModelManager;
use OstOrderCsvWriter\Services\OrderService;
use OstOrderCsvWriter\Services\ParserService;
use OstOrderCsvWriter\Services\CsvWriterService;

class ExportOrdersCommand extends ShopwareCommand
{
    /**
     * ...
     *
     * @var Db
     */
    private $db;

    /**
     * ...
     *
     * @var ModelManager
     */
    private $modelManager;

    /**
     * ...
     *
     * @var array
     */
    private $configuration;

    /**
     * @param Db $db
     * @param ModelManager $modelManager
     * @param array $configuration
     */
    public function __construct(Db $db, ModelManager $modelManager, array $configuration)
    {
        parent::__construct();
        $this->db = $db;
        $this->modelManager = $modelManager;
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // ...
        $output->writeln('reading orders');

        /** @var OrderService $orderService */
        $orderService = Shopware()->Container()->get('ost_order_csv_writer.order_service');

        // get the orders
        $orders = $orderService->get();

        // log
        $output->writeln('orders found: ' . count($orders));

        // no orders found?!
        if (count($orders) === 0) {
            // stop
            $output->writeln('done');
            $output->writeln('');
            return;
        }

        // ...
        $output->writeln('parsing orders');

        /** @var ParserService $parserService */
        $parserService = Shopware()->Container()->get('ost_order_csv_writer.parser_service');

        // parse them
        $parsed = $parserService->parse($orders);

        // ...
        $output->writeln('writing csv');

        // create vars
        $directory = Shopware()->Container()->getParameter('shopware.app.rootdir') . trim($this->configuration['csvFolder'], '/');
        $filePrefix = $this->configuration['csvPrefix'] . date('YmdHis') . '-' . substr(md5(microtime()), 0, 8);

        // log
        $output->writeln('directory: ' . $directory);
        $output->writeln('file prefix: ' . $filePrefix);

        /** @var CsvWriterService $csvWrtiterService */
        $csvWrtiterService = Shopware()->Container()->get('ost_order_csv_writer.csv_writer_service');

        // parse them
        $csvWrtiterService->write(
            $parsed,
            $directory,
            $filePrefix
        );

        // done
        $output->writeln('');
    }
}
