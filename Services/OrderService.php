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

use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

class OrderService
{
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
     * ...
     *
     * @param ModelManager $modelManager
     * @param array $configuration
     */
    public function __construct(ModelManager $modelManager, array $configuration)
    {
        $this->modelManager = $modelManager;
        $this->configuration = $configuration;
    }

    /**
     * ...
     *
     * @return Order[]
     */
    public function get()
    {
        // get every order which should get imported by flag
        $query = '
            SELECT orderID
            FROM s_order_attributes
            WHERE ost_order_csv_writer_import_order = 1
        ';
        $ids = Shopware()->Db()->fetchAll($query);

        // loop every order
        foreach ($ids as $arr) {
            // get the order id
            $id = (integer) $arr['orderID'];

            // set status
            Shopware()->Modules()->Order()->setOrderStatus($id, 0, false, null);
            Shopware()->Modules()->Order()->setPaymentStatus($id, 17, false, null);
        }

        // create a query builder
        $builder = $this->modelManager->createQueryBuilder();

        // set it up with default values
        $builder->select(array('orders', 'details', 'customer', 'billing', 'billingAttribute', 'shipping', 'shippingAttribute', 'payment', 'paymentAttribute', 'dispatch', 'dispatchAttribute'))
            ->from(Order::class, 'orders')
            ->leftJoin('orders.attribute', 'ordersAttribute')
            ->leftJoin('orders.details', 'details')
            ->leftJoin('orders.customer', 'customer')
            ->leftJoin('orders.billing', 'billing')
            ->leftJoin('billing.attribute', 'billingAttribute')
            ->leftJoin('orders.shipping', 'shipping')
            ->leftJoin('shipping.attribute', 'shippingAttribute')
            ->leftJoin('orders.orderStatus', 'orderStatus')
            ->leftJoin('orders.paymentStatus', 'paymentStatus')
            ->leftJoin('orders.payment', 'payment')
            ->leftJoin('payment.attribute', 'paymentAttribute')
            ->leftJoin('orders.dispatch', 'dispatch')
            ->leftJoin('dispatch.attribute', 'dispatchAttribute')
            ->where('orderStatus.id = 0')
            ->andWhere('orders.number > 0')
            ->andWhere('((orders.orderTime >= :startDate) OR (ordersAttribute.ostOrderCsvWriterImportOrder = 1))')
            ->andWhere('((paymentAttribute.ostOrderCsvWriterSecure = 1) OR (paymentStatus.id = :statusCompletelyPaid))')
            ->setParameter('statusCompletelyPaid', Status::PAYMENT_STATE_COMPLETELY_PAID)
            ->setParameter('startDate', date('Y-m-d H:i:s', strtotime('-' . (integer) $this->configuration['orderHours'] . ' hours')))
            ->orderBy('orders.id', 'ASC');

        // get the orders
        $orders = $builder->getQuery()->getResult();

        // reset every order import flag
        $query = '
            UPDATE s_order_attributes
            SET ost_order_csv_writer_import_order = 0
            WHERE ost_order_csv_writer_import_order = 1
        ';
        Shopware()->Db()->query($query);

        // return them
        return $orders;
    }
}
