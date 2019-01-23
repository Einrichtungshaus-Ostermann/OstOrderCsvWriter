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

use OstOrderCsvWriter\Utils\FileUtils;
use OstOrderLiveExport\Services\OrderExportServiceInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail as ArticleDetail;
use Shopware\Models\Order\Billing;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Shipping;
use Shopware\Models\Order\Status;

class CsvExportService implements OrderExportServiceInterface
{
    /**
     * @var ModelManager
     */
    private $modelManager;
    /**
     * @var array
     */
    private $config;

    public function __construct(ModelManager $modelManager, array $config)
    {
        $this->modelManager = $modelManager;
        $this->config = $config;
    }

    /**
     * @param string $number
     */
    public function export(string $number)
    {
        /** @var Order $order */
        $order = $this->modelManager->getRepository(Order::class)->findOneBy(['number' => $number]);

        if ($order === null) {
            return;
        }

        $exportData = [
            'auftraege' => [],
            'positionen' => [],
            'check' => []
        ];

        if ($order->getPaymentStatus()->getId() !== Status::PAYMENT_STATE_COMPLETELY_PAID && !in_array($order->getPayment()->getId(), explode(',', $this->config['securePayments']), true)) {
            return;
        }

        $exportData['auftraege'][] = $this->getRowForOrder($order);
        $exportData['positionen'][] = $this->getPositionRowsForOrder($order);
        $exportData['check'][] = $this->getCheckFileRow(\count($exportData['auftraege']), \count($exportData['positionen']), $order->getInvoiceAmount());

        FileUtils::arrayToCSVFiles($this->config['csvPrefix'], __DIR__ . '/../../../../', $exportData);
    }

