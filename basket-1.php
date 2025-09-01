<?php

// ================================
// CONTRACTS (INTERFACES)
// ================================

// app/Payment/Contracts/PaymentGatewayInterface.php
namespace App\Payment\Contracts;

interface PaymentGatewayInterface
{ 
    public function initializePayment(array $data): array;
    public function verifyPayment(string $reference): array;
    public function processRefund(array $data): array;
    public function getTransactionDetails(string $transactionId): array;
    public function getSupportedCurrencies(): array;
    public function getSupportedPaymentMethods(): array;
    public function getName(): string;
}

// app/Payment/Contracts/PaymentRepositoryInterface.php
namespace App\Payment\Contracts;

interface PaymentRepositoryInterface
{
    public function create(array $data): object;
    public function findByReference(string $reference): ?object;
    public function findByTransactionId(string $transactionId): ?object;
    public function update(int $id, array $data): bool;
    public function getByStatus(string $status): array;
    public function getByGateway(string $gateway): array;
    public function getRecentPayments(int $limit = 10): array;
}

// app/Payment/Contracts/WebhookHandlerInterface.php
namespace App\Payment\Contracts;

interface WebhookHandlerInterface
{
    public function handle(array $payload, string $signature): bool;
    public function verifySignature(array $payload, string $signature): bool;
    public function extractEventData(array $payload): array;
}

// app/Payment/Contracts/PaymentServiceInterface.php
namespace App\Payment\Contracts;

interface PaymentServiceInterface
{
    public function processPayment(array $data, string $gateway): array;
    public function verifyPayment(string $reference): array;
    public function processRefund(array $data): array;
    public function handleWebhook(array $payload, string $gateway, string $signature): bool;
}

// ================================
// ENUMS
// ================================

// app/Payment/Enums/PaymentStatus.php
namespace App\Payment\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIAL_REFUND = 'partial_refund';
}

// app/Payment/Enums/PaymentMethod.php
namespace App\Payment\Enums;

enum PaymentMethod: string
{
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case USSD = 'ussd';
    case QR_CODE = 'qr_code';
    case MOBILE_MONEY = 'mobile_money';
}

// app/Payment/Enums/GatewayType.php
namespace App\Payment\Enums;

enum GatewayType: string
{
    case STRIPE = 'stripe';
    case PAYSTACK = 'paystack';
    case FLUTTERWAVE = 'flutterwave';
    case RAZORPAY = 'razorpay';
}

// app/Payment/Enums/Currency.php
namespace App\Payment\Enums;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case NGN = 'NGN';
    case GHS = 'GHS';
    case KES = 'KES';
    case ZAR = 'ZAR';
}

// ================================
// EXCEPTIONS
// ================================

// app/Payment/Exceptions/PaymentException.php
namespace App\Payment\Exceptions;

use Exception;

abstract class PaymentException extends Exception
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

// app/Payment/Exceptions/PaymentGatewayException.php
namespace App\Payment\Exceptions;

class PaymentGatewayException extends PaymentException
{
    public static function gatewayError(string $gateway, string $error, array $context = []): self
    {
        return new self("Payment gateway error from {$gateway}: {$error}", 500, null, $context);
    }

    public static function unsupportedGateway(string $gateway): self
    {
        return new self("Unsupported payment gateway: {$gateway}", 400);
    }
}

// app/Payment/Exceptions/InvalidPaymentDataException.php
namespace App\Payment\Exceptions;

class InvalidPaymentDataException extends PaymentException
{
    public static function missingField(string $field): self
    {
        return new self("Missing required field: {$field}", 422);
    }

    public static function invalidAmount(float $amount): self
    {
        return new self("Invalid payment amount: {$amount}", 422);
    }
}

// app/Payment/Exceptions/WebhookVerificationException.php
namespace App\Payment\Exceptions;

class WebhookVerificationException extends PaymentException
{
    public static function invalidSignature(string $gateway): self
    {
        return new self("Invalid webhook signature from {$gateway}", 401);
    }
}

// ================================
// EVENTS
// ================================

// app/Payment/Events/PaymentInitiated.php
namespace App\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentInitiated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly object $payment,
        public readonly array $metadata = []
    ) {}
}

// app/Payment/Events/PaymentCompleted.php
namespace App\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly object $payment,
        public readonly array $metadata = []
    ) {}
}

// app/Payment/Events/PaymentFailed.php
namespace App\Payment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly object $payment,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {}
}

// ================================
// VALUE OBJECTS
// ================================

