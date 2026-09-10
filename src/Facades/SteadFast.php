<?php

namespace SabitAhmad\SteadFast\Facades;

use Illuminate\Support\Facades\Facade;
use SabitAhmad\SteadFast\DTO\BalanceResponse;
use SabitAhmad\SteadFast\DTO\BulkOrderResponse;
use SabitAhmad\SteadFast\DTO\FraudCheckResponse;
use SabitAhmad\SteadFast\DTO\OrderRequest;
use SabitAhmad\SteadFast\DTO\OrderResponse;
use SabitAhmad\SteadFast\DTO\PaymentResponse;
use SabitAhmad\SteadFast\DTO\PoliceStationResponse;
use SabitAhmad\SteadFast\DTO\ReturnRequest;
use SabitAhmad\SteadFast\DTO\ReturnResponse;
use SabitAhmad\SteadFast\DTO\StatusResponse;

/**
 * @method static OrderResponse createOrder(OrderRequest $order)
 * @method static BulkOrderResponse bulkCreate(array $orders, ?bool $useQueue = null)
 * @method static BulkOrderResponse processBulkOrders(array $orders)
 * @method static BalanceResponse getBalance()
 * @method static StatusResponse checkStatusByConsignmentId(int $id)
 * @method static StatusResponse checkStatusByInvoice(string $invoice)
 * @method static StatusResponse checkStatusByTrackingCode(string $trackingCode)
 * @method static ReturnResponse createReturnRequest(ReturnRequest $returnRequest)
 * @method static ReturnResponse getReturnRequest(int $id)
 * @method static array<int, ReturnResponse> getReturnRequests()
 * @method static array<int, PaymentResponse> getPayments(?int $page = null)
 * @method static PaymentResponse getPayment(int|string $paymentId)
 * @method static array<int, PoliceStationResponse> getPoliceStations(bool $forceRefresh = false)
 * @method static FraudCheckResponse checkFraud(string $phoneNumber)
 * @method static array testConnection()
 * @method static bool clearCache(?string $key = null)
 * @method static array getConfig()
 *
 * @see \SabitAhmad\SteadFast\SteadFast
 */
class SteadFast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SabitAhmad\SteadFast\SteadFast::class;
    }
}
