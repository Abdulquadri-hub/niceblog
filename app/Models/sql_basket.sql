
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) UNIQUE NOT NULL,
    domain VARCHAR(255) UNIQUE NULL,
    database_name VARCHAR(100) UNIQUE NOT NULL,
    database_host VARCHAR(255) DEFAULT 'localhost',

    -- Subscription & Billing
    plan_type ENUM('free', 'starter', 'professional', 'enterprise') DEFAULT 'free',
    subscription_status ENUM('active', 'suspended', 'cancelled', 'trial') DEFAULT 'trial',
    trial_ends_at TIMESTAMP NULL,
    billing_email VARCHAR(255),

    -- Settings
    status ENUM('active', 'suspended', 'pending') DEFAULT 'pending',
    max_users INT DEFAULT 5,
    max_posts INT DEFAULT 100,
    max_storage_mb INT DEFAULT 1000,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_subdomain (subdomain),
    INDEX idx_custom_domain (custom_domain),
    INDEX idx_status (status)
);


### `system_plans` table
```sql
CREATE TABLE system_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    price_monthly DECIMAL(10,2) NOT NULL,
    price_yearly DECIMAL(10,2) NOT NULL,
    max_users INT NOT NULL,
    max_posts INT NOT NULL,
    max_storage_mb INT NOT NULL,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 2. TENANT DATABASE SCHEMA (Per-tenant database)

### `users` table
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,

    -- Profile
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    avatar_url VARCHAR(500) NULL,
    bio TEXT NULL,

    -- Status & Permissions
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    is_owner BOOLEAN DEFAULT FALSE,

    -- Metadata
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_email (email),
    INDEX idx_status (status)
);
```

### `roles` table
```sql
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    permissions JSON, -- Store permissions as JSON array
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default roles insert
INSERT INTO roles (name, slug, description, permissions, is_default) VALUES
('Owner', 'owner', 'Full access to everything', '["*"]', FALSE),
('Admin', 'admin', 'Manage users, posts, and settings', '["users.*", "posts.*", "categories.*", "settings.read", "settings.write"]', FALSE),
('Editor', 'editor', 'Create and edit all posts', '["posts.*", "categories.read", "media.*"]', FALSE),
('Author', 'author', 'Create and edit own posts', '["posts.create", "posts.edit_own", "posts.delete_own", "media.upload"]', TRUE),
('Contributor', 'contributor', 'Submit posts for review', '["posts.create_draft", "media.upload"]', FALSE);
```

### `user_roles` table
```sql
CREATE TABLE user_roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    role_id INT NOT NULL,
    assigned_by BIGINT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role (user_id, role_id)
);
```

### `categories` table
```sql
CREATE TABLE categories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    parent_id BIGINT NULL,

    -- SEO
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,

    -- Display
    color VARCHAR(7) DEFAULT '#6B7280',
    icon VARCHAR(100) NULL,
    is_featured BOOLEAN DEFAULT FALSE,

    -- Status
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,

    -- Metadata
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_slug (slug),
    INDEX idx_parent (parent_id),
    INDEX idx_status (status)
);
```

### `posts` table
```sql
CREATE TABLE posts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,

    -- Content
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) UNIQUE NOT NULL,
    excerpt TEXT NULL,
    content LONGTEXT NOT NULL,
    content_raw LONGTEXT NULL, -- For markdown storage

    -- SEO
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    canonical_url VARCHAR(500) NULL,

    -- Media
    featured_image_url VARCHAR(500) NULL,
    featured_image_alt VARCHAR(255) NULL,

    -- Publishing
    status ENUM('draft', 'pending', 'published', 'scheduled', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    scheduled_at TIMESTAMP NULL,

    -- Engagement
    view_count INT DEFAULT 0,
    like_count INT DEFAULT 0,
    comment_count INT DEFAULT 0,

    -- Settings
    allow_comments BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    is_pinned BOOLEAN DEFAULT FALSE,

    -- Authorship
    author_id BIGINT NOT NULL,
    editor_id BIGINT NULL, -- Last editor

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (author_id) REFERENCES users(id),
    FOREIGN KEY (editor_id) REFERENCES users(id),

    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_author (author_id),
    INDEX idx_published_at (published_at),
    INDEX idx_created_at (created_at),
    FULLTEXT idx_search (title, content)
);
```

### `post_categories` table
```sql
CREATE TABLE post_categories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    post_id BIGINT NOT NULL,
    category_id BIGINT NOT NULL,

    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_post_category (post_id, category_id)
);
```

### `tags` table
```sql
CREATE TABLE tags (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#6B7280',

    -- Stats
    post_count INT DEFAULT 0,

    -- SEO
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_slug (slug),
    INDEX idx_name (name)
);
```

### `post_tags` table
```sql
CREATE TABLE post_tags (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    post_id BIGINT NOT NULL,
    tag_id BIGINT NOT NULL,

    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    UNIQUE KEY unique_post_tag (post_id, tag_id)
);
```

