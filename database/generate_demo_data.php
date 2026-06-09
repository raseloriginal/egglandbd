<?php
// ============================================================
// EGGLAND BD - Multi-Agent & Detailed Demo Data Generator
// ============================================================

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/audit.php';

$db = Database::getInstance();

echo "Starting complete database rebuild and multi-agent demo data generation...\n";

// Disable foreign key checks to truncate clean
$db->exec("SET FOREIGN_KEY_CHECKS = 0;");

$tables = [
    'order_items', 'deliveries', 'cash_collections', 'ledger', 'deposits',
    'expenses', 'attendance', 'notifications', 'audit_logs', 'orders',
    'retailers', 'egg_lots', 'sr', 'dsr', 'agents', 'users', 'products',
    'categories', 'egg_types', 'areas', 'roles', 'user_tokens'
];

foreach ($tables as $tbl) {
    $db->exec("TRUNCATE TABLE `$tbl`;");
}

$db->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "All tables truncated successfully.\n";

// --- 1. Seed Roles ---
$db->exec("INSERT INTO `roles` (`id`, `name`, `slug`) VALUES
(1, 'Admin', 'admin'),
(2, 'Agent', 'agent'),
(3, 'SR', 'sr'),
(4, 'DSR', 'dsr');");

// --- 2. Seed Areas ---
$db->exec("INSERT INTO `areas` (`id`, `name`, `district`) VALUES
(1, 'Mirpur', 'Dhaka'),
(2, 'Mohammadpur', 'Dhaka'),
(3, 'Uttara', 'Dhaka'),
(4, 'Gulshan', 'Dhaka'),
(5, 'Dhanmondi', 'Dhaka'),
(6, 'Motijheel', 'Dhaka'),
(7, 'Khilgaon', 'Dhaka'),
(8, 'Rayer Bazar', 'Dhaka');");

// --- 3. Seed Egg Types ---
$db->exec("INSERT INTO `egg_types` (`id`, `name`, `description`) VALUES
(1, 'Desi Egg', 'Free-range country eggs'),
(2, 'Farm Egg', 'Poultry farm commercial eggs'),
(3, 'Hybrid Egg', 'Hybrid breed eggs'),
(4, 'Duck Egg', 'Fresh duck eggs'),
(5, 'Quail Egg', 'Small quail eggs');");

// --- 4. Seed Categories ---
$db->exec("INSERT INTO `categories` (`id`, `name`, `icon`, `color`, `sort_order`) VALUES
(1, 'Desi Eggs', 'fa-egg', '#8B002D', 1),
(2, 'Farm Eggs', 'fa-egg', '#F5B400', 2),
(3, 'Specialty Eggs', 'fa-star', '#650020', 3);");

// --- 5. Seed Products ---
$db->exec("INSERT INTO `products` (`id`, `category_id`, `egg_type_id`, `name`, `sku`, `unit`, `unit_size`, `buying_price`, `selling_price`, `current_stock`, `low_stock_alert`) VALUES
(1, 1, 1, 'Desi Egg (Single)', 'DE-001', 'piece', 1, 12.00, 14.00, 50000, 2000),
(2, 1, 1, 'Desi Egg (Tray 30)', 'DE-030', 'tray', 30, 350.00, 410.00, 2000, 200),
(3, 2, 2, 'Farm Egg (Single)', 'FE-001', 'piece', 1, 8.00, 10.00, 100000, 5000),
(4, 2, 2, 'Farm Egg (Tray 30)', 'FE-030', 'tray', 30, 230.00, 280.00, 5000, 300),
(5, 2, 2, 'Farm Egg (Crate 90)', 'FE-090', 'crate', 90, 680.00, 820.00, 1000, 100),
(6, 3, 4, 'Duck Egg (Single)', 'DK-001', 'piece', 1, 15.00, 18.00, 20000, 1000),
(7, 3, 5, 'Quail Egg (Pack 12)', 'QE-012', 'pack', 12, 35.00, 45.00, 5000, 500);");

echo "Core static tables seeded.\n";

// --- 6. Seed Users (1 Admin, 3 Agents, 3 SRs, 3 DSRs) ---
// All passwords are Admin@1234
$passHash = '$2y$10$wvtnHY3gURrlt7xxdCh8O.mgbWTHyfHr93VQOzhgV5Gdons8MyAPS';

$users = [
    // Admin
    [1, 1, 'System Admin', 'admin', 'admin@egglandbd.com', '01700000000'],
    // Rahim Agency (Agent 1)
    [2, 2, 'Rahim Agent', 'agent1', 'agent1@egglandbd.com', '01711111111'],
    [3, 3, 'Karim SR', 'sr1', 'sr1@egglandbd.com', '01722222222'],
    [4, 4, 'Hasan DSR', 'dsr1', 'dsr1@egglandbd.com', '01733333333'],
    // Salam Agency (Agent 2)
    [5, 2, 'Salam Agent', 'agent2', 'agent2@egglandbd.com', '01711111122'],
    [6, 3, 'Jafar SR', 'sr2', 'sr2@egglandbd.com', '01722222233'],
    [7, 4, 'Akbar DSR', 'dsr2', 'dsr2@egglandbd.com', '01733333344'],
    // Kuddus Agency (Agent 3)
    [8, 2, 'Kuddus Agent', 'agent3', 'agent3@egglandbd.com', '01711111133'],
    [9, 3, 'Milon SR', 'sr3', 'sr3@egglandbd.com', '01722222244'],
    [10, 4, 'Sohel DSR', 'dsr3', 'dsr3@egglandbd.com', '01733333355']
];

$insUser = $db->prepare("
    INSERT INTO users (id, role_id, name, username, email, phone, password, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
");

foreach ($users as $u) {
    $insUser->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $passHash]);
}

echo "Inserted 10 user accounts.\n";

// --- 7. Insert Profiles (Agents, SRs, DSRs) ---
// Rahim Agency
$db->exec("INSERT INTO agents (id, user_id, area_id, commission_type, commission_rate, credit_limit, joining_date) VALUES
(1, 2, 1, 'percentage', 2.50, 500000.00, CURDATE())");
$db->exec("INSERT INTO sr (id, user_id, agent_id, area_id, commission_rate, joining_date) VALUES
(1, 3, 1, 1, 1.50, CURDATE())");
$db->exec("INSERT INTO dsr (id, user_id, agent_id, area_id, vehicle_no, commission_rate, current_lat, current_lng, joining_date) VALUES
(1, 4, 1, 1, 'D-MIRPUR-01', 1.00, 23.8150, 90.3640, CURDATE())");

// Salam Agency
$db->exec("INSERT INTO agents (id, user_id, area_id, commission_type, commission_rate, credit_limit, joining_date) VALUES
(2, 5, 3, 'percentage', 3.00, 600000.00, CURDATE())");
$db->exec("INSERT INTO sr (id, user_id, agent_id, area_id, commission_rate, joining_date) VALUES
(2, 6, 2, 3, 1.80, CURDATE())");
$db->exec("INSERT INTO dsr (id, user_id, agent_id, area_id, vehicle_no, commission_rate, current_lat, current_lng, joining_date) VALUES
(2, 7, 2, 3, 'D-UTTARA-02', 1.20, 23.8720, 90.3980, CURDATE())");

// Kuddus Agency
$db->exec("INSERT INTO agents (id, user_id, area_id, commission_type, commission_rate, credit_limit, joining_date) VALUES
(3, 8, 6, 'percentage', 2.80, 550000.00, CURDATE())");
$db->exec("INSERT INTO sr (id, user_id, agent_id, area_id, commission_rate, joining_date) VALUES
(3, 9, 3, 6, 1.60, CURDATE())");
$db->exec("INSERT INTO dsr (id, user_id, agent_id, area_id, vehicle_no, commission_rate, current_lat, current_lng, joining_date) VALUES
(3, 10, 3, 6, 'D-MOTIJHEEL-03', 1.10, 23.7330, 90.4170, CURDATE())");

echo "Seeded profiles for 3 Agents, 3 SRs, and 3 DSRs.\n";

// --- 8. Seed Retailers (30 retailers total: 10 per agent/agency) ---
$retailersData = [
    // Rahim Agency (Agent 1 - Mirpur & Mohammadpur)
    [1, 3, 1, 'Mitin General Store', 'Mitin Islam', '01900000001', 'Mirpur-1, Dhaka', 23.8103, 90.3654, 50000.00],
    [1, 3, 1, 'Shewra Market Store', 'Jalal Uddin', '01900000002', 'Shewra Bazar, Mirpur', 23.8200, 90.3700, 30000.00],
    [1, 3, 2, 'Mohammadpur Egg Corner', 'Hafizur Rahman', '01900000003', 'Mohammadpur, Dhaka', 23.7615, 90.3562, 40000.00],
    [1, 3, 2, 'Town Hall Retail', 'Motaleb Hossain', '01900000004', 'Mohammadpur Town Hall', 23.7580, 90.3540, 25000.00],
    [1, 3, 1, 'Pallabi Traders', 'Mahbub Alam', '01900000016', 'Pallabi, Mirpur-12', 23.8240, 90.3620, 45000.00],
    [1, 3, 1, 'Kazipara Egg House', 'Selim Raza', '01900000017', 'Kazipara, Mirpur', 23.8010, 90.3720, 35000.00],
    [1, 3, 2, 'Adabor General Store', 'Shafik Ahmed', '01900000018', 'Ring Road, Adabor', 23.7680, 90.3510, 30000.00],
    [1, 3, 2, 'Shyamoli Grocery', 'Faruk Khan', '01900000019', 'Shyamoli, Dhaka', 23.7720, 90.3610, 50000.00],
    [1, 3, 1, 'Mirpur-10 Coop', 'Asaduzzaman', '01900000020', 'Mirpur-10 Roundabout', 23.8060, 90.3680, 60000.00],
    [1, 3, 2, 'Sher-e-Bangla Shop', 'Anisur Rahman', '01900000021', 'Agargaon Road, Dhaka', 23.7780, 90.3720, 40000.00],

    // Salam Agency (Agent 2 - Uttara & Gulshan)
    [2, 6, 3, 'Uttara Grocers', 'Sarker Alam', '01900000005', 'Sector 3, Uttara', 23.8720, 90.3980, 60000.00],
    [2, 6, 3, 'Daily Mart Uttara', 'Kamrul Hasan', '01900000006', 'Sector 11, Uttara', 23.8910, 90.3880, 80000.00],
    [2, 6, 4, 'Gulshan Egg Supply', 'Rezaul Karim', '01900000007', 'Gulshan-2, Dhaka', 23.7925, 90.4078, 100000.00],
    [2, 6, 4, 'Niketon Store', 'Farhad Ahmed', '01900000008', 'Niketon, Gulshan', 23.7800, 90.4100, 50000.00],
    [2, 6, 3, 'Azampur Egg Traders', 'Sajjad Hossain', '01900000022', 'Azampur, Uttara', 23.8680, 90.4010, 55000.00],
    [2, 6, 3, 'Abdullahpur Bazar Shop', 'Mizanur Miah', '01900000023', 'Abdullahpur, Uttara', 23.8980, 90.4030, 40000.00],
    [2, 6, 4, 'Banani Fresh Foods', 'Tanvir Rahman', '01900000015', 'Road 11, Banani', 23.7930, 90.4020, 95000.00],
    [2, 6, 4, 'Baridhara Corner', 'Kamal Uddin', '01900000024', 'Baridhara J-Block', 23.8010, 90.4210, 70000.00],
    [2, 6, 3, 'Uttara Sector 4 Coop', 'Zakir Hossain', '01900000025', 'Sector 4, Uttara', 23.8750, 90.3990, 50000.00],
    [2, 6, 4, 'Gulshan-1 Super Store', 'Rashedul Islam', '01900000026', 'Gulshan-1 Circle', 23.7790, 90.3970, 85000.00],

    // Kuddus Agency (Agent 3 - Motijheel, Dhanmondi, Khilgaon)
    [3, 9, 5, 'Dhanmondi Egg House', 'Bashar Chowdhury', '01900000009', 'Dhanmondi 27, Dhaka', 23.7540, 90.3720, 75000.00],
    [3, 9, 8, 'Rayer Bazar Store', 'Ahsan Ullah', '01900000010', 'Rayer Bazar, Dhaka', 23.7420, 90.3600, 35000.00],
    [3, 9, 6, 'Motijheel Super Shop', 'Mainul Islam', '01900000011', 'Motijheel C/A, Dhaka', 23.7330, 90.4170, 90000.00],
    [3, 9, 7, 'Khilgaon Egg Plaza', 'Sujan Miah', '01900000012', 'Taltola, Khilgaon', 23.7500, 90.4280, 45000.00],
    [3, 9, 7, 'Baily Road Bakers', 'Ashraf Ali', '01900000013', 'Baily Road, Dhaka', 23.7430, 90.4090, 80000.00],
    [3, 9, 5, 'Kalabagan Egg Mart', 'Tariqul Islam', '01900000027', 'Kalabagan, Dhaka', 23.7480, 90.3760, 40000.00],
    [3, 9, 5, 'Sobhanbagh Store', 'Ehsan Habib', '01900000028', 'Sobhanbagh, Dhanmondi', 23.7530, 90.3770, 50000.00],
    [3, 9, 6, 'Dilkusha Egg Point', 'Nazmul Huda', '01900000029', 'Dilkusha C/A, Motijheel', 23.7310, 90.4190, 85000.00],
    [3, 9, 7, 'Goriban Bazar Shop', 'Abul Kashem', '01900000030', 'Goriban, Khilgaon', 23.7550, 90.4350, 30000.00],
    [3, 9, 8, 'Zigatola Coop Store', 'Jahangir Alam', '01900000031', 'Zigatola Post Office', 23.7390, 90.3700, 45000.00]
];

$insRetailer = $db->prepare("
    INSERT INTO retailers (agent_id, added_by, area_id, name, owner_name, phone, address, lat, lng, credit_limit, outstanding_balance)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00)
");

$retailerIds = [];
$retailerAgentMap = []; // Keep track of which retailer belongs to which agent
foreach ($retailersData as $r) {
    $insRetailer->execute([
        $r[0], // agent_id
        $r[1], // added_by (SR user ID)
        $r[2], // area_id
        $r[3], // name
        $r[4], // owner_name
        $r[5], // phone
        $r[6], // address
        $r[7], // lat
        $r[8], // lng
        $r[9]  // credit_limit
    ]);
    $id = $db->lastInsertId();
    $retailerIds[] = $id;
    $retailerAgentMap[$id] = [
        'agent_id' => $r[0],
        'sr_id'    => ($r[0] === 1 ? 1 : ($r[0] === 2 ? 2 : 3)),
        'dsr_id'   => ($r[0] === 1 ? 1 : ($r[0] === 2 ? 2 : 3)),
        'sr_user_id' => $r[1]
    ];
}

echo "Inserted " . count($retailerIds) . " retailers across all 3 agents.\n";

// --- 9. Insert Egg Lots (Supplies) (18 supply lots total) ---
$products = $db->query("SELECT id, buying_price, selling_price, current_stock FROM products")->fetchAll();
$insLot = $db->prepare("
    INSERT INTO egg_lots (lot_number, product_id, supplier_name, supplier_phone, purchase_date, quantity, buying_price, total_cost, current_balance, status, added_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1)
");

$lotIndex = 1;
foreach ($products as $p) {
    // Generate 2-3 lots per product
    for ($l = 1; $l <= 2; $l++) {
        $qty = rand(20, 60) * 1000; // 20k to 60k eggs
        $cost = $qty * $p['buying_price'];
        $date = date('Y-m-d', strtotime('-' . rand(5, 25) . ' days'));
        $lotNum = 'LOT-' . date('Y') . '-' . str_pad($lotIndex++, 3, '0', STR_PAD_LEFT);
        
        $insLot->execute([
            $lotNum,
            $p['id'],
            'Poultry Farms Corp ' . chr(64 + rand(1, 4)),
            '018999999' . rand(10, 99),
            $date,
            $qty,
            $p['buying_price'],
            $cost,
            $qty
        ]);
    }
}

echo "Inserted " . ($lotIndex - 1) . " egg lots.\n";

// --- 10. Insert Orders, Deliveries, and Cash Collections (135 orders total) ---
$orderStatuses = ['delivered', 'delivered', 'delivered', 'delivered', 'approved', 'processing', 'pending', 'cancelled'];
$paymentMethods = ['cash', 'bkash', 'nagad', 'bank'];

$insOrder = $db->prepare("
    INSERT INTO orders (order_number, retailer_id, agent_id, sr_id, dsr_id, order_type, status, subtotal, discount, grand_total, paid_amount, due_amount, payment_status, notes, approved_by, approved_at, delivered_at, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, 'regular', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insOrderItem = $db->prepare("
    INSERT INTO order_items (order_id, product_id, quantity, unit_price, total)
    VALUES (?, ?, ?, ?, ?)
");

$insDelivery = $db->prepare("
    INSERT INTO deliveries (order_id, dsr_id, status, scheduled_date, delivered_at, delivery_lat, delivery_lng, notes, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insCollection = $db->prepare("
    INSERT INTO cash_collections (order_id, retailer_id, agent_id, collected_by, amount, payment_method, reference, notes, collected_at, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insLedger = $db->prepare("
    INSERT INTO ledger (retailer_id, agent_id, type, reference_type, reference_id, debit, credit, balance, notes, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?)
");

$orderCount = 135;
$generatedCount = 0;

for ($i = $orderCount; $i >= 1; $i--) {
    $daysAgo = rand(0, 14);
    $createdAt = date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days -' . rand(1, 12) . ' hours'));
    $orderDate = date('Y-m-d', strtotime($createdAt));
    
    $retailerId = $retailerIds[array_rand($retailerIds)];
    $rMeta = $retailerAgentMap[$retailerId];
    
    $agentId = $rMeta['agent_id'];
    $srId    = $rMeta['sr_id'];
    $dsrId   = $rMeta['dsr_id'];
    $srUserId = $rMeta['sr_user_id'];
    
    $status = $orderStatuses[array_rand($orderStatuses)];
    
    // Pick 1-3 random products for order
    $itemCount = rand(1, 3);
    $selectedProducts = array_rand($products, $itemCount);
    if (!is_array($selectedProducts)) $selectedProducts = [$selectedProducts];
    
    $orderItems = [];
    $subtotal = 0;
    foreach ($selectedProducts as $idx) {
        $p = $products[$idx];
        
        $qty = rand(1, 15) * 10; // 10 to 150 trays/items
        if ($p['id'] == 1 || $p['id'] == 3 || $p['id'] == 6) { // Single eggs
            $qty = rand(2, 30) * 100; // 200 to 3000 pieces
        }
        
        $total = $qty * $p['selling_price'];
        $subtotal += $total;
        $orderItems[] = [
            'id' => $p['id'],
            'qty' => $qty,
            'price' => $p['selling_price'],
            'total' => $total
        ];
    }
    
    $discount = (rand(1, 10) > 8) ? round($subtotal * 0.05, 2) : 0.00;
    $grandTotal = $subtotal - $discount;
    
    $paidAmount = 0.00;
    $dueAmount = $grandTotal;
    $payStatus = 'unpaid';
    
    if ($status === 'delivered') {
        $payRand = rand(1, 10);
        if ($payRand > 5) {
            $paidAmount = $grandTotal;
            $dueAmount = 0.00;
            $payStatus = 'paid';
        } elseif ($payRand > 2) {
            $paidAmount = round($grandTotal * (rand(3, 7) / 10), 2);
            $dueAmount = $grandTotal - $paidAmount;
            $payStatus = 'partial';
        }
    }
    
    $orderNum = 'ORD-' . date('Ymd', strtotime($createdAt)) . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
    
    $approvedBy = ($status !== 'pending' && $status !== 'cancelled') ? 1 : null;
    $approvedAt = $approvedBy ? date('Y-m-d H:i:s', strtotime($createdAt . ' + ' . rand(10, 60) . ' minutes')) : null;
    $deliveredAtStr = ($status === 'delivered') ? date('Y-m-d H:i:s', strtotime($createdAt . ' + ' . rand(2, 6) . ' hours')) : null;
    
    $insOrder->execute([
        $orderNum,
        $retailerId,
        $agentId,
        $srId,
        $dsrId,
        $status,
        $subtotal,
        $discount,
        $grandTotal,
        $paidAmount,
        $dueAmount,
        $payStatus,
        'Demo order transaction.',
        $approvedBy,
        $approvedAt,
        $deliveredAtStr,
        $createdAt,
        $createdAt
    ]);
    $orderId = $db->lastInsertId();
    
    // Insert items
    foreach ($orderItems as $item) {
        $insOrderItem->execute([
            $orderId,
            $item['id'],
            $item['qty'],
            $item['price'],
            $item['total']
        ]);
        
        // Update product stock if delivered
        if ($status === 'delivered') {
            $db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
               ->execute([$item['qty'], $item['id']]);
        } elseif (in_array($status, ['approved', 'processing'])) {
            $db->prepare("UPDATE products SET reserved_stock = reserved_stock + ? WHERE id = ?")
               ->execute([$item['qty'], $item['id']]);
        }
    }
    
    // Insert Delivery
    if ($status === 'delivered') {
        $insDelivery->execute([
            $orderId,
            $dsrId,
            'delivered',
            $orderDate,
            $deliveredAtStr,
            23.75 + (rand(-100, 100) / 1000),
            90.38 + (rand(-100, 100) / 1000),
            'Delivered successfully.',
            $createdAt
        ]);
        
        // Ledger Sale
        $insLedger->execute([
            $retailerId,
            $agentId,
            'sale',
            'order',
            $orderId,
            $grandTotal,
            0.00,
            "Order sale #{$orderNum}",
            $createdAt
        ]);
        
        // Cash Collection
        if ($paidAmount > 0) {
            $payMethod = $paymentMethods[array_rand($paymentMethods)];
            $ref = ($payMethod !== 'cash') ? 'TXN' . rand(100000, 999999) : null;
            
            // Collected by the corresponding SR User ID of that retailer
            $insCollection->execute([
                $orderId,
                $retailerId,
                $agentId,
                $srUserId,
                $paidAmount,
                $payMethod,
                $ref,
                "Collected for order {$orderNum}.",
                $orderDate,
                $createdAt
            ]);
            $collectionId = $db->lastInsertId();
            
            $insLedger->execute([
                $retailerId,
                $agentId,
                'payment',
                'collection',
                $collectionId,
                0.00,
                $paidAmount,
                "Payment received for order {$orderNum} [{$payMethod}]",
                $createdAt
            ]);
        }
    } elseif ($status === 'processing') {
        $insDelivery->execute([
            $orderId,
            $dsrId,
            'in_transit',
            $orderDate,
            null,
            null,
            null,
            'Delivery in transit.',
            $createdAt
        ]);
    }
    
    $generatedCount++;
}

echo "Generated " . $generatedCount . " orders/deliveries/ledgers.\n";

// --- 11. Insert Agent Deposits (25 deposits: ~8 per agent) ---
$banks = ['DBBL', 'City Bank', 'Brac Bank', 'EBL', 'Standard Chartered'];
$insDeposit = $db->prepare("
    INSERT INTO deposits (agent_id, amount, bank_name, account_number, reference, notes, status, deposited_at, added_by, confirmed_by, confirmed_at, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
");

for ($d = 1; $d <= 24; $d++) {
    $daysAgo = rand(1, 14);
    $depDate = date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
    $depTime = date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days -' . rand(1, 8) . ' hours'));
    $amount = rand(5, 25) * 10000;
    
    $agId = rand(1, 3);
    // added_by the corresponding Agent's user_id: Agent1=2, Agent2=5, Agent3=8
    $agUserId = ($agId === 1 ? 2 : ($agId === 2 ? 5 : 8));
    
    $bank = $banks[array_rand($banks)];
    $acc = 'ACC-' . rand(1000, 9999) . '-' . rand(10000, 99999);
    $status = (rand(1, 10) > 3) ? 'confirmed' : 'pending';
    
    $confAt = ($status === 'confirmed') ? date('Y-m-d H:i:s', strtotime($depTime . ' + ' . rand(1, 5) . ' hours')) : null;
    
    $insDeposit->execute([
        $agId,
        $amount,
        $bank,
        $acc,
        'DEP' . rand(10000, 99999),
        'Regular bank deposit.',
        $status,
        $depDate,
        $agUserId,
        $confAt,
        $depTime
    ]);
}

echo "Generated 24 bank deposits across all 3 agents.\n";

// --- 12. Insert Expenses (30 expenses total: ~10 per agent) ---
$expCategories = ['fuel', 'packaging', 'rent', 'salaries', 'utilities', 'other'];
$expDetails = [
    'fuel' => ['Truck fuel replenishment', 'CNG recharge for delivery vehicles', 'Generator diesel fuel'],
    'packaging' => ['Purchase of plastic egg trays', 'Paper carton purchase', 'Securing ropes'],
    'rent' => ['Warehouse monthly rent partition', 'Garage rental payment'],
    'salaries' => ['DSR commission daily allowance', 'Staff daily overtime payment'],
    'utilities' => ['Warehouse electricity bill', 'Office internet subscription', 'Water supply charge'],
    'other' => ['Vehicle minor repair & maintenance', 'Teatime snacks for staff', 'Office stationary items']
];

$insExpense = $db->prepare("
    INSERT INTO expenses (agent_id, category, description, amount, reference, expense_date, notes, added_by, created_by, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

for ($e = 1; $e <= 30; $e++) {
    $daysAgo = rand(1, 14);
    $expDate = date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
    $expTime = date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days -' . rand(1, 10) . ' hours'));
    
    $agId = rand(1, 3);
    $agUserId = ($agId === 1 ? 2 : ($agId === 2 ? 5 : 8));
    
    $cat = $expCategories[array_rand($expCategories)];
    $descList = $expDetails[$cat];
    $desc = $descList[array_rand($descList)];
    $amount = rand(5, 50) * 100;
    if ($cat === 'rent') $amount = rand(10, 25) * 1000;
    if ($cat === 'salaries') $amount = rand(8, 15) * 1000;
    
    $insExpense->execute([
        $agId,
        $cat,
        $desc,
        $amount,
        'EXP' . rand(10000, 99999),
        $expDate,
        'Operational warehouse cost.',
        $agUserId,
        $agUserId,
        $expTime
    ]);
}

echo "Generated 30 expense records across all 3 agents.\n";

// --- 13. Insert Attendance Records (last 14 days for all 3 SRs and 3 DSRs) ---
$insAtt = $db->prepare("
    INSERT INTO attendance (user_id, date, status, clock_in, clock_out, clock_in_lat, clock_in_lng, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$staffUsers = [
    3 => 'sr', 4 => 'dsr', // Rahim
    6 => 'sr', 7 => 'dsr', // Salam
    9 => 'sr', 10 => 'dsr' // Kuddus
];

for ($day = 14; $day >= 0; $day--) {
    $date = date('Y-m-d', strtotime('-' . $day . ' days'));
    if (date('N', strtotime($date)) == 5) continue; // Skip Fridays
    
    foreach ($staffUsers as $uId => $type) {
        // 90% attendance rate
        if (rand(1, 10) === 10) {
            $insAtt->execute([
                $uId, $date, 'absent', null, null, null, null, $date . ' 00:00:00'
            ]);
            continue;
        }
        
        $inTime = ($type === 'sr') 
            ? date('H:i:s', strtotime('08:30:00 +' . rand(5, 45) . ' minutes'))
            : date('H:i:s', strtotime('08:15:00 +' . rand(5, 40) . ' minutes'));
            
        $outTime = ($type === 'sr')
            ? date('H:i:s', strtotime('17:00:00 +' . rand(10, 90) . ' minutes'))
            : date('H:i:s', strtotime('18:00:00 +' . rand(10, 100) . ' minutes'));
            
        $insAtt->execute([
            $uId,
            $date,
            'present',
            $inTime,
            $outTime,
            23.8000 + (rand(-50, 50) / 1000),
            90.3500 + (rand(-50, 50) / 1000),
            $date . ' ' . $inTime
        ]);
    }
}

echo "Generated multi-employee attendance logs.\n";

// --- 14. Re-calculate Retailers Outstanding Balances & Ledger Balance ---
echo "Recalculating outstanding balances and ledger running balances...\n";

foreach ($retailerIds as $retId) {
    $entries = $db->prepare("SELECT id, debit, credit FROM ledger WHERE retailer_id = ? ORDER BY created_at ASC, id ASC");
    $entries->execute([$retId]);
    $ledgerItems = $entries->fetchAll();
    
    $runningBalance = 0.00;
    $updL = $db->prepare("UPDATE ledger SET balance = ? WHERE id = ?");
    
    foreach ($ledgerItems as $item) {
        $debit = (float)$item['debit'];
        $credit = (float)$item['credit'];
        $runningBalance += ($debit - $credit);
        
        $updL->execute([$runningBalance, $item['id']]);
    }
    
    $db->prepare("UPDATE retailers SET outstanding_balance = ? WHERE id = ?")
       ->execute([$runningBalance, $retId]);
}

echo "All retailer outstanding balances reconciled.\n";
echo "Database rebuild complete! Fully populated with multi-agent data.\n";
