-- ============================================================================
-- NOVURA MARKETPLACE - DEMO SEED DATA
-- Run ONCE on a fresh database, after schema.sql:
--     mysql -u root -p novura < database/seed.sql
--
-- DEMO ACCOUNTS (all use @novura.test addresses)
--   Admin :  admin@novura.test    / Admin@12345
--   Vendor:  vendor1@novura.test  (TechHub Nigeria)  / Novura@123
--            vendor2@novura.test  (StyleHouse)       / Novura@123
--            vendor3@novura.test  (HomeNest)         / Novura@123
--   Buyer :  buyer1@novura.test, buyer2@novura.test  / Novura@123
--
-- !! DELETE these accounts and change the admin password before going live. !!
-- Products have no photos yet; the storefront shows a placeholder until you
-- upload images (vendor dashboard step).
-- ============================================================================

USE `novura`;

-- ----------------------------------------------------------------------------
-- USERS
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `users` (`role`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `email_verified_at`, `status`) VALUES
('admin',  'Novura',  'Admin',   'admin@novura.test',   '08000000000', '$2b$12$WWdErGwlr/HqnD/e6Qfj5OnYiOsdfFm7XHbLjnu0Q1HKfovEGQGPi', NOW(), 'active'),
('vendor', 'Tunde',   'Bello',   'vendor1@novura.test', '08011111111', '$2b$12$7iT6dAIxZ7Ge5Nvkz3eKjOwSEZkDAQRKzHPr31oNPwLAtzUD6sSoq', NOW(), 'active'),
('vendor', 'Amaka',   'Obi',     'vendor2@novura.test', '08022222222', '$2b$12$7iT6dAIxZ7Ge5Nvkz3eKjOwSEZkDAQRKzHPr31oNPwLAtzUD6sSoq', NOW(), 'active'),
('vendor', 'Ibrahim', 'Musa',    'vendor3@novura.test', '08033333333', '$2b$12$7iT6dAIxZ7Ge5Nvkz3eKjOwSEZkDAQRKzHPr31oNPwLAtzUD6sSoq', NOW(), 'active'),
('buyer',  'Chioma',  'Eze',     'buyer1@novura.test',  '08044444444', '$2b$12$7iT6dAIxZ7Ge5Nvkz3eKjOwSEZkDAQRKzHPr31oNPwLAtzUD6sSoq', NOW(), 'active'),
('buyer',  'Segun',   'Adeyemi', 'buyer2@novura.test',  '08055555555', '$2b$12$7iT6dAIxZ7Ge5Nvkz3eKjOwSEZkDAQRKzHPr31oNPwLAtzUD6sSoq', NOW(), 'active');

-- ----------------------------------------------------------------------------
-- VENDORS (approved so their products show on the storefront)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `vendors` (`user_id`, `store_name`, `slug`, `description`, `phone`, `city`, `state`, `commission_rate`, `status`)
SELECT u.id, 'TechHub Nigeria', 'techhub-ng', 'Phones, laptops, TVs and audio from trusted brands, with warranty.', '08011111111', 'Ikeja', 'Lagos', 10.00, 'approved'
  FROM `users` u WHERE u.email = 'vendor1@novura.test';

INSERT IGNORE INTO `vendors` (`user_id`, `store_name`, `slug`, `description`, `phone`, `city`, `state`, `commission_rate`, `status`)
SELECT u.id, 'StyleHouse', 'stylehouse', 'Everyday fashion, footwear, skincare and fragrance.', '08022222222', 'Port Harcourt', 'Rivers', 10.00, 'approved'
  FROM `users` u WHERE u.email = 'vendor2@novura.test';

INSERT IGNORE INTO `vendors` (`user_id`, `store_name`, `slug`, `description`, `phone`, `city`, `state`, `commission_rate`, `status`)
SELECT u.id, 'HomeNest', 'homenest', 'Kitchen, home, groceries, fitness and baby essentials.', '08033333333', 'Abuja', 'FCT', 10.00, 'approved'
  FROM `users` u WHERE u.email = 'vendor3@novura.test';

