# ShopConnect Sequential Development Roadmap

## Phase 1: Core Foundation (Landlord System)

### 1. Tenant Registration & CAC Verification System
**What you're building:** Complete business registration with government verification

**Implementation Order:**
1. **CACVerificationInterface & Repository**
   ```php
   interface CACVerificationInterface {
       public function initiate(array $data): CACVerification;
       public function submitDocuments(int $verificationId, array $documents): bool;
       public function verifyCACNumber(string $cacNumber): array;
       public function approveVerification(int $verificationId, int $adminId): bool;
       public function rejectVerification(int $verificationId, string $reason): bool;
   }
   ```

2. **CACVerificationController**
   - POST `/api/cac/verify` - Initiate verification
   - POST `/api/cac/{id}/documents` - Upload documents
   - GET `/api/cac/{id}/status` - Check status
   - PUT `/api/cac/{id}/approve` - Admin approval

3. **Document Upload Service**
   ```php
   interface DocumentUploadInterface {
       public function upload(UploadedFile $file, string $type): string;
       public function validateDocument(UploadedFile $file, string $type): bool;
   }
   ```

4. **CACVerification Model & Migration**
   - Use your existing `cac_verifications` and `cac_documents` tables
   - Status tracking: pending → documents_submitted → under_review → verified

### 2. Tenant Management System  
**What you're building:** Multi-tenant database creation and lifecycle

**Implementation Order:**
1. **Enhanced TenantInterface (extends your existing)**
   ```php
   interface TenantInterface extends TenantRepositoryInterface {
       public function createWithCAC(array $tenantData, CACVerification $cac): Tenant;
       public function updateSetupStep(int $tenantId, string $step): bool;
       public function markSetupComplete(int $tenantId): bool;
   }
   ```

2. **SetupTenantJob Enhancement**
   - Add CAC verification check before tenant creation
   - Enhanced error handling and rollback
   - Setup step tracking

3. **TenantController**
   - POST `/api/tenants/register` - Register with CAC
   - GET `/api/tenants/{id}/setup-status` - Track progress
   - PUT `/api/tenants/{id}/activate` - Final activation

### 3. Subscription Management
**What you're building:** Plan management and billing system

**Implementation Order:**
1. **SubscriptionPlanInterface**
   ```php
   interface SubscriptionPlanInterface {
       public function create(array $data): SubscriptionPlan;
       public function getActivePlans(): Collection;
       public function checkFeatureAccess(int $planId, string $feature): bool;
   }
   ```

2. **TenantSubscriptionInterface**
   ```php
   interface TenantSubscriptionInterface {
       public function subscribe(int $tenantId, int $planId): TenantSubscription;
       public function upgrade(int $tenantId, int $newPlanId): bool;
       public function cancel(int $tenantId): bool;
       public function getCurrentSubscription(int $tenantId): ?TenantSubscription;
   }
   ```

3. **SubscriptionController**
   - GET `/api/plans` - List available plans
   - POST `/api/tenants/{id}/subscribe` - Subscribe to plan
   - PUT `/api/tenants/{id}/subscription/upgrade` - Change plan

4. **Usage Limits Service**
   ```php
   interface UsageLimitInterface {
       public function checkLimit(int $tenantId, string $resource): bool;
       public function incrementUsage(int $tenantId, string $resource): void;
       public function resetMonthlyUsage(int $tenantId): void;
   }
   ```

---

## Phase 2: Tenant Core Features

### 4. User Management (Per Tenant)
**What you're building:** Customer and staff management within each store

**Implementation Order:**
1. **TenantUserInterface**
   ```php
   interface TenantUserInterface {
       public function createOwner(array $data): User;
       public function createCustomer(array $data): User;
       public function createStaff(array $data, array $permissions): User;
       public function updateProfile(int $userId, array $data): User;
   }
   ```

2. **AuthenticationController (per tenant)**
   - POST `/auth/register` - Customer registration
   - POST `/auth/login` - Login
   - POST `/auth/staff/create` - Staff creation (owner only)

3. **UserAddressInterface**
   ```php
   interface UserAddressInterface {
       public function create(int $userId, array $data): UserAddress;
       public function setDefault(int $userId, int $addressId): bool;
       public function getUserAddresses(int $userId): Collection;
   }
   ```

### 5. Product Catalog System
**What you're building:** Complete product management

**Implementation Order:**
1. **CategoryInterface**
   ```php
   interface CategoryInterface {
       public function create(array $data): Category;
       public function createHierarchy(array $categories): Collection;
       public function getTree(): Collection;
       public function moveCategory(int $categoryId, ?int $newParentId): bool;
   }
   ```

2. **ProductInterface**
   ```php
   interface ProductInterface {
       public function create(array $data): Product;
       public function addImages(int $productId, array $images): Collection;
       public function createVariants(int $productId, array $variants): Collection;
       public function updateInventory(int $productId, int $quantity, string $type): bool;
       public function getByCategory(int $categoryId): Collection;
       public function search(string $query, array $filters = []): Collection;
   }
   ```

