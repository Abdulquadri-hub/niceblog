# Multi-Tenant E-commerce Database Schema

## Landlord Database (Central Management)

### 1. Tenants Table
CREATE TABLE tenants (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    domain VARCHAR(255) UNIQUE NULL,
    subdomain VARCHAR(100) UNIQUE NULL,
    database_name VARCHAR(64) NOT NULL,
    status ENUM('pending', 'creating_database', 'running_migrations', 'seeding_data', 'creating_owner', 'active', 'suspended', 'failed') DEFAULT 'pending',
    setup_step VARCHAR(100) NULL,
    setup_error TEXT NULL,
    setup_completed_at TIMESTAMP NULL,
    trial_ends_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 2. Subscription Plans Table

CREATE TABLE subscription_plans (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    billing_cycle ENUM('monthly', 'yearly') NOT NULL,
    features JSON NOT NULL, -- {products_limit: 100, storage_gb: 5, custom_domain: true}
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 3. Tenant Subscriptions Table

CREATE TABLE tenant_subscriptions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active', 'canceled', 'expired', 'suspended') DEFAULT 'active',
    current_period_start TIMESTAMP NOT NULL,
    current_period_end TIMESTAMP NOT NULL,
    canceled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
);


### 4. Platform Transactions Table

CREATE TABLE transactions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    type ENUM('subscription', 'commission', 'payout') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    gateway VARCHAR(50), -- stripe, paypal, etc
    gateway_transaction_id VARCHAR(255),
    metadata JSON,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);


---

## Tenant Database (Individual Shop)

### 1. Users Table (Shop Customers & Staff)

CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    is_owner BOOLEAN DEFAULT FALSE,
    is_staff BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 2. User Addresses Table

CREATE TABLE user_addresses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('billing', 'shipping', 'both') DEFAULT 'both',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company VARCHAR(100) NULL,
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country CHAR(2) NOT NULL,
    phone VARCHAR(20) NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


### 3. Categories Table

CREATE TABLE categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    image_url VARCHAR(500) NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
);


### 4. Products Table

CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    short_description VARCHAR(500) NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    compare_price DECIMAL(10,2) NULL, -- original price for discounts
    cost_price DECIMAL(10,2) NULL, -- for profit calculation
    weight DECIMAL(8,2) NULL,
    dimensions JSON NULL, -- {length: 10, width: 5, height: 3}
    type ENUM('simple', 'variable', 'digital') DEFAULT 'simple',
    status ENUM('draft', 'active', 'inactive', 'archived') DEFAULT 'draft',
    visibility ENUM('public', 'private', 'password') DEFAULT 'public',
    featured BOOLEAN DEFAULT FALSE,
    track_inventory BOOLEAN DEFAULT TRUE,
    manage_stock BOOLEAN DEFAULT TRUE,
    stock_quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    backorders ENUM('no', 'notify', 'yes') DEFAULT 'no',
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);


### 5. Product Categories Table

CREATE TABLE product_categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_category (product_id, category_id)
);


### 6. Product Images Table

CREATE TABLE product_images (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(500) NOT NULL,
    alt_text VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);


### 7. Product Variants Table

CREATE TABLE product_variants (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    compare_price DECIMAL(10,2) NULL,
    cost_price DECIMAL(10,2) NULL,
    weight DECIMAL(8,2) NULL,
    stock_quantity INT DEFAULT 0,
    image_url VARCHAR(500) NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);


### 8. Attributes Table

CREATE TABLE attributes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    type ENUM('text', 'number', 'select', 'multiselect', 'boolean', 'date') NOT NULL,
    is_required BOOLEAN DEFAULT FALSE,
    is_filterable BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 9. Attribute Values Table

CREATE TABLE attribute_values (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    attribute_id BIGINT UNSIGNED NOT NULL,
    value VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE
);


### 10. Product Variant Attributes Table

CREATE TABLE product_variant_attributes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    variant_id BIGINT UNSIGNED NOT NULL,
    attribute_id BIGINT UNSIGNED NOT NULL,
    attribute_value_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_id) REFERENCES attributes(id),
    FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id),
    UNIQUE KEY unique_variant_attribute (variant_id, attribute_id)
);


### 11. Shopping Carts Table

CREATE TABLE shopping_carts (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL, -- NULL for guest carts
    session_id VARCHAR(255) NULL, -- for guest users
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


### 12. Cart Items Table

CREATE TABLE cart_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    cart_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL, -- price at time of adding to cart
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES shopping_carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
);


### 13. Orders Table

CREATE TABLE orders (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id BIGINT UNSIGNED NULL, -- NULL for guest orders
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    currency CHAR(3) DEFAULT 'USD',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    shipping_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded', 'partially_refunded') DEFAULT 'pending',
    payment_method VARCHAR(50) NULL,
    payment_gateway VARCHAR(50) NULL,
    gateway_transaction_id VARCHAR(255) NULL,
    notes TEXT NULL,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);