-- ----------------------------------------------------------------------------
-- CATEGORIES (top level)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`, `is_active`) VALUES
(NULL, 'Phones & Tablets', 'phones-tablets',   1, 1),
(NULL, 'Electronics',      'electronics',      2, 1),
(NULL, 'Computing',        'computing',        3, 1),
(NULL, 'Fashion',          'fashion',          4, 1),
(NULL, 'Home & Kitchen',   'home-kitchen',     5, 1),
(NULL, 'Beauty & Health',  'beauty-health',    6, 1),
(NULL, 'Groceries',        'groceries',        7, 1),
(NULL, 'Sports & Fitness', 'sports-fitness',   8, 1),
(NULL, 'Baby & Kids',      'baby-kids',        9, 1),
(NULL, 'Gaming',           'gaming',          10, 1);

-- ----------------------------------------------------------------------------
-- CATEGORIES (sub-categories)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`, `is_active`)
SELECT id, 'Smartphones', 'smartphones', 1, 1 FROM `categories` WHERE slug = 'phones-tablets'
UNION ALL SELECT id, 'Tablets', 'tablets', 2, 1 FROM `categories` WHERE slug = 'phones-tablets'
UNION ALL SELECT id, 'Phone Accessories', 'phone-accessories', 3, 1 FROM `categories` WHERE slug = 'phones-tablets';

INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`, `is_active`)
SELECT id, 'Televisions', 'televisions', 1, 1 FROM `categories` WHERE slug = 'electronics'
UNION ALL SELECT id, 'Audio', 'audio', 2, 1 FROM `categories` WHERE slug = 'electronics';

INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`, `is_active`)
SELECT id, 'Laptops', 'laptops', 1, 1 FROM `categories` WHERE slug = 'computing'
UNION ALL SELECT id, 'Monitors', 'monitors', 2, 1 FROM `categories` WHERE slug = 'computing';

INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`, `is_active`)
SELECT id, 'Men''s Fashion', 'mens-fashion', 1, 1 FROM `categories` WHERE slug = 'fashion'
UNION ALL SELECT id, 'Women''s Fashion', 'womens-fashion', 2, 1 FROM `categories` WHERE slug = 'fashion'
UNION ALL SELECT id, 'Shoes', 'shoes', 3, 1 FROM `categories` WHERE slug = 'fashion';

INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`, `is_active`)
SELECT id, 'Kitchen Appliances', 'kitchen-appliances', 1, 1 FROM `categories` WHERE slug = 'home-kitchen'
UNION ALL SELECT id, 'Furniture', 'furniture', 2, 1 FROM `categories` WHERE slug = 'home-kitchen';