3. **ProductController**
   - GET `/api/products` - List products (with filters)
   - POST `/api/products` - Create product
   - PUT `/api/products/{id}` - Update product
   - POST `/api/products/{id}/images` - Upload images
   - POST `/api/products/{id}/variants` - Create variants

4. **ProductVariantInterface**
   ```php
   interface ProductVariantInterface {
       public function create(int $productId, array $data): ProductVariant;
       public function updateStock(int $variantId, int $quantity): bool;
       public function getVariantsByAttributes(int $productId, array $attributes): Collection;
   }
   ```

5. **InventoryInterface**
   ```php
   interface InventoryInterface {
       public function adjustStock(int $productId, int $quantity, string $type, ?string $reference): bool;
       public function checkStock(int $productId, ?int $variantId = null): int;
       public function getLowStockProducts(): Collection;
       public function getMovementHistory(int $productId): Collection;
   }
   ```

### 6. Shopping Cart System
**What you're building:** Cart management for customers

**Implementation Order:**
1. **ShoppingCartInterface**
   ```php
   interface ShoppingCartInterface {
       public function create(?int $userId = null, ?string $sessionId = null): ShoppingCart;
       public function addItem(int $cartId, int $productId, int $quantity, ?int $variantId = null): CartItem;
       public function updateQuantity(int $cartId, int $productId, int $quantity): bool;
       public function removeItem(int $cartId, int $productId): bool;
       public function getCart(int $cartId): ShoppingCart;
       public function clearCart(int $cartId): bool;
   }
   ```

2. **CartController**
   - POST `/api/cart/add` - Add item to cart
   - PUT `/api/cart/update` - Update quantities
   - DELETE `/api/cart/remove/{productId}` - Remove item
   - GET `/api/cart` - Get current cart

---

## Phase 3: Order Management & Payments

### 7. Order Processing System
**What you're building:** Complete order lifecycle management

**Implementation Order:**
1. **OrderInterface**
   ```php
   interface OrderInterface {
       public function createFromCart(int $cartId, array $shippingData, array $billingData): Order;
       public function updateStatus(int $orderId, string $status): bool;
       public function addOrderItems(int $orderId, array $items): Collection;
       public function calculateTotals(Order $order): Order;
       public function getOrdersByUser(int $userId): Collection;
   }
   ```

2. **OrderController**
   - POST `/api/orders/create` - Create order from cart
   - GET `/api/orders` - List user orders
   - GET `/api/orders/{id}` - Get order details
   - PUT `/api/orders/{id}/status` - Update status (admin)

3. **CouponInterface**
   ```php
   interface CouponInterface {
       public function validate(string $code, Order $order): bool;
       public function apply(string $code, Order $order): float;
       public function trackUsage(int $couponId, int $orderId, ?int $userId): void;
   }
   ```

### 8. Payment Integration
**What you're building:** Multi-gateway payment processing

**Implementation Order:**
1. **Enhanced PaymentGatewayInterface (build on your existing)**
   ```php
   interface PaymentGatewayInterface extends TransactionsInterface {
       public function processOrderPayment(Order $order, array $paymentData): PaymentResult;
       public function handleWebhook(string $gateway, array $payload): bool;
       public function refundPayment(string $transactionId, float $amount): bool;
   }
   ```

2. **PaymentController**
   - POST `/api/payments/process` - Process payment
   - POST `/api/webhooks/{gateway}` - Handle webhooks
   - POST `/api/payments/{id}/refund` - Process refunds

3. **Commission Tracking (extends your Transaction model)**
   ```php
   interface CommissionInterface {
       public function calculateCommission(Order $order): float;
       public function recordCommission(Order $order, float $amount): bool;
       public function getCommissionReport(int $tenantId, string $period): array;
   }
   ```

---

## Phase 4: Social Commerce Integration

### 9. Social Media Account Management
**What you're building:** Connect and manage social media accounts

**Implementation Order:**
1. **SocialAccountInterface**
   ```php
   interface SocialAccountInterface {
       public function connect(string $platform, array $credentials): SocialAccount;
       public function disconnect(int $accountId): bool;
       public function refreshToken(int $accountId): bool;
       public function validateConnection(int $accountId): bool;
       public function getConnectedAccounts(): Collection;
   }
   ```

2. **SocialAccountController**
   - POST `/api/social/connect/{platform}` - Connect account
   - DELETE `/api/social/{id}/disconnect` - Disconnect
   - GET `/api/social/accounts` - List connected accounts
   - POST `/api/social/{id}/refresh` - Refresh tokens

### 10. Product Sharing System
**What you're building:** Share products across social platforms