### `comments` table
```sql
CREATE TABLE comments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    post_id BIGINT NOT NULL,
    parent_id BIGINT NULL,

    -- Author (can be user or guest)
    user_id BIGINT NULL,
    author_name VARCHAR(255) NOT NULL,
    author_email VARCHAR(255) NOT NULL,
    author_url VARCHAR(500) NULL,
    author_ip VARCHAR(45) NULL,

    -- Content
    content TEXT NOT NULL,

    -- Status
    status ENUM('pending', 'approved', 'spam', 'trash') DEFAULT 'pending',

    -- Engagement
    like_count INT DEFAULT 0,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_post_id (post_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

### `media` table
```sql
CREATE TABLE media (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,

    -- File info
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_size INT NOT NULL, -- in bytes
    mime_type VARCHAR(100) NOT NULL,

    -- Image specific
    width INT NULL,
    height INT NULL,
    alt_text VARCHAR(255) NULL,

    -- Metadata
    title VARCHAR(255) NULL,
    description TEXT NULL,

    -- Organization
    folder VARCHAR(255) NULL,

    -- Usage
    is_used BOOLEAN DEFAULT FALSE,
    usage_count INT DEFAULT 0,

    -- Uploader
    uploaded_by BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_mime_type (mime_type),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_created_at (created_at)
);
```

### `invitations` table
```sql
CREATE TABLE invitations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    token VARCHAR(100) UNIQUE NOT NULL,

    -- Invitation details
    invited_by BIGINT NOT NULL,
    message TEXT NULL,

    -- Status
    status ENUM('pending', 'accepted', 'expired', 'cancelled') DEFAULT 'pending',
    expires_at TIMESTAMP NOT NULL,
    accepted_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (invited_by) REFERENCES users(id),
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_status (status)
);
```

### `settings` table
```sql
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(255) UNIQUE NOT NULL,
    value LONGTEXT,
    type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT NULL,
    is_public BOOLEAN DEFAULT FALSE, -- Can be accessed via API
    group_name VARCHAR(100) DEFAULT 'general',

    updated_by BIGINT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_group (group_name),
    INDEX idx_key (key_name)
);

-- Default settings
INSERT INTO settings (key_name, value, type, description, is_public, group_name) VALUES
('site_title', 'My Blog', 'string', 'Website title', TRUE, 'general'),
('site_description', 'A great blog powered by our platform', 'string', 'Website description', TRUE, 'general'),
('posts_per_page', '10', 'number', 'Number of posts per page', TRUE, 'content'),
('allow_comments', 'true', 'boolean', 'Allow comments on posts', TRUE, 'content'),
('comment_moderation', 'true', 'boolean', 'Require comment approval', FALSE, 'content'),
('theme', 'default', 'string', 'Active theme', TRUE, 'appearance'),
('timezone', 'UTC', 'string', 'Site timezone', FALSE, 'general');
```

### `activity_logs` table
```sql
CREATE TABLE activity_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NULL,

    -- Activity details
    action VARCHAR(100) NOT NULL, -- 'created', 'updated', 'deleted', 'login', etc.
    entity_type VARCHAR(100) NULL, -- 'post', 'user', 'comment', etc.
    entity_id BIGINT NULL,

    -- Context
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    -- Metadata
    properties JSON NULL, -- Store additional data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
);
```

---

## 3. Key Features & Concepts

### Multitenancy Benefits
- **Complete isolation**: Each tenant has their own database
- **Customization**: Tenants can have different schemas/features
- **Scalability**: Easy to scale individual tenants
- **Security**: No data bleeding between tenants

### Role-Based Access Control (RBAC)
- Flexible permission system using JSON
- Hierarchical roles (Owner > Admin > Editor > Author > Contributor)
- Easy to extend permissions

### Content Management
- Full blog functionality with categories, tags, comments
- Media management with file organization
- SEO optimization built-in
- Content scheduling and workflows

### Team Collaboration
- User invitations with role assignment
- Activity logging for audit trails
- Comment system for team feedback

### SaaS Features
- Subscription management
- Usage tracking and limits
- Tenant lifecycle management

---

## 4. Laravel Implementation Notes

### Models & Relationships
```php
// Tenant model (System DB)
class Tenant extends Model {
    protected $connection = 'system';

    public function owner() {
        return $this->hasOne(TenantOwner::class);
    }

    public function getDatabaseConnection() {
        return "tenant_{$this->database_name}";
    }
}

// Base Tenant Model (Tenant DB)
abstract class BaseTenantModel extends Model {
    protected $connection = 'tenant';

    protected static function booted() {
        // Automatically set tenant connection
        static::setConnection(app('tenant')->getDatabaseConnection());
    }
}
```

### Key Patterns
1. **Tenant Resolution**: Middleware to identify tenant by subdomain/domain
2. **Database Switching**: Dynamic connection switching per request
3. **Seeding**: Automated tenant database setup with default data
4. **Migrations**: Tenant-specific migration management

This schema provides a solid foundation for a professional multitenant blog SaaS. Each component is designed for real-world scalability and maintainability.