// app/Payment/ValueObjects/Money.php
namespace App\Payment\ValueObjects;

use App\Payment\Enums\Currency;
use InvalidArgumentException;

class Money
{
    public function __construct(
        private readonly float $amount,
        private readonly Currency $currency
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getAmountInCents(): int
    {
        return (int) round($this->amount * 100);
    }

    public function format(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency->value;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}

// app/Payment/ValueObjects/CustomerInfo.php
namespace App\Payment\ValueObjects;

class CustomerInfo
{
    public function __construct(
        private readonly string $email,
        private readonly ?string $name = null,
        private readonly ?string $phone = null,
        private readonly array $metadata = []
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            'phone' => $this->phone,
            'metadata' => $this->metadata
        ];
    }
}

// ================================
// FACTORY
// ================================

// app/Payment/Factories/PaymentGatewayFactory.php
namespace App\Payment\Factories;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Enums\GatewayType;
use App\Payment\Exceptions\PaymentGatewayException;
use App\Payment\Gateways\Stripe\StripeGateway;
use App\Payment\Gateways\Paystack\PaystackGateway;
use App\Payment\Gateways\Flutterwave\FlutterwaveGateway;
use Illuminate\Container\Container;

class PaymentGatewayFactory
{
    private Container $container;
    private array $gateways = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->registerGateways();
    }

    public function create(GatewayType|string $type): PaymentGatewayInterface
    {
        $gatewayType = is_string($type) ? GatewayType::tryFrom($type) : $type;

        if (!$gatewayType) {
            throw PaymentGatewayException::unsupportedGateway((string) $type);
        }

        if (!isset($this->gateways[$gatewayType->value])) {
            throw PaymentGatewayException::unsupportedGateway($gatewayType->value);
        }

        return $this->container->make($this->gateways[$gatewayType->value]);
    }

    public function getSupportedGateways(): array
    {
        return array_keys($this->gateways);
    }

    private function registerGateways(): void
    {
        $this->gateways = [
            GatewayType::STRIPE->value => StripeGateway::class,
            GatewayType::PAYSTACK->value => PaystackGateway::class,
            GatewayType::FLUTTERWAVE->value => FlutterwaveGateway::class,
        ];
    }
}

// ================================
// REPOSITORIES
// ================================

// app/Payment/Repositories/PaymentRepository.php
namespace App\Payment\Repositories;

use App\Models\Payment;
use App\Payment\Contracts\PaymentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(private Payment $model) {}

    public function create(array $data): object
    {
        return $this->model->create($data);
    }

    public function findByReference(string $reference): ?object
    {
        return $this->model->where('reference', $reference)->first();
    }

    public function findByTransactionId(string $transactionId): ?object
    {
        return $this->model->where('transaction_id', $transactionId)->first();
    }

    public function update(int $id, array $data): bool
    {
        $payment = $this->model->findOrFail($id);
        return $payment->update($data);
    }

    public function getByStatus(string $status): array
    {
        return $this->model->where('status', $status)->get()->toArray();
    }

    public function getByGateway(string $gateway): array
    {
        return $this->model->where('gateway_type', $gateway)->get()->toArray();
    }

    public function getRecentPayments(int $limit = 10): array
    {
        return $this->model->latest()->limit($limit)->get()->toArray();
    }

    public function findOrFail(int $id): object
    {
        return $this->model->findOrFail($id);
    }

    public function getSuccessfulPaymentsByDateRange(\DateTime $start, \DateTime $end): Collection
    {
        return $this->model
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->get();
    }
}

// ================================
// GATEWAY IMPLEMENTATIONS
// ================================