### 14. Order Items Table

CREATE TABLE order_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(255) NOT NULL, -- snapshot at order time
    product_sku VARCHAR(100) NOT NULL,
    variant_info JSON NULL, -- snapshot of variant attributes
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL, -- price at order time
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);


### 15. Order Addresses Table

CREATE TABLE order_addresses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    type ENUM('billing', 'shipping') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company VARCHAR(100) NULL,
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country CHAR(2) NOT NULL,
    phone VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);


### 16. Coupons Table

CREATE TABLE coupons (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('fixed', 'percentage') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) NULL,
    usage_limit INT NULL,
    usage_limit_per_customer INT NULL,
    used_count INT DEFAULT 0,
    starts_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 17. Coupon Usage Table

CREATE TABLE coupon_usage (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    coupon_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);


### 18. Reviews Table

CREATE TABLE reviews (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL, -- to verify purchase
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255) NULL,
    comment TEXT NULL,
    is_verified BOOLEAN DEFAULT FALSE, -- verified purchase
    is_approved BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);


### 19. Wishlists Table

CREATE TABLE wishlists (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product_variant (user_id, product_id, variant_id)
);


### 20. Shop Settings Table

CREATE TABLE shop_settings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key VARCHAR(100) UNIQUE NOT NULL,
    value TEXT NULL,
    type ENUM('string', 'number', 'boolean', 'json', 'text') DEFAULT 'string',
    is_public BOOLEAN DEFAULT FALSE, -- can be accessed by customers
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 21. Notifications Table

CREATE TABLE notifications (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL, -- order_status, low_stock, etc.
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


### 22. Inventory Movements Table

CREATE TABLE inventory_movements (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    type ENUM('sale', 'return', 'adjustment', 'restock') NOT NULL,
    quantity_change INT NOT NULL, -- negative for outbound, positive for inbound
    quantity_after INT NOT NULL,
    reference_type VARCHAR(50) NULL, -- order, adjustment, etc.
    reference_id BIGINT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);


### 23. Shipping Zones Table

CREATE TABLE shipping_zones (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    countries JSON NOT NULL, -- array of country codes
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


### 24. Shipping Methods Table

CREATE TABLE shipping_methods (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    zone_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    calculation_type ENUM('flat_rate', 'weight_based', 'free') NOT NULL,
    cost DECIMAL(10,2) DEFAULT 0,
    free_shipping_threshold DECIMAL(10,2) NULL,
    weight_rates JSON NULL, -- for weight-based calculations
    estimated_days_min INT NULL,
    estimated_days_max INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
);


### 25. Tax Rates Table

CREATE TABLE tax_rates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    rate DECIMAL(5,4) NOT NULL, -- 0.0825 for 8.25%
    type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    country CHAR(2) NULL,
    state VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    is_compound BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


## Key Indexes for Performance


-- Products
CREATE INDEX idx_products_status_visibility ON products(status, visibility);
CREATE INDEX idx_products_featured ON products(featured);
CREATE INDEX idx_products_created_at ON products(created_at);
CREATE FULLTEXT INDEX idx_products_search ON products(name, description, sku);

-- Orders
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_orders_order_number ON orders(order_number);

-- Categories
CREATE INDEX idx_categories_parent_slug ON categories(parent_id, slug);
CREATE INDEX idx_categories_active ON categories(is_active);

-- Cart Items
CREATE INDEX idx_cart_items_cart_product ON cart_items(cart_id, product_id);

-- Reviews
CREATE INDEX idx_reviews_product_approved ON reviews(product_id, is_approved);

-- Inventory
CREATE INDEX idx_inventory_product_created ON inventory_movements(product_id, created_at);


## Foreign Key Relationships Summary

1. **Landlord → Tenant**: One-to-many (tenants, subscriptions, transactions)
2. **Tenant → Users**: One-to-many (shop staff and customers)
3. **Products → Categories**: Many-to-many
4. **Products → Images**: One-to-many
5. **Products → Variants**: One-to-many
6. **Variants → Attributes**: Many-to-many
7. **Users → Carts → Items**: One-to-many-to-many
8. **Orders → Items**: One-to-many
9. **Orders → Addresses**: One-to-many
10. **Products → Reviews**: One-to-many
11. **Users → Wishlists**: One-to-many

This schema supports:
- ✅ Multi-tenant architecture
- ✅ Complex product variations
- ✅ Full e-commerce functionality
- ✅ Inventory management
- ✅ Order processing
- ✅ Payment tracking
- ✅ Customer management
- ✅ Reviews and ratings
- ✅ Shipping and tax calculations
- ✅ Discount systems
- ✅ Admin analytics
