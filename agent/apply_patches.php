<?php
$file = 'c:/xampp/htdocs/egglandbd/agent/retailers.php';
$content = file_get_contents($file);

// 1. Fix the Order Button in the loop
// Find the phone div correctly regardless of exact whitespace.
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

// 2. Fix the Javascript bug in closeAllSheets
$jsPattern = "/document\.getElementById\(s\)\.classList\.remove\('open'\);/";
$jsReplacement = "const el = document.getElementById(s);\n    if (el) el.classList.remove('open');";
$content = preg_replace($jsPattern, $jsReplacement, $content);

$jsOverlayPattern = "/if \(removeOverlay\) document\.getElementById\('bsOverlay'\)\.classList\.remove\('active'\);/";
$jsOverlayReplacement = "if (removeOverlay) {\n    const overlay = document.getElementById('bsOverlay');\n    if (overlay) overlay.classList.remove('active');\n  }";
$content = preg_replace($jsOverlayPattern, $jsOverlayReplacement, $content);

file_put_contents($file, $content);
echo "Injections applied successfully.\n";
