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

use Doctrine\ORM\QueryBuilder;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail as ArticleDetail;
use Shopware\Models\Attribute\Article as ArticleAttribute;
use Shopware\Models\Order\Billing;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Shipping;
use Shopware\Models\Order\Status;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Attribute\Payment as PaymentAttribute;
use Shopware\Models\Attribute\Dispatch as DispatchAttribute;

class ParserService
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
     * @param Order[] $orders
     *
     * @return array
     */
    public function parse(array $orders)
    {
        // the output array
        $arr = array(
            'auftraege' => array(),
            'positionen' => array(),
            'check' => array()
        );

        // loop every order
        foreach ($orders as $order) {
            // parse the order
            $arr['auftraege'][] = $this->parseOrder($order);

            // loop the positions
            foreach ($order->getDetails() as $position) {
                // and parse it
                $arr['positionen'][] = $this->parsePosition($order, $position);
            }
        }

        // parse the complete check
        $arr['check'] = $this->parseCheck($orders);

        // and return it
        return $arr;
    }

    /**
     * ...
     *
     * @param Order $order
     *
     * @return array
     */
    private function parseOrder(Order $order)
    {
        // get shortcodes for billing and shipping address
        $billing = $order->getBilling();
        $shipping = ($order->getShipping() === null) ? $order->getBilling() : $order->getShipping();

        // return parsed order
        return [
            'Bestellnummer'             => str_pad($order->getNumber(), 9, '0', STR_PAD_LEFT),
            'Datum'                     => $order->getOrderTime()->format('Y-m-d'),
            'Anrede'                    => $billing->getSalutation() === 'ms' ? 'Frau' : 'Herr',
            'Zuname'                    => $billing->getLastName(),
            'Vorname'                   => $billing->getFirstName(),
            'Firma'                     => $billing->getCompany(),
            'Strasse'                   => $billing->getStreet(),
            'Hausnummer'                => '',
            'Land'                      => $billing->getCountry()->getIso(),
            'PLZ'                       => $billing->getZipCode(),
            'Ort'                       => $billing->getCity(),
            'Etage'                     => $this->getFloor($billing),
            'FonPrivat'                 => $billing->getPhone(),
            'FonBeruflich'              => $billing->getPhone(),
            'FonMobil'                  => '',
            'Email'                     => $order->getCustomer()->getEmail(),
            'Geburtstag'                => $order->getCustomer()->getBirthday() !== null ? $order->getCustomer()->getBirthday()->format('Y-m-d') : '00-00-0000',
            'LieferadresseAnrede'       => $shipping->getSalutation() === 'ms' ? 'Frau' : 'Herr',
            'LieferadresseZuname'       => $shipping->getLastName(),
            'LieferadresseVorname'      => $shipping->getFirstName(),
            'LieferadresseFirma'        => $shipping->getCompany(),
            'LieferadresseStrasse'      => $shipping->getStreet(),
            'LieferadresseHausnummer'   => '',
            'LieferadresseLand'         => $shipping->getCountry()->getIso(),
            'LieferadressePLZ'          => $shipping->getZipCode(),
            'LieferadresseOrt'          => $shipping->getCity(),
            'LieferadresseEtage'        => $this->getFloor($shipping),
            'LieferadresseFonPrivat'    => $shipping->getPhone(),
            'LieferadresseFonBeruflich' => $shipping->getPhone(),
            'LieferadresseFonMobil'     => '',
            'Gesamtpreis'               => $order->getInvoiceAmount(),
            'Anzahlung'                 => '',
            'Bezahloption'              => $this->getPaymentMapping($order),
            'Lieferart'                 => $this->getDispatchMapping($order),
            'Standort'                  => '',
            'lieferkosten'              => $order->getInvoiceShipping(),
            'mwst'                      => ($order->getInvoiceAmount() + $order->getInvoiceShipping()) - ($order->getInvoiceAmountNet() + $order->getInvoiceShippingNet()),
            'mitteilung'                => str_replace(["\r", "\n", '"', ';', "'", '&'], '', $order->getCustomerComment()),
            'transaction_id'            => $order->getTransactionId(),
            'Wunschtermin'              => '',
            'Terminart'                 => '',
            'Lieferwunsch'              => '',
            'Zahlungs-ID'               => $order->getTransactionId(),
            'Newsletter J/N'            => '',
            'ext.Auftrags-ID'           => $order->getId(),
            'Zahlungsreferenz intern'   => $order->getTransactionId(),
            'Zahlungsreferenz extern'   => $order->getTransactionId(),
        ];
    }

    /**
     * ...
     *
     * @param Order $order
     * @param Detail $position
     *
     * @return array
     */
    private function parsePosition(Order $order, Detail $position)
    {
        /** @var ArticleDetail $article */
        $article = $this->modelManager->getRepository(ArticleDetail::class)->findOneBy(['number' => $position->getArticleNumber()]);

        // return parsed position
        return [
            'Firma'                   => $this->getMandator($article),
            'Bestellnummer'           => str_pad($position->getOrder()->getNumber(), 9, '0', STR_PAD_LEFT),
            'Menge'                   => $position->getQuantity(),
            'Artikelnummer'           => str_pad(explode('.', $position->getArticleNumber())[0], 7, '0', STR_PAD_LEFT),
            'Ausfuehrungskennzeichen' => substr_count($position->getArticleNumber(), '-') + 1 > 1 ? str_pad(explode('-', $position->getArticleNumber())[1], 5, '0', STR_PAD_LEFT) : '00000',
            'Ausfuehrung'             => '',
            'Abholpreis'              => number_format($position->getPrice(), 2, '.', ''),
            'Montage J/N'             => $this->getAssemblySurcharge($order, $position),
            'EAN13'                   => $position->getEan(),
            'Herstellerartikelnummer' => $this->getArticleSupplierNumber($article),
            'Diomex Konfig-ID'        => '',
            'Diomex Geo-ID'           => '',
            'Diomex Produkt-ID'       => '',
            'Verkaeufer-Nr'           => $this->getSellerNumber($order),
            'Provisionsschluessel'    => $this->getProvisionKey($order),
            'Anlieferart'             => '',
            'Lieferart'               => '',
            'Wunschtermin'            => '',
            'Terminart'               => ''
        ];
    }

    /**
     * ...
     *
     * @param Order[] $orders
     *
     * @return array
     */
    private function parseCheck(array $orders)
    {
        // ...
        return [
            'auftraege_gesamt'  => count($orders),
            'positionen_gesamt' => array_sum(array_map(function(Order $order) { return count($order->getDetails()); }, $orders)),
            'gesamtpreis'       => array_sum(array_map(function(Order $order) { return (float) $order->getInvoiceAmount(); }, $orders))
        ];
    }

    /**
     * ...
     *
     * @param Billing|Shipping $address
     *
     * @return string
     */
    private function getFloor($address)
    {
        // floor is saved within additional line
        return (empty((string) $address->getAdditionalAddressLine1()))
            ? 'EG'
            : (string) $address->getAdditionalAddressLine1();
    }

    /**
     * ...
     *
     * @param Order $order
     * @param Detail $position
     *
     * @return string
     */
    private function getAssemblySurcharge(Order $order, Detail $position)
    {
        // get via attribute
        return '';
    }

    /**
     * ...
     *
     * @param Order $order
     *
     * @return string
     */
    private function getSellerNumber(Order $order)
    {
        // ...
        return $this->configuration['defaultSellerId'];
    }

    /**
     * ...
     *
     * @param Order $order
     *
     * @return string
     */
    private function getProvisionKey(Order $order)
    {
        // ...
        return $this->configuration['defaultProvision'];
    }

    /**
     * ...
     *
     * @param ArticleDetail $article
     *
     * @return string
     */
    private function getMandator($article)
    {
        // ...
        return ($article instanceof ArticleDetail && $article->getAttribute() instanceof ArticleAttribute)
            ? $article->getAttribute()->getAttr1()
            : '';
    }

    /**
     * ...
     *
     * @param ArticleDetail $article
     *
     * @return string
     */
    private function getArticleSupplierNumber($article)
    {
        // ...
        return ($article instanceof ArticleDetail && $article->getAttribute() instanceof ArticleAttribute)
            ? $article->getAttribute()->getAttr9()
            : '';
    }

    /**
     * ...
     *
     * @param Order $order
     *
     * @return string
     */
    private function getPaymentMapping(Order $order)
    {
        // via attribute
        return ($order->getPayment() instanceof Payment && $order->getPayment()->getAttribute() instanceof PaymentAttribute)
            ? $order->getPayment()->getAttribute()->getOstOrderCsvWriterIwmMapping()
            : '';
    }

    /**
     * ...
     *
     * @param Order $order
     *
     * @return string
     */
    private function getDispatchMapping(Order $order)
    {
        // via attribute
        return ($order->getDispatch() instanceof Dispatch && $order->getDispatch()->getAttribute() instanceof DispatchAttribute)
            ? $order->getDispatch()->getAttribute()->getOstOrderCsvWriterIwmMapping()
            : '';
    }
}