// app/Payment/Gateways/Stripe/StripeGateway.php
namespace App\Payment\Gateways\Stripe;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Enums\Currency;
use App\Payment\Enums\PaymentMethod;
use App\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeGateway implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $baseUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->secretKey = config('payment.gateways.stripe.secret_key');
    }

    public function initializePayment(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post($this->baseUrl . '/payment_intents', [
                'amount' => $data['amount'] * 100, // Convert to cents
                'currency' => strtolower($data['currency']),
                'payment_method_types' => ['card'],
                'metadata' => $data['metadata'] ?? [],
                'receipt_email' => $data['customer']['email'],
            ]);

            if ($response->failed()) {
                throw PaymentGatewayException::gatewayError('Stripe', $response->json('error.message', 'Unknown error'));
            }

            $responseData = $response->json();

            return [
                'success' => true,
                'reference' => $data['reference'],
                'transaction_id' => $responseData['id'],
                'authorization_url' => null,
                'client_secret' => $responseData['client_secret'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => $this->mapStatus($responseData['status']),
                'gateway_response' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('Stripe payment initialization failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw PaymentGatewayException::gatewayError('Stripe', $e->getMessage());
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            // In Stripe, we'd typically verify using the payment intent ID
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/payment_intents/' . $reference);

            if ($response->failed()) {
                throw PaymentGatewayException::gatewayError('Stripe', 'Payment verification failed');
            }

            $responseData = $response->json();

            return [
                'success' => $responseData['status'] === 'succeeded',
                'status' => $this->mapStatus($responseData['status']),
                'reference' => $reference,
                'transaction_id' => $responseData['id'],
                'amount' => $responseData['amount'] / 100,
                'currency' => strtoupper($responseData['currency']),
                'gateway_response' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('Stripe payment verification failed', [
                'error' => $e->getMessage(),
                'reference' => $reference
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    public function processRefund(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post($this->baseUrl . '/refunds', [
                'payment_intent' => $data['transaction_id'],
                'amount' => isset($data['amount']) ? $data['amount'] * 100 : null,
                'reason' => $data['reason'] ?? 'requested_by_customer',
            ]);

            if ($response->failed()) {
                throw PaymentGatewayException::gatewayError('Stripe', $response->json('error.message', 'Refund failed'));
            }

            $responseData = $response->json();

            return [
                'success' => true,
                'refund_id' => $responseData['id'],
                'status' => $responseData['status'],
                'amount' => $responseData['amount'] / 100,
                'gateway_response' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('Stripe refund failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw PaymentGatewayException::gatewayError('Stripe', $e->getMessage());
        }
    }

    public function getTransactionDetails(string $transactionId): array
    {
        return $this->verifyPayment($transactionId);
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];
    }

    public function getSupportedPaymentMethods(): array
    {
        return [PaymentMethod::CARD->value];
    }

    public function getName(): string
    {
        return 'stripe';
    }

    private function mapStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'requires_payment_method', 'requires_confirmation' => 'pending',
            'succeeded' => 'completed',
            'canceled' => 'cancelled',
            'requires_action' => 'pending',
            default => 'failed'
        };
    }
}

// app/Payment/Gateways/Paystack/PaystackGateway.php
namespace App\Payment\Gateways\Paystack;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Enums\PaymentMethod;
use App\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackGateway implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('payment.gateways.paystack.secret_key');
    }

    public function initializePayment(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/transaction/initialize', [
                'amount' => $data['amount'] * 100, // Convert to kobo for NGN
                'currency' => $data['currency'],
                'email' => $data['customer']['email'],
                'reference' => $data['reference'],
                'callback_url' => $data['callback_url'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($response->failed() || !$response->json('status')) {
                throw PaymentGatewayException::gatewayError('Paystack', $response->json('message', 'Unknown error'));
            }

            $responseData = $response->json('data');

            return [
                'success' => true,
                'reference' => $responseData['reference'],
                'authorization_url' => $responseData['authorization_url'],
                'access_code' => $responseData['access_code'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => 'pending',
                'gateway_response' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('Paystack payment initialization failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw PaymentGatewayException::gatewayError('Paystack', $e->getMessage());
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/transaction/verify/' . $reference);

            if ($response->failed()) {
                throw PaymentGatewayException::gatewayError('Paystack', 'Payment verification failed');
            }

            $responseData = $response->json();

            if (!$responseData['status']) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => $responseData['message']
                ];
            }

            $data = $responseData['data'];

            return [
                'success' => $data['status'] === 'success',
                'status' => $this->mapStatus($data['status']),
                'reference' => $data['reference'],
                'transaction_id' => $data['id'],
                'amount' => $data['amount'] / 100,
                'currency' => $data['currency'],
                'gateway_response' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Paystack payment verification failed', [
                'error' => $e->getMessage(),
                'reference' => $reference
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    public function processRefund(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/refund', [
                'transaction' => $data['transaction_id'],
                'amount' => isset($data['amount']) ? $data['amount'] * 100 : null,
                'currency' => $data['currency'] ?? 'NGN',
                'customer_note' => $data['reason'] ?? 'Refund requested',
                'merchant_note' => $data['merchant_note'] ?? 'Refund processed',
            ]);

            if ($response->failed() || !$response->json('status')) {
                throw PaymentGatewayException::gatewayError('Paystack', $response->json('message', 'Refund failed'));
            }

            $responseData = $response->json('data');

            return [
                'success' => true,
                'refund_id' => $responseData['id'],
                'status' => $responseData['status'],
                'amount' => $responseData['amount'] / 100,
                'gateway_response' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('Paystack refund failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw PaymentGatewayException::gatewayError('Paystack', $e->getMessage());
        }
    }

    public function getTransactionDetails(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/transaction/' . $transactionId);

            if ($response->failed() || !$response->json('status')) {
                throw PaymentGatewayException::gatewayError('Paystack', 'Transaction not found');
            }

            return $response->json('data');

        } catch (\Exception $e) {
            Log::error('Paystack transaction details failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);

            throw PaymentGatewayException::gatewayError('Paystack', $e->getMessage());
        }
    }

    public function getSupportedCurrencies(): array
    {
        return ['NGN', 'USD', 'GHS', 'ZAR', 'KES'];
    }

    public function getSupportedPaymentMethods(): array
    {
        return [
            PaymentMethod::CARD->value,
            PaymentMethod::BANK_TRANSFER->value,
            PaymentMethod::USSD->value,
            PaymentMethod::QR_CODE->value,
            PaymentMethod::MOBILE_MONEY->value
        ];
    }

    public function getName(): string
    {
        return 'paystack';
    }

    private function mapStatus(string $paystackStatus): string
    {
        return match ($paystackStatus) {
            'success' => 'completed',
            'failed' => 'failed',
            'abandoned' => 'cancelled',
            default => 'pending'
        };
    }
}

// app/Payment/Gateways/Flutterwave/FlutterwaveGateway.php
namespace App\Payment\Gateways\Flutterwave;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Enums\PaymentMethod;
use App\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveGateway implements PaymentGatewayInterface {
    private string $secretKey;
    private string $baseUrl = 'https://api.flutterwave.com/v3';

    public function __construct()
    {
        $this->secretKey = config('payment.gateways.flutterwave.secret_key');
    }

    public function initializePayment(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/payments', [
                'tx_ref' => $data['reference'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'redirect_url' => $data['redirect_url'] ?? config('app.url') . '/payment/callback',
                'payment_options' => 'card,banktransfer,ussd',
                'customer' => [
                    'email' => $data['customer']['email'],
                    'name' => $data['customer']['name'] ?? '',
                    'phonenumber' => $data['customer']['phone'] ?? '',
                ],
                'customizations' => [
                    'title' => $data['title'] ?? config('app.name') . ' Payment',
                    'description' => $data['description'] ?? 'Payment for services',
                ],
                'meta' => $data['metadata'] ?? [],
            ]);

            if ($response->failed() || $response->json('status') !== 'success') {
                throw PaymentGatewayException::gatewayError('Flutterwave', $response->json('message', 'Unknown error'));
            }

            $responseData = $response->json('data');

            return [
                'success' => true,
                'reference' => $data['reference'],
                'authorization_url' => $responseData['link'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'status' => 'pending',
                'gateway_response' => $responseData,
            ];

        } catch (\Exception $e) {
            Log::error('Flutterwave payment initialization failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw PaymentGatewayException::gatewayError('Flutterwave', $e->getMessage());
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/transactions/verify_by_reference', [
                'tx_ref' => $reference
            ]);

            if ($response->failed()) {
                throw PaymentGatewayException::gatewayError('Flutterwave', 'Payment verification failed');
            }

            $responseData = $response->json();

            if ($responseData['status'] !== 'success') {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => $responseData['message']
                ];
            }

            $data = $responseData['data'];

            return [
                'success' => $data['status'] === 'successful',
                'status' => $this->mapStatus($data['status']),
                'reference' => $data['tx_ref'],
                'transaction_id' => $data['id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'gateway_response' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Flutterwave payment verification failed', [
                'error' => $e->getMessage(),
                'reference' => $reference
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    public function processRefund(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/transactions/' . $data['transaction_id'] . '/refund', [
                'amount' => $data['amount'] ?? null,
                'comments' => $data['reason'] ?? 'Refund requested',
            ]);

            if ($response->failed() || $response->json('status') !== 'success') {
                throw PaymentGatewayException::gatewayError('Flutterwave', $response->json('message', 'Refund failed'));
            }

            $responseData = $response->json('data');

            return [
                'success' => true,
                'refund_id' => $responseData['id'],
                'status' => $responseData['status'],
                'amount' => $responseData['amount'],
                'gateway_response' => $responseData,
            ];
        }catch(\Throwable $th){

        }
    }

}
