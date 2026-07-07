<?php
$file = 'c:/xampp/htdocs/egglandbd/agent/retailers.php';
// First, restore to a clean state
exec('git checkout agent/retailers.php');

$content = file_get_contents($file);

// 1. SQL query update
$oldSQL = "SELECT * FROM retailers WHERE agent_id = ? AND status = 'active' ORDER BY name ASC";
$newSQL = "SELECT r.*,
      (SELECT COUNT(*) FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending') as has_order,
      (SELECT o.id FROM orders o WHERE o.retailer_id=r.id AND o.agent_id=? AND o.status='pending' ORDER BY o.created_at DESC LIMIT 1) as order_id
    FROM retailers r
    WHERE r.agent_id = ? AND r.status = 'active'
    ORDER BY r.name ASC";
$content = str_replace($oldSQL, $newSQL, $content);
$content = str_replace("\$stmt->execute([\$agentId]);", "\$stmt->execute([\$agentId, \$agentId, \$agentId]);", $content);

// 2. Fetch products
$productsQuery = "\n\$products = \$pdo->query(\"SELECT * FROM products WHERE status='active' ORDER BY name\")->fetchAll();\n";
$content = str_replace("\$pdo = getDB();", "\$pdo = getDB();\n" . $productsQuery, $content);
$content = str_replace("\$currency = getSetting('currency_symbol', '৳');", "\$currency = getSetting('currency_symbol', '৳');", $content);

// 3. Update Order Button (replace the phone div)
$phonePattern = '/<\?php if \(!empty\(\$r\[\'phone\'\]\)\): \?>\s*<div class="shrink-0">\s*<a href="tel:<\?= htmlspecialchars\(\$r\[\'phone\'\]\) \?>" class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-sm hover:bg-green-100 transition-colors">\s*<i class="fas fa-phone"><\/i>\s*<\/a>\s*<\/div>\s*<\?php endif; \?>/s';
$buttonReplacement = '<div class="flex items-center gap-2 shrink-0">
                            <button onclick="handleOrderClick(<?= $r[\'id\'] ?>)" class="px-3 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold hover:bg-primary hover:text-white transition-colors">
                                <i class="fas fa-shopping-cart mr-1"></i> অর্ডার
                            </button>
                            <?php if (!empty($r[\'phone\'])): ?>
                            <a href="tel:<?= htmlspecialchars($r[\'phone\']) ?>" class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-sm hover:bg-green-100 transition-colors">
                                <i class="fas fa-phone"></i>
                            </a>
                            <?php endif; ?>
                        </div>';
$content = preg_replace($phonePattern, $buttonReplacement, $content);

// 4. Inject CSS
$css = "
<style>
.bottom-sheet { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.bottom-sheet.open { transform: translateY(0); }
.bottom-sheet-overlay { transition: opacity 0.3s ease; }
.bottom-sheet-overlay.active { opacity: 1; pointer-events: auto; }
</style>
</head>";
$content = str_replace('</head>', $css, $content);

// 5. Get Modals HTML from operation.php
$opContent = file_get_contents('c:/xampp/htdocs/egglandbd/agent/operation.php');
preg_match('/(<!-- ========== BOTTOM SHEETS ========== -->.*?)<!-- Sheet 3: Ready Sale -->/s', $opContent, $mMatch);
$modals = $mMatch[1] ?? '';

// 6. Get JS from operation.php
preg_match('/(\/\/ ===== BOTTOM SHEET HELPERS =====.*?)\/\/ ===== READY SALE =====/s', $opContent, $jsMatch);
$js = $jsMatch[1] ?? '';

// Fix the Javascript bug in closeAllSheets
$jsPattern = "/document\.getElementById\(s\)\.classList\.remove\('open'\);/";
$jsReplacement = "const el = document.getElementById(s);\n    if (el) el.classList.remove('open');";
$js = preg_replace($jsPattern, $jsReplacement, $js);

$jsOverlayPattern = "/if \(removeOverlay\) document\.getElementById\('bsOverlay'\)\.classList\.remove\('active'\);/";
$jsOverlayReplacement = "if (removeOverlay) {\n    const overlay = document.getElementById('bsOverlay');\n    if (overlay) overlay.classList.remove('active');\n  }";
$js = preg_replace($jsOverlayPattern, $jsOverlayReplacement, $js);


$jsPrefix = "
    const RETAILERS = <?= json_encode(\$retailers, JSON_UNESCAPED_UNICODE) ?>;
    const PRODUCTS  = <?= json_encode(\$products, JSON_UNESCAPED_UNICODE) ?>;
    const CURRENCY  = '<?= \$currency ?>';
    const BASE_URL  = '<?= BASE_URL ?>';
    let currentRetailer = null;
    let orderItems = {};

    function handleOrderClick(id) {
        const r = RETAILERS.find(x => x.id == id);
        if (r) {
            if (parseInt(r.has_order) > 0) openOrderWarning(r);
            else openNewOrder(r);
        }
    }
    
    function showToast(msg) { alert(msg); }
";

// replace loadSalesMarkers with location.reload()
$js = str_replace('loadSalesMarkers();', 'setTimeout(() => location.reload(), 1000);', $js);
// remove update retailer in array
$js = preg_replace('/const r = RETAILERS.*?data\.order_id; }/s', '', $js);

$bottomInjection = $modals . "\n<script>\n" . $jsPrefix . "\n" . $js . "\n</script>\n</body>";
$content = str_replace('</body>', $bottomInjection, $content);

file_put_contents('c:/xampp/htdocs/egglandbd/agent/retailers.php', $content);
echo "Successfully rebuilt retailers.php with all fixes!\n";