**Implementation Order:**
1. **SocialSharingInterface**
   ```php
   interface SocialSharingInterface {
       public function shareProduct(int $productId, int $socialAccountId, ?string $customMessage = null): SocialPost;
       public function schedulePost(int $productId, int $socialAccountId, DateTime $scheduledAt): SocialPost;
       public function generateShareUrl(int $productId, string $platform): string;
       public function trackClick(string $shareId): void;
   }
   ```

2. **SocialSharingController**
   - POST `/api/social/share/{productId}` - Share product
   - POST `/api/social/schedule` - Schedule post
   - GET `/api/social/posts` - List posts
   - GET `/api/social/analytics` - Sharing analytics

3. **SocialPostInterface**
   ```php
   interface SocialPostInterface {
       public function create(array $data): SocialPost;
       public function updateStatus(int $postId, string $status): bool;
       public function recordEngagement(int $postId, array $metrics): bool;
       public function getScheduledPosts(): Collection;
   }
   ```

### 11. WhatsApp Business Integration
**What you're building:** WhatsApp customer communication

**Implementation Order:**
1. **WhatsAppInterface**
   ```php
   interface WhatsAppInterface {
       public function sendProductMessage(string $phone, Product $product): bool;
       public function sendOrderUpdate(string $phone, Order $order): bool;
       public function createConversation(string $phone, ?string $customerName = null): WhatsAppConversation;
       public function recordMessage(int $conversationId, array $messageData): WhatsAppMessage;
   }
   ```

2. **WhatsAppController**
   - POST `/api/whatsapp/send-product` - Send product message
   - POST `/api/whatsapp/webhook` - Handle incoming messages
   - GET `/api/whatsapp/conversations` - List conversations

---

## Phase 5: Analytics & Growth Features

### 12. Traffic Source Tracking
**What you're building:** Track where customers come from

**Implementation Order:**
1. **TrafficSourceInterface**
   ```php
   interface TrafficSourceInterface {
       public function recordVisit(string $sessionId, array $sourceData): TrafficSource;
       public function trackConversion(string $sessionId, Order $order): bool;
       public function getSourceAnalytics(string $period): array;
       public function getTopPerformingSources(): Collection;
   }
   ```

2. **AnalyticsMiddleware**
   - Track all incoming requests with UTM parameters
   - Record social media referrals
   - Track conversion paths

### 13. Store Customization
**What you're building:** Theme and branding customization

**Implementation Order:**
1. **StoreThemeInterface**
   ```php
   interface StoreThemeInterface {
       public function updateTheme(array $themeData): StoreTheme;
       public function uploadLogo(UploadedFile $logo): string;
       public function updateColors(array $colors): bool;
       public function setCustomCSS(string $css): bool;
   }
   ```

2. **StoreCustomizationController**
   - PUT `/api/store/theme` - Update theme
   - POST `/api/store/logo` - Upload logo
   - PUT `/api/store/colors` - Update colors

---

## Phase 6: Advanced Features

### 14. Reviews & Ratings
**What you're building:** Customer review system

**Implementation Order:**
1. **ReviewInterface**
   ```php
   interface ReviewInterface {
       public function create(int $productId, int $userId, array $data): Review;
       public function moderate(int $reviewId, bool $approved): bool;
       public function getProductReviews(int $productId): Collection;
       public function getAverageRating(int $productId): float;
   }
   ```

### 15. Advanced Analytics Dashboard
**What you're building:** Comprehensive business analytics

**Implementation Order:**
1. **AnalyticsInterface**
   ```php
   interface AnalyticsInterface {
       public function getSalesMetrics(string $period): array;
       public function getProductPerformance(): array;
       public function getSocialCommerceMetrics(): array;
       public function getCustomerMetrics(): array;
   }
   ```

---

## Development Pattern for Each Feature

### For every feature, follow this pattern:

1. **Create Interface** - Define contracts first
2. **Create Repository** - Implement data access layer
3. **Create Service** - Business logic layer
4. **Create Controller** - HTTP layer
5. **Write Tests** - Unit and integration tests
6. **Create Migration** - Database changes
7. **Update Models** - Eloquent models with relationships

### Example Implementation Structure:
```
app/
├── Contracts/
│   ├── CACVerificationInterface.php
│   └── SocialSharingInterface.php
├── Repositories/
│   ├── CACVerificationRepository.php
│   └── SocialSharingRepository.php
├── Services/
│   ├── CACVerificationService.php
│   └── SocialSharingService.php
├── Http/Controllers/
│   ├── CACVerificationController.php
│   └── SocialSharingController.php
└── Models/
    ├── CACVerification.php
    └── SocialPost.php
```

## Key Dependencies Between Features

- **CAC Verification** must complete before **Tenant Creation**
- **User Management** required for **Product Management**
- **Product Catalog** required for **Shopping Cart**
- **Shopping Cart** required for **Order Processing**
- **Order Processing** required for **Payment Integration**
- **Product Catalog** + **Social Accounts** required for **Social Sharing**
- **Orders** required for **Analytics**

This roadmap ensures each feature builds logically on previous ones while maintaining your repository pattern and SOLID principles throughout.
