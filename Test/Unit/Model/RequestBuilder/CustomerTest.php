<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Model\RequestBuilder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Model\RequestBuilder\Customer;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class CustomerTest extends TestCase
{
    private Customer $builder;
    private Session&MockObject $session;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->session = $this->createMock(Session::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->builder = new Customer(
            $this->session,
            $this->customerRepository,
            $this->logger
        );
    }

    public function testGetPrimaryDeliveryDetailsReturnsDigitalForVirtualOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getIsVirtual')->willReturn(true);

        $result = $this->builder->getPrimaryDeliveryDetails($order);

        $this->assertSame('DIGITAL', $result['deliveryType']);
        $this->assertSame('DIGITAL', $result['deliveryMethod']);
        $this->assertArrayNotHasKey('deliveryPrice', $result);
    }

    public function testGetPrimaryDeliveryDetailsReturnsPhysicalForStandardOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getIsVirtual')->willReturn(false);
        $order->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $order->method('getShippingAmount')->willReturn(5.99);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');

        $result = $this->builder->getPrimaryDeliveryDetails($order);

        $this->assertSame('PHYSICAL', $result['deliveryType']);
        $this->assertSame('flatrate_flatrate', $result['deliveryMethod']);
        $this->assertSame('5.99', $result['deliveryPrice']['amountLocalCurrency']);
    }

    public function testGetPrimaryRecipientReturnsEmptyWhenNoShippingAddress(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn(null);

        $this->assertSame([], $this->builder->getPrimaryRecipient($order));
    }

    public function testGetPrimaryRecipientIncludesPhoneWhenPresent(): void
    {
        $address = $this->createAddressMock('John', 'Doe', 'john@example.com', '+33612345678');
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getCustomerEmail')->willReturn('john@example.com');

        $result = $this->builder->getPrimaryRecipient($order);

        $this->assertSame('John', $result['personalDetails']['firstName']);
        $this->assertSame('+33612345678', $result['phone'][0]['phone']);
    }

    public function testGetPrimaryRecipientOmitsPhoneWhenNull(): void
    {
        $address = $this->createAddressMock('John', 'Doe', 'john@example.com', null);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($address);

        $result = $this->builder->getPrimaryRecipient($order);

        $this->assertArrayNotHasKey('phone', $result);
    }

    public function testGetAccountOwnerInfoFallsToBillingForGuest(): void
    {
        $this->session->method('getCustomerData')->willReturn(null);

        $billingAddress = $this->createAddressMock('Jane', 'Guest', 'guest@example.com');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerId')->willReturn(null);
        $order->method('getBillingAddress')->willReturn($billingAddress);

        $result = $this->builder->getAccountOwnerInfo($order);

        $this->assertSame('Jane', $result['firstName']);
        $this->assertSame('Guest', $result['lastName']);
        $this->assertSame('guest@example.com', $result['email']);
        $this->assertArrayNotHasKey('accountId', $result);
    }

    public function testGetAccountOwnerInfoUsesSessionCustomer(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstname')->willReturn('Session');
        $customer->method('getLastname')->willReturn('User');
        $customer->method('getEmail')->willReturn('session@example.com');
        $customer->method('getId')->willReturn(42);
        $customer->method('getCreatedAt')->willReturn('2023-01-15 10:00:00');

        $this->session->method('getCustomerData')->willReturn($customer);

        $order = $this->createMock(OrderInterface::class);

        $result = $this->builder->getAccountOwnerInfo($order);

        $this->assertSame('Session', $result['firstName']);
        $this->assertSame('42', $result['accountId']);
    }

    public function testGetAccountOwnerInfoFallsBackToRepository(): void
    {
        $this->session->method('getCustomerData')->willReturn(null);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstname')->willReturn('Repo');
        $customer->method('getLastname')->willReturn('Customer');
        $customer->method('getEmail')->willReturn('repo@example.com');
        $customer->method('getId')->willReturn(55);
        $customer->method('getCreatedAt')->willReturn('2024-06-01 12:00:00');

        $this->customerRepository->method('getById')->with(55)->willReturn($customer);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerId')->willReturn(55);
        $order->method('getBillingAddress')->willReturn(null);

        $result = $this->builder->getAccountOwnerInfo($order);

        $this->assertSame('Repo', $result['firstName']);
    }

    public function testGetBillingDetailsReturnsEmptyWhenNoBillingAddress(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn(null);

        $this->assertSame([], $this->builder->getBillingDetails($order));
    }

    public function testGetBillingDetailsReturnsFullAddressData(): void
    {
        $address = $this->createAddressMock('Bill', 'Payer', 'bill@example.com');
        $address->method('getStreet')->willReturn(['123 Main St', 'Apt 4']);
        $address->method('getCity')->willReturn('Paris');
        $address->method('getPostcode')->willReturn('75001');
        $address->method('getCountryId')->willReturn('FR');
        $address->method('getRegion')->willReturn('Île-de-France');
        $address->method('getCompany')->willReturn('ACME');
        $address->method('getTelephone')->willReturn('+33100000000');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($address);
        $order->method('getCustomerEmail')->willReturn('bill@example.com');

        $result = $this->builder->getBillingDetails($order);

        $this->assertSame('Bill', $result['firstName']);
        $this->assertSame('123 Main St', $result['address1']);
        $this->assertSame('Apt 4', $result['address2']);
        $this->assertSame('FR', $result['countryCode']);
    }

    public function testGetCustomerAccountDataReturnsStaticStructure(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $result = $this->builder->getCustomerAccountData($order);

        $this->assertFalse($result['customerEngagement']['wishlist']['inUse']);
        $this->assertSame(0, $result['customerEngagement']['wishlist']['itemInListCount']);
    }

    private function createAddressMock(
        string $firstName = '',
        string $lastName = '',
        string $email = '',
        ?string $phone = null
    ): OrderAddressInterface&MockObject {
        $address = $this->createMock(OrderAddressInterface::class);
        $address->method('getFirstname')->willReturn($firstName);
        $address->method('getLastname')->willReturn($lastName);
        $address->method('getEmail')->willReturn($email);
        $address->method('getTelephone')->willReturn($phone);
        $address->method('getStreet')->willReturn(['Street 1']);
        $address->method('getCity')->willReturn('City');
        $address->method('getPostcode')->willReturn('12345');
        $address->method('getCountryId')->willReturn('FR');
        $address->method('getRegion')->willReturn('Region');
        $address->method('getCompany')->willReturn(null);
        $address->method('getCustomerAddressId')->willReturn(null);
        return $address;
    }
}
