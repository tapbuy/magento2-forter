<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Model\RequestBuilder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Tapbuy\Forter\Api\RequestBuilder\CustomerBuilderInterface;

class Customer implements CustomerBuilderInterface
{
    private const DELIVERY_TYPE_PHYSICAL = 'PHYSICAL';
    private const DELIVERY_TYPE_DIGITAL = 'DIGITAL';

    /**
     * @param Session $session
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private readonly Session $session,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * Get customer.
     *
     * @param OrderInterface $order
     * @return CustomerInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function getCustomer(OrderInterface $order): CustomerInterface
    {
        $customer = $this->session->getCustomerData();
        if ($customer !== null) {
            return $customer;
        }

        $customerId = $order->getCustomerId();
        if ($customerId !== null) {
            // If the customer can't be retrieved from the session. For example in cases of order send failure.
            return $this->customerRepository->getById($customerId);
        }

        throw new NoSuchEntityException(__('Unable to get customer.'));
    }

    /**
     * Get primary delivery details.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getPrimaryDeliveryDetails(OrderInterface $order): array
    {
        if ($order->getIsVirtual()) {
            return [
                'deliveryType' => self::DELIVERY_TYPE_DIGITAL,
                'deliveryMethod' => 'DIGITAL'
            ];
        }

        return [
            'deliveryType' => self::DELIVERY_TYPE_PHYSICAL,
            'deliveryMethod' => (string) $order->getShippingMethod(),
            'deliveryPrice' => [
                'amountLocalCurrency' => (string) $order->getShippingAmount(),
                'currency' => $order->getOrderCurrencyCode()
            ]
        ];
    }

    /**
     * Get primary recipient.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getPrimaryRecipient(OrderInterface $order): array
    {
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress === null) {
            return [];
        }

        $recipient = [
            'personalDetails' => [
                'firstName' => $shippingAddress->getFirstname() ?? '',
                'lastName' => $shippingAddress->getLastname() ?? '',
                'email' => $shippingAddress->getEmail() ?? $order->getCustomerEmail() ?? ''
            ],
            'address' => $this->getAddressData($shippingAddress)
        ];

        $phone = $shippingAddress->getTelephone();
        if ($phone !== null) {
            $recipient['phone'] = [
                ['phone' => $phone]
            ];
        }

        return $recipient;
    }

    /**
     * Get account owner info.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getAccountOwnerInfo(OrderInterface $order): array
    {
        $customer = null;
        try {
            $customer = $this->getCustomer($order);
        } catch (NoSuchEntityException | LocalizedException $e) {
            // Do nothing.
        }
        // Customer not logged in.
        if ($customer === null) {
            $billingAddress = $order->getBillingAddress();

            return [
                'firstName' => $billingAddress?->getFirstname(),
                'lastName' => $billingAddress?->getLastname(),
                'email' => $billingAddress?->getEmail(),
            ];
        }

        return [
            'firstName' => (string)$customer->getFirstname(),
            'lastName' => (string)$customer->getLastname(),
            'email' => (string)$customer->getEmail(),
            'accountId' => (string)$customer->getId(),
            'created' => strtotime($customer->getCreatedAt()),
        ];
    }

    /**
     * Get customer account data.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getCustomerAccountData(OrderInterface $order): array
    {
        return [
            'customerEngagement' => [
                'wishlist' => [
                    'inUse' => false,
                    'itemInListCount' => 0
                ]
            ]
        ];
    }

    /**
     * Get billing details in flat format for TapBuy API.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getBillingDetails(OrderInterface $order): array
    {
        $billingAddress = $order->getBillingAddress();

        if ($billingAddress === null) {
            return [];
        }

        $street = $billingAddress->getStreet();

        return [
            'firstName' => $billingAddress->getFirstname() ?? '',
            'lastName' => $billingAddress->getLastname() ?? '',
            'email' => $billingAddress->getEmail() ?? $order->getCustomerEmail() ?? '',
            'address1' => $street[0] ?? '',
            'address2' => $street[1] ?? '',
            'city' => $billingAddress->getCity() ?? '',
            'postalCode' => $billingAddress->getPostcode() ?? '',
            'countryCode' => $billingAddress->getCountryId() ?? '',
            'region' => $billingAddress->getRegion() ?? '',
            'company' => $billingAddress->getCompany() ?? '',
            'phone' => $billingAddress->getTelephone() ?? ''
        ];
    }

    /**
     * Get address data.
     *
     * @param OrderAddressInterface $address
     * @return array
     */
    private function getAddressData(OrderAddressInterface $address): array
    {
        $street = $address->getStreet();
        $address1 = $street[0] ?? '';
        $address2 = $street[1] ?? '';

        return [
            'address1' => $address1,
            'address2' => $address2,
            'city' => $address->getCity() ?? '',
            'zip' => $address->getPostcode() ?? '',
            'country' => $address->getCountryId() ?? '',
            'region' => $address->getRegion() ?? '',
            'company' => $address->getCompany() ?? '',
            'savedData' => [
                'usedSavedData' => $address->getCustomerAddressId() !== null,
                'choseToSaveData' => false
            ]
        ];
    }
}