INSERT IGNORE INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`, `is_active`)
SELECT id, 'Skincare', 'skincare', 1, 1 FROM `categories` WHERE slug = 'beauty-health'
UNION ALL SELECT id, 'Fragrance', 'fragrance', 2, 1 FROM `categories` WHERE slug = 'beauty-health';

-- ----------------------------------------------------------------------------
-- PRODUCTS  (status 'active'; flash sales run for 7 days from the import time)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `products`
 (`vendor_id`, `category_id`, `name`, `slug`, `sku`, `brand`, `short_description`, `description`,
  `price`, `compare_price`, `stock_quantity`, `is_featured`, `is_flash_sale`, `flash_sale_price`, `flash_sale_ends_at`, `status`)
VALUES
-- TechHub Nigeria -----------------------------------------------------------
((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='smartphones'),
 'Aurora X5 Smartphone', 'aurora-x5-smartphone', 'NV-1001', 'Aurora',
 '6.7-inch AMOLED display, 50MP camera and a 5000mAh battery that lasts all day.',
 'The Aurora X5 pairs a bright 6.7-inch AMOLED display with a 50MP main camera, fast charging and a 5000mAh battery. Dual SIM, 8GB RAM and a one-year manufacturer warranty.',
 189000.00, 215000.00, 25, 1, 1, 169000.00, DATE_ADD(NOW(), INTERVAL 7 DAY), 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='smartphones'),
 'Aurora X5 Lite', 'aurora-x5-lite', 'NV-1002', 'Aurora',
 'Smooth 90Hz display and a 13MP camera at an easy price.',
 'A dependable everyday phone with a 6.5-inch 90Hz display, 4GB RAM, dual SIM and a 5000mAh battery. Includes charger and case.',
 112500.00, 125000.00, 40, 1, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='tablets'),
 'Pixelon Tab 10 Tablet', 'pixelon-tab-10', 'NV-1003', 'Pixelon',
 '10.4-inch tablet for streaming, study and video calls.',
 'Pixelon Tab 10 has a 10.4-inch display, quad speakers, 4GB RAM, 64GB storage and a long-lasting 7000mAh battery.',
 98000.00, NULL, 18, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='phone-accessories'),
 'Volt 20000mAh Power Bank', 'volt-power-bank-20000', 'NV-1004', 'Volt',
 'Charge two devices at once with 22.5W fast charging.',
 'A slim 20000mAh power bank with dual USB-A and USB-C output, 22.5W fast charging and a digital battery display.',
 14500.00, 18000.00, 120, 0, 1, 11900.00, DATE_ADD(NOW(), INTERVAL 7 DAY), 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='audio'),
 'Nova Buds Wireless Earbuds', 'nova-buds-earbuds', 'NV-1005', 'Nova',
 'Active noise cancelling, 28-hour battery and a sweat-resistant fit.',
 'True wireless earbuds with active noise cancelling, touch controls, Bluetooth 5.3 and up to 28 hours of playback with the charging case.',
 17900.00, 24000.00, 75, 1, 1, 14900.00, DATE_ADD(NOW(), INTERVAL 7 DAY), 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='audio'),
 'Sonic Bar 2.1 Soundbar', 'sonic-bar-soundbar', 'NV-1006', 'Sonic',
 '2.1 channel soundbar with wireless subwoofer.',
 'Fill the room with 120W of sound. Bluetooth, HDMI ARC and optical input, with a wireless subwoofer for deep bass.',
 64000.00, NULL, 12, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='televisions'),
 'VisionPlus 43-inch Smart TV', 'visionplus-43-smart-tv', 'NV-1007', 'VisionPlus',
 'Full HD smart TV with built-in streaming apps.',
 '43-inch Full HD LED smart TV with built-in streaming apps, Wi-Fi, three HDMI ports and a slim bezel design.',
 245000.00, 290000.00, 9, 1, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='televisions'),
 'Lumen 55-inch 4K Smart TV', 'lumen-55-4k-tv', 'NV-1008', 'Lumen',
 '4K HDR picture with voice-enabled remote.',
 '55-inch 4K HDR smart TV with a voice remote, Dolby audio and a fast processor for smooth streaming and gaming.',
 385000.00, NULL, 5, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='laptops'),
 'ProBook 14 Laptop', 'probook-14-laptop', 'NV-1009', 'ProBook',
 '14-inch laptop with 16GB RAM and a 512GB SSD.',
 'A light business laptop with a 14-inch Full HD screen, 16GB RAM, 512GB SSD, backlit keyboard and up to 10 hours of battery life.',
 520000.00, 565000.00, 7, 1, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='laptops'),
 'SwiftNote 15 Laptop', 'swiftnote-15-laptop', 'NV-1010', 'SwiftNote',
 '15.6-inch everyday laptop with 8GB RAM and 256GB SSD.',
 'A reliable laptop for school and home: 15.6-inch display, 8GB RAM, 256GB SSD and a full-size keyboard with number pad.',
 395000.00, NULL, 14, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='monitors'),
 'ClearView 24-inch Monitor', 'clearview-24-monitor', 'NV-1011', 'ClearView',
 '24-inch Full HD IPS monitor with thin bezels.',
 'A 24-inch IPS monitor with a 75Hz refresh rate, HDMI and VGA inputs and a tilt-adjustable stand.',
 89000.00, NULL, 20, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='techhub-ng'), (SELECT id FROM categories WHERE slug='gaming'),
 'Nexus Pro Wireless Controller', 'nexus-pro-controller', 'NV-1012', 'Nexus',
 'Low-latency wireless controller for PC and consoles.',
 'Ergonomic wireless gamepad with hall-effect triggers, programmable back buttons and a 20-hour rechargeable battery.',
 26500.00, NULL, 40, 0, 0, NULL, NULL, 'active'),

-- StyleHouse ----------------------------------------------------------------
((SELECT id FROM vendors WHERE slug='stylehouse'), (SELECT id FROM categories WHERE slug='mens-fashion'),
 'Classic Oxford Shirt', 'classic-oxford-shirt', 'NV-2001', 'StyleHouse',
 'Breathable cotton shirt for work and weekends.',
 'A tailored Oxford shirt in soft, breathable cotton with a button-down collar. Machine washable.',
 12500.00, 15000.00, 60, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='stylehouse'), (SELECT id FROM categories WHERE slug='mens-fashion'),
 'Slim Fit Chinos', 'slim-fit-chinos', 'NV-2002', 'StyleHouse',
 'Stretch cotton chinos with a clean, modern fit.',
 'Comfortable stretch-cotton chinos with a slim fit, front pockets and a zip fly. Pairs with shirts and sneakers.',
 18000.00, NULL, 45, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='stylehouse'), (SELECT id FROM categories WHERE slug='womens-fashion'),
 'Ankara Wrap Dress', 'ankara-wrap-dress', 'NV-2003', 'StyleHouse',
 'Bold Ankara print wrap dress, tailored in Nigeria.',
 'A flattering wrap dress in vibrant Ankara print with a tie waist and flared skirt. Lined for comfort.',
 22000.00, 27500.00, 30, 1, 1, 17500.00, DATE_ADD(NOW(), INTERVAL 7 DAY), 'active'),

((SELECT id FROM vendors WHERE slug='stylehouse'), (SELECT id FROM categories WHERE slug='womens-fashion'),
 'Silk Blend Scarf', 'silk-blend-scarf', 'NV-2004', 'StyleHouse',
 'Soft, lightweight scarf in four colours.',
 'A lightweight silk-blend scarf that works as a headwrap, neck scarf or bag accessory.',
 7500.00, NULL, 80, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='stylehouse'), (SELECT id FROM categories WHERE slug='shoes'),
 'Urban Runner Sneakers', 'urban-runner-sneakers', 'NV-2005', 'StyleHouse',
 'Cushioned everyday sneakers with a breathable mesh upper.',
 'Lightweight sneakers with a cushioned sole, breathable mesh upper and non-slip grip for all-day comfort.',
 32000.00, 38000.00, 50, 1, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='stylehouse'), (SELECT id FROM categories WHERE slug='shoes'),
 'Leather Derby Shoes', 'leather-derby-shoes', 'NV-2006', 'StyleHouse',
 'Genuine leather lace-up shoes for formal wear.',
 'Handcrafted derby shoes in genuine leather with a cushioned insole and durable rubber sole.',
 41000.00, NULL, 22, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='stylehouse'), (SELECT id FROM categories WHERE slug='skincare'),
 'Hydra Glow Face Serum', 'hydra-glow-serum', 'NV-2007', 'Hydra',
 'Hyaluronic acid and vitamin C serum for glowing skin.',
 'A lightweight 30ml serum with hyaluronic acid and vitamin C that hydrates and evens skin tone. Suitable for daily use.',
 9800.00, NULL, 100, 0, 1, 7900.00, DATE_ADD(NOW(), INTERVAL 7 DAY), 'active'),

((SELECT id FROM vendors WHERE slug='stylehouse'), (SELECT id FROM categories WHERE slug='fragrance'),
 'Oud Noir Eau de Parfum', 'oud-noir-perfume', 'NV-2008', 'Noir',
 'Warm woody fragrance, 100ml.',
 'A rich eau de parfum with notes of oud, amber and vanilla. Long-lasting, 100ml bottle.',
 28500.00, NULL, 35, 1, 0, NULL, NULL, 'active'),

-- HomeNest ------------------------------------------------------------------
((SELECT id FROM vendors WHERE slug='homenest'), (SELECT id FROM categories WHERE slug='kitchen-appliances'),
 'TurboBlend 1.5L Blender', 'turboblend-blender', 'NV-3001', 'TurboBlend',
 '1000W blender with stainless steel blades.',
 'A powerful 1000W blender with a 1.5L glass jug, six stainless steel blades and three speed settings.',
 24500.00, 29000.00, 40, 1, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='homenest'), (SELECT id FROM categories WHERE slug='kitchen-appliances'),
 'AirCrisp 5L Air Fryer', 'aircrisp-air-fryer', 'NV-3002', 'AirCrisp',
 'Cook crispy meals with up to 80% less oil.',
 'A 5-litre air fryer with digital controls, eight cooking presets and a non-stick, dishwasher-safe basket.',
 56000.00, 68000.00, 28, 1, 1, 49500.00, DATE_ADD(NOW(), INTERVAL 7 DAY), 'active'),

((SELECT id FROM vendors WHERE slug='homenest'), (SELECT id FROM categories WHERE slug='furniture'),
 'Ergo Office Chair', 'ergo-office-chair', 'NV-3003', 'Ergo',
 'Mesh back chair with lumbar support.',
 'An ergonomic office chair with breathable mesh, adjustable lumbar support, height control and smooth-rolling casters.',
 72000.00, NULL, 15, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='homenest'), (SELECT id FROM categories WHERE slug='groceries'),
 'Premium Long Grain Rice 10kg', 'premium-rice-10kg', 'NV-3004', 'GoldenGrain',
 'Clean, stone-free long grain rice.',
 'Premium parboiled long grain rice, carefully cleaned and sealed for freshness. 10kg bag.',
 21000.00, NULL, 90, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='homenest'), (SELECT id FROM categories WHERE slug='sports-fitness'),
 'FlexFit Yoga Mat', 'flexfit-yoga-mat', 'NV-3005', 'FlexFit',
 'Non-slip 6mm mat with carry strap.',
 'A 6mm thick non-slip yoga mat with good cushioning, a textured surface and a carry strap.',
 8500.00, NULL, 70, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='homenest'), (SELECT id FROM categories WHERE slug='sports-fitness'),
 'Pro Dumbbell Set 20kg', 'pro-dumbbell-set-20kg', 'NV-3006', 'FlexFit',
 'Adjustable dumbbell set for home workouts.',
 'An adjustable 20kg dumbbell set with chrome handles, secure collars and rubber-coated plates.',
 31000.00, NULL, 25, 0, 0, NULL, NULL, 'active'),

((SELECT id FROM vendors WHERE slug='homenest'), (SELECT id FROM categories WHERE slug='baby-kids'),
 'Cuddle Soft Baby Blanket', 'cuddle-soft-baby-blanket', 'NV-3007', 'Cuddle',
 'Ultra-soft hypoallergenic fleece blanket.',
 'A warm, ultra-soft fleece blanket that is gentle on baby skin and machine washable. 100cm x 80cm.',
 6900.00, NULL, 55, 0, 0, NULL, NULL, 'active');

-- ----------------------------------------------------------------------------
-- VARIANTS (one selectable option per row; run once)
-- ----------------------------------------------------------------------------
INSERT INTO `product_variants` (`product_id`, `attribute_name`, `attribute_value`, `price_adjustment`, `stock_quantity`) VALUES
((SELECT id FROM products WHERE slug='aurora-x5-smartphone'), 'Storage', '128GB', 0.00, 15),
((SELECT id FROM products WHERE slug='aurora-x5-smartphone'), 'Storage', '256GB', 30000.00, 10),

((SELECT id FROM products WHERE slug='classic-oxford-shirt'), 'Size', 'S', 0.00, 12),
((SELECT id FROM products WHERE slug='classic-oxford-shirt'), 'Size', 'M', 0.00, 18),
((SELECT id FROM products WHERE slug='classic-oxford-shirt'), 'Size', 'L', 0.00, 18),
((SELECT id FROM products WHERE slug='classic-oxford-shirt'), 'Size', 'XL', 500.00, 12),

((SELECT id FROM products WHERE slug='slim-fit-chinos'), 'Size', '30', 0.00, 10),
((SELECT id FROM products WHERE slug='slim-fit-chinos'), 'Size', '32', 0.00, 15),
((SELECT id FROM products WHERE slug='slim-fit-chinos'), 'Size', '34', 0.00, 12),
((SELECT id FROM products WHERE slug='slim-fit-chinos'), 'Size', '36', 0.00, 8),

((SELECT id FROM products WHERE slug='ankara-wrap-dress'), 'Size', 'S', 0.00, 8),
((SELECT id FROM products WHERE slug='ankara-wrap-dress'), 'Size', 'M', 0.00, 10),
((SELECT id FROM products WHERE slug='ankara-wrap-dress'), 'Size', 'L', 0.00, 8),
((SELECT id FROM products WHERE slug='ankara-wrap-dress'), 'Size', 'XL', 0.00, 4),

((SELECT id FROM products WHERE slug='silk-blend-scarf'), 'Colour', 'Emerald', 0.00, 20),
((SELECT id FROM products WHERE slug='silk-blend-scarf'), 'Colour', 'Burgundy', 0.00, 20),
((SELECT id FROM products WHERE slug='silk-blend-scarf'), 'Colour', 'Gold', 0.00, 20),
((SELECT id FROM products WHERE slug='silk-blend-scarf'), 'Colour', 'Navy', 0.00, 20),

((SELECT id FROM products WHERE slug='urban-runner-sneakers'), 'Size', '40', 0.00, 10),
((SELECT id FROM products WHERE slug='urban-runner-sneakers'), 'Size', '41', 0.00, 10),
((SELECT id FROM products WHERE slug='urban-runner-sneakers'), 'Size', '42', 0.00, 12),
((SELECT id FROM products WHERE slug='urban-runner-sneakers'), 'Size', '43', 0.00, 10),
((SELECT id FROM products WHERE slug='urban-runner-sneakers'), 'Size', '44', 0.00, 8);

-- ----------------------------------------------------------------------------
-- REVIEWS (approved) + refresh the rating summary on each product
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `reviews` (`product_id`, `user_id`, `rating`, `title`, `comment`, `status`) VALUES
((SELECT id FROM products WHERE slug='aurora-x5-smartphone'), (SELECT id FROM users WHERE email='buyer1@novura.test'), 5, 'Excellent phone', 'Battery easily lasts a full day and the camera is sharp. Delivery was quick.', 'approved'),
((SELECT id FROM products WHERE slug='aurora-x5-smartphone'), (SELECT id FROM users WHERE email='buyer2@novura.test'), 4, 'Very good value', 'Great screen and performance for the price. The charger could be faster.', 'approved'),

((SELECT id FROM products WHERE slug='nova-buds-earbuds'),    (SELECT id FROM users WHERE email='buyer1@novura.test'), 4, 'Good noise cancelling', 'Comfortable fit and strong bass. Battery life is as advertised.', 'approved'),
((SELECT id FROM products WHERE slug='nova-buds-earbuds'),    (SELECT id FROM users WHERE email='buyer2@novura.test'), 4, 'Solid earbuds', 'Pairs instantly and the case is compact.', 'approved'),

((SELECT id FROM products WHERE slug='probook-14-laptop'),    (SELECT id FROM users WHERE email='buyer1@novura.test'), 5, 'Perfect for work', 'Fast, quiet and light. I use it for meetings all day.', 'approved'),
((SELECT id FROM products WHERE slug='probook-14-laptop'),    (SELECT id FROM users WHERE email='buyer2@novura.test'), 5, 'Worth every naira', 'Boots in seconds and the keyboard is comfortable.', 'approved'),

((SELECT id FROM products WHERE slug='urban-runner-sneakers'),(SELECT id FROM users WHERE email='buyer1@novura.test'), 5, 'So comfortable', 'True to size and very light. Got compliments already.', 'approved'),
((SELECT id FROM products WHERE slug='urban-runner-sneakers'),(SELECT id FROM users WHERE email='buyer2@novura.test'), 4, 'Great everyday shoe', 'Good grip and cushioning. The white shows dirt quickly.', 'approved'),

((SELECT id FROM products WHERE slug='aircrisp-air-fryer'),   (SELECT id FROM users WHERE email='buyer1@novura.test'), 4, 'Cooks evenly', 'Chips and chicken come out crispy. Easy to clean.', 'approved'),
((SELECT id FROM products WHERE slug='aircrisp-air-fryer'),   (SELECT id FROM users WHERE email='buyer2@novura.test'), 5, 'Love it', 'Saves oil and time. Fits a whole family meal.', 'approved'),

((SELECT id FROM products WHERE slug='classic-oxford-shirt'), (SELECT id FROM users WHERE email='buyer1@novura.test'), 3, 'Nice but runs small', 'Fabric is good but order one size up.', 'approved'),
((SELECT id FROM products WHERE slug='classic-oxford-shirt'), (SELECT id FROM users WHERE email='buyer2@novura.test'), 4, 'Good quality', 'Holds its shape after washing.', 'approved');

UPDATE `products` p
   SET p.avg_rating   = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM `reviews` r WHERE r.product_id = p.id AND r.status = 'approved'), 0),
       p.review_count = (SELECT COUNT(*) FROM `reviews` r WHERE r.product_id = p.id AND r.status = 'approved')
 WHERE p.id IN (SELECT DISTINCT product_id FROM `reviews`);

-- ----------------------------------------------------------------------------
-- BANNERS (no image files yet: the storefront draws a gradient until you upload)
-- ----------------------------------------------------------------------------
INSERT INTO `banners` (`title`, `subtitle`, `image`, `link_url`, `position`, `sort_order`, `is_active`) VALUES
('Mega Tech Sale',          'Phones, laptops and TVs at unbeatable prices',  'banners/hero-1.jpg', 'public/category.php?slug=electronics',   'hero',  1, 1),
('Fresh Fashion Finds',     'New styles from top Nigerian sellers',          'banners/hero-2.jpg', 'public/category.php?slug=fashion',       'hero',  2, 1),
('Home Upgrade Week',       'Kitchen and home essentials from HomeNest',     'banners/hero-3.jpg', 'public/category.php?slug=home-kitchen',  'hero',  3, 1),
('Flash Sales',             'Limited-time deals. Grab them before they end', 'banners/promo-1.jpg', 'public/shop.php?flash=1',               'promo', 1, 1),
('Sell on Novura',          'Open your store and reach thousands of buyers', 'banners/promo-2.jpg', 'public/register.php?type=vendor',        'promo', 2, 1);
