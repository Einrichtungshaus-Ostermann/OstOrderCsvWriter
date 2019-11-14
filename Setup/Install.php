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

namespace OstOrderCsvWriter\Setup;

use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;

class Install
{
    /**
     * ...
     *
     * @var array
     */
    public static $attributes = [
        's_core_paymentmeans_attributes' => [
            [
                'column' => 'ost_order_csv_writer_iwm_mapping',
                'type'   => 'string',
                'data'   => [
                    'label'            => 'IWM Mapping',
                    'helpText'         => 'Die ID der Zahlungsart für die IWM.',
                    'translatable'     => false,
                    'displayInBackend' => true,
                    'custom'           => false,
                    'position'         => 1400
                ]
            ],
            [
                'column' => 'ost_order_csv_writer_secure',
                'type'   => 'boolean',
                'data'   => [
                    'label'            => 'Sichere Zahlungsart',
                    'helpText'         => 'Bei sicheren Zahlungsarten wird der Zahlungsstatus nicht geprüft.',
                    'translatable'     => false,
                    'displayInBackend' => true,
                    'custom'           => false,
                    'position'         => 1410
                ]
            ]
        ],
        's_premium_dispatch_attributes' => [
            [
                'column' => 'ost_order_csv_writer_iwm_mapping',
                'type'   => 'string',
                'data'   => [
                    'label'            => 'IWM Mapping',
                    'helpText'         => 'Die ID der Versandart für die IWM.',
                    'translatable'     => false,
                    'displayInBackend' => true,
                    'custom'           => false,
                    'position'         => 1400
                ]
            ]
        ],
        's_order_attributes' => [
            [
                'column' => 'ost_order_csv_writer_import_order',
                'type'   => 'boolean',
                'data'   => [
                    'label'            => 'IWM Export aktivieren',
                    'helpText'         => 'Soll diese Bestellung für den nächsten IMW Import exportiert werden - unabhängig vom Datum? Bestell- und Zahlungsstatus müssen dennoch valide sein. Nach einem erfolgten Import wird dieses Freitextfeld automatisch wieder deaktiviert.',
                    'translatable'     => false,
                    'displayInBackend' => true,
                    'custom'           => false,
                    'position'         => 1410
                ]
            ]
        ],
    ];

    /**
     * Main bootstrap object.
     *
     * @var Plugin
     */
    protected $plugin;

    /**
     * ...
     *
     * @var InstallContext
     */
    protected $context;

    /**
     * ...
     *
     * @var ModelManager
     */
    protected $modelManager;

    /**
     * ...
     *
     * @var CrudService
     */
    protected $crudService;

    /**
     * ...
     *
     * @param Plugin         $plugin
     * @param InstallContext $context
     * @param ModelManager   $modelManager
     * @param CrudService    $crudService
     */
    public function __construct(Plugin $plugin, InstallContext $context, ModelManager $modelManager, CrudService $crudService)
    {
        // set params
        $this->plugin = $plugin;
        $this->context = $context;
        $this->modelManager = $modelManager;
        $this->crudService = $crudService;
    }

    /**
     * ...
     *
     * @throws \Exception
     */
    public function install()
    {
    }
}
