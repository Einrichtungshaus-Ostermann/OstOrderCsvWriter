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

use Exception;
use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;

class Update
{
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
     */
    public function install()
    {
        // install updates
        $this->update('0.0.0');
    }

    /**
     * ...
     *
     * @param string $version
     */
    public function update($version)
    {
        // switch old version
        switch ($version) {
            case '0.0.0':
            case '1.0.0':
                $this->updateAttributes();
            case '1.1.0':
            case '1.1.1':
            case '1.1.2':
            case '1.1.3':
            case '1.1.4':
            case '1.1.5':
                $this->updateAttributes();
        }
    }

    /**
     * ...
     *
     * @throws Exception
     */
    private function updateAttributes()
    {
        // ...
        foreach (Install::$attributes as $table => $attributes) {
            foreach ($attributes as $attribute) {
                try {
                    $this->crudService->update(
                        $table,
                        $attribute['column'],
                        $attribute['type'],
                        $attribute['data']
                    );
                } catch (Exception $exception) {}
            }
        }

        // ...
        try {
            $this->modelManager->generateAttributeModels(array_keys(Install::$attributes));
        } catch (Exception $exception) {}
    }
}