    public function getRowForOrder(Order $order): array
    {
        /** @var Billing|Shipping $shippingAddress */
        $shippingAddress = null;
        if ($order->getShipping() !== null) {
            $shippingAddress = $order->getShipping(); // Shipping
        } elseif ($order->getBilling() !== null) {
            $shippingAddress = $order->getBilling(); // Billing
        } else {
            return [];
        }

        /** @var Billing|Shipping $addressInfo */
        $addressInfo = null;
        if ($order->getBilling() !== null) {
            $addressInfo = $order->getBilling(); // Billing
        } else {
            $addressInfo = $shippingAddress;
        }

        return [
            'Bestellnummer'             => str_pad($order->getNumber(), 9, '0', STR_PAD_LEFT),
            'Datum'                     => $order->getOrderTime()->format('Y-m-d'),
            'Anrede'                    => $addressInfo->getSalutation() === 'ms' ? 'Frau' : 'Herr',
            'Zuname'                    => $addressInfo->getLastName(),
            'Vorname'                   => $addressInfo->getFirstName(),
            'Firma'                     => $addressInfo->getCompany(),
            'Strasse'                   => $addressInfo->getStreet(),
            'Hausnummer'                => '',
            'Land'                      => $addressInfo->getCountry()->getIso(),
            'PLZ'                       => $addressInfo->getZipCode(),
            'Ort'                       => $addressInfo->getCity(),
            'Etage'                     => 'EG',
            'FonPrivat'                 => $addressInfo->getPhone(),
            'FonBeruflich'              => '',
            'FonMobil'                  => '',
            'Email'                     => $order->getCustomer()->getEmail(),
            'Geburtstag'                => $order->getCustomer()->getBirthday() !== null ? $order->getCustomer()->getBirthday()->format('Y-m-d') : '00-00-0000',
            'LieferadresseAnrede'       => $shippingAddress->getSalutation() === 'ms' ? 'Frau' : 'Herr',
            'LieferadresseZuname'       => $shippingAddress->getLastName(),
            'LieferadresseVorname'      => $shippingAddress->getFirstName(),
            'LieferadresseFirma'        => $shippingAddress->getCompany(),
            'LieferadresseStrasse'      => $shippingAddress->getStreet(),
            'LieferadresseHausnummer'   => '',
            'LieferadresseLand'         => $shippingAddress->getCountry()->getIso(),
            'LieferadressePLZ'          => $shippingAddress->getZipCode(),
            'LieferadresseOrt'          => $shippingAddress->getCity(),
            'LieferadresseEtage'        => 'EG',
            'LieferadresseFonPrivat'    => $shippingAddress->getPhone(),
            'LieferadresseFonBeruflich' => '',
            'LieferadresseFonMobil'     => '',
            'Gesamtpreis'               => $order->getInvoiceAmount(),
            'Anzahlung'                 => '',
            'Bezahloption'              => $this->parseBackendSetting('paymentArray')[$order->getPayment()->getId()] ?? '',
            'Lieferart'                 => $this->parseBackendSetting('shippingArray')[$order->getDispatch()->getId()] ?? '',
            'Standort'                  => '',
            'lieferkosten'              => $order->getInvoiceShipping(),
            'mwst'                      => ($order->getInvoiceAmount() + $order->getInvoiceShipping()) - ($order->getInvoiceAmountNet() + $order->getInvoiceShippingNet()),
            'mitteilung'                => str_replace(["\r", "\n", '"', ';', "'", '&'], '', $order->getComment()),
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
     * @param Detail $orderDetail
     *
     * @return array
     */
    public function getPositionRow($orderDetail): array
    {
        /** @var Article $article */
        $article = $this->modelManager->getRepository(ArticleDetail::class)->findOneBy(['articleId' => $orderDetail->getArticleId()]);
        $orderDetailAttributes = $this->modelManager->toArray($orderDetail->getAttribute());
        $orderAttributes = $this->modelManager->toArray($orderDetail->getOrder()->getAttribute());

//        $ausfuerung = '';
//        if ($orderDetailAttributes['BurgIntegrationIdmconfigXml'] !== null) {
//            $number = simplexml_load_string($orderDetailAttributes['BurgIntegrationIdmconfigXml'])->xpath('/Documents/Document/Orders/Order/Reference/Number/Number')[0];
//
//            $ausfuerung .= str_pad('B2C_IDMCONFIG_NUMBER: ' . $number, 40, ' ');
//            $ausfuerung .= $orderDetailAttributes['BurgIntegrationIdmconfigBeschreibung'];
//        }

        $sellerNumber = '80';
        if (!empty($this->config['sellerIdField']) && !empty($orderAttributes[$this->config['sellerIdField']])) {
            $sellerNumber = $orderAttributes[$this->config['sellerIdField']];
        }

        $row = [
            'Firma'                   => $article === null ? '' : $article->getAttribute()->getAttr1(),
            'Bestellnummer'           => str_pad($orderDetail->getOrder()->getNumber(), 9, '0', STR_PAD_LEFT),
            'Menge'                   => $orderDetail->getQuantity(),
            'Artikelnummer'           => str_pad(explode('.', $orderDetail->getArticleNumber())[0], 7, '0', STR_PAD_LEFT),
            'Ausfuehrungskennzeichen' => substr_count($orderDetail->getArticleNumber(), '-') + 1 > 1 ? str_pad(explode('-', $orderDetail->getArticleNumber())[1], 5, '0', STR_PAD_LEFT) : '00000',
            'Ausfuehrung'             => '', //str_replace(["\r", "\n", '"', ';', "'", '&'], '', $ausfuerung),
            'Abholpreis'              => number_format($orderDetail->getPrice(), 2, '.', ''),
            'Montage J/N'             => $orderDetail->getAttribute()->getBestitMontage(),
            'EAN13'                   => $orderDetail->getEan(),
            'Herstellerartikelnummer' => $article === null ? '' : $article->getAttribute()->getAttr9(),
            'Diomex Konfig-ID'        => '',
            'Diomex Geo-ID'           => '',
            'Diomex Produkt-ID'       => '',
            'Verkaeufer-Nr'           => $sellerNumber,
            'Provisionsschluessel'    => '99',
            'Anlieferart'             => '',
            'Lieferart'               => '',
            'Wunschtermin'            => '',
            'Terminart'               => ''
        ];

        return $row;
    }

    public function getCheckFileRow($orderAmount, $positionAmount, $totalPrice): array
    {
        return [
            'auftraege_gesamt'  => $orderAmount,
            'positionen_gesamt' => $positionAmount,
            'gesamtpreis'       => $totalPrice
        ];
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    public function getPositionRowsForOrder(Order $order): array
    {
        $positions = [];

        /** @var Detail $orderDetail */
        foreach ($order->getDetails() as $orderDetail) {
            $positions[] = $this->getPositionRow($orderDetail);
        }

        return array_merge(...$positions);
    }

    public function parseBackendSetting($settingId): array
    {
        $settingArrayWithDelimiter = explode(',', $this->config[$settingId]);

        $settings = [];
        foreach ($settingArrayWithDelimiter as $value) {
            $temp = explode('|', $value);

            $settings += [
                $temp[0] => $temp[1]
            ];
        }

        return $settings;
    }
}