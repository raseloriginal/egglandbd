<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>এজেন্ট লেজার - তারিখ ভিত্তিক গ্রুপড স্প্রেডশিট</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Hind Siliguri', sans-serif; background-color: #f8fafc; }
        .excel-mono { font-family: 'JetBrains Mono', monospace; font-size: 13px; }
        .excel-table th, .excel-table td { border: 1px solid #cbd5e1; }
        .editable-cell:focus { outline: 2px solid #2563eb; background-color: #eff6ff; font-weight: 600; }
        
        @media print {
            @page { size: A4 portrait; margin: 8mm 10mm; }
            .no-print { display: none !important; }
            body { background: white; padding: 0; color: #000; }
            .print-container { border: none !important; box-shadow: none !important; max-width: 100% !important; width: 100% !important; padding: 0 !important; }
            .excel-table th { background-color: #f1f5f9 !important; color: #0f172a !important; font-weight: 750 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .excel-table td { padding: 5px 8px !important; font-size: 11px !important; }
        }
    </style>
</head>
<body class="p-3 md:p-6 text-slate-900 selection:bg-blue-100">

    <div class="max-w-5xl mx-auto bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden print-container">
        
        <div class="no-print bg-slate-50 border-b border-slate-200 p-4 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="bg-blue-600 text-white font-bold px-3 py-1 rounded-lg text-xs tracking-wider shadow-sm">DATE GROUPED VIEW</span>
                <span class="text-xs text-slate-500 font-medium">💡 একই তারিখে একাধিক অর্ডার থাকলে সেল স্বয়ংক্রিয়ভাবে মার্জ হয়ে যাবে</span>
            </div>
            <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2 rounded-xl text-sm font-semibold shadow transition-all">
                🖨️ এ৪ সাইজে প্রিন্ট / PDF রিপোর্ট
            </button>
        </div>

        <div class="p-6 bg-gradient-to-r from-slate-50 to-white border-b border-slate-100 flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Abid Traders — Charghat (এপ্রিল ২০২৬)</h1>
                <p class="text-xs text-slate-500 font-mono">Multi-Product Distribution Order Matrix &bull; Happy Bangladesh Ltd.</p>
            </div>
            <div class="text-right text-xs">
                <span class="text-slate-400 block uppercase font-bold">রিপোর্ট ফরম্যাট</span>
                <span class="font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 inline-block mt-1">গ্রুপড লেজার</span>
            </div>
        </div>

        <div class="p-4 overflow-x-auto">
            <table class="w-full text-left border-collapse excel-table" id="groupedExcelTable">
                <thead>
                    <tr class="bg-slate-100 text-slate-800 text-xs font-bold tracking-wide uppercase">
                        <th class="text-center w-10 py-3 no-print bg-slate-100/60 text-slate-400 select-none">#</th>
                        <th class="text-center w-28 py-3">তারিখ (Date)</th>
                        <th class="pl-4 py-3 w-48">পণ্যের নাম (Products)</th>
                        <th class="text-right py-3 w-24">পরিমাণ (Qty)</th>
                        <th class="text-right py-3 w-20">রেট (Rate)</th>
                        <th class="text-right py-3 w-28">মোট মূল্য (Price)</th>
                        <th class="text-right py-3 w-28 bg-emerald-50/40 text-emerald-900 border-r border-emerald-100">জমা (Deposit)</th>
                        <th class="text-right py-3 w-28 bg-rose-50/40 text-rose-900">বাকি (Due)</th>
                    </tr>
                </thead>
                <tbody class="text-slate-800 excel-mono divide-y divide-slate-200" id="tableRowContainer">
                    
                    <tr class="calc-row bg-blue-50/10">
                        <td class="text-center text-slate-400 no-print text-xs select-none">1</td>
                        <td class="text-center font-bold text-blue-700 bg-blue-50/30" rowspan="3">07.04.26</td>
                        <td class="pl-4 py-2.5 text-left font-sans font-medium text-red-600">লাল ডিম (A)</td>
                        <td class="text-right editable-cell font-semibold text-slate-900 data-qty" contenteditable="true" oninput="liveCalc()">690</td>
                        <td class="text-right editable-cell text-slate-500 data-rate" contenteditable="true" oninput="liveCalc()">7.666</td>
                        <td class="text-right bg-slate-50/30 text-slate-600 font-medium row-price">0.00</td>
                        <td class="text-right editable-cell bg-emerald-50/10 text-emerald-600 font-bold border-r border-emerald-50 data-deposit" contenteditable="true" oninput="liveCalc()">25200</td>
                        <td class="text-right font-bold row-due">0.00</td>
                    </tr>
                    <tr class="calc-row bg-blue-50/10">
                        <td class="text-center text-slate-400 no-print text-xs select-none">2</td>
                        <td class="pl-4 py-2.5 text-left font-sans font-medium text-slate-600">সাদা ডিম (A)</td>
                        <td class="text-right editable-cell font-semibold text-slate-900 data-qty" contenteditable="true" oninput="liveCalc()">1230</td>
                        <td class="text-right editable-cell text-slate-500 data-rate" contenteditable="true" oninput="liveCalc()">6.733</td>
                        <td class="text-right bg-slate-50/30 text-slate-600 font-medium row-price">0.00</td>
                        <td class="text-right editable-cell bg-emerald-50/10 text-emerald-600 font-bold border-r border-emerald-50 data-deposit" contenteditable="true" oninput="liveCalc()">0</td>
                        <td class="text-right font-bold row-due">0.00</td>
                    </tr>
                    <tr class="calc-row bg-blue-50/10 border-b-2 border-slate-300">
                        <td class="text-center text-slate-400 no-print text-xs select-none">3</td>
                        <td class="pl-4 py-2.5 text-left font-sans font-medium text-amber-700">হাঁসের ডিম (A)</td>
                        <td class="text-right editable-cell font-semibold text-slate-900 data-qty" contenteditable="true" oninput="liveCalc()">400</td>
                        <td class="text-right editable-cell text-slate-500 data-rate" contenteditable="true" oninput="liveCalc()">9.50</td>
                        <td class="text-right bg-slate-50/30 text-slate-600 font-medium row-price">0.00</td>
                        <td class="text-right editable-cell bg-emerald-50/10 text-emerald-600 font-bold border-r border-emerald-50 data-deposit" contenteditable="true" oninput="liveCalc()">10000</td>
                        <td class="text-right font-bold row-due">0.00</td>
                    </tr>

                    <tr class="calc-row">
                        <td class="text-center text-slate-400 no-print text-xs select-none">4</td>
                        <td class="text-center font-medium text-slate-600">13.04.26</td>
                        <td class="pl-4 py-2.5 text-left font-sans font-medium text-slate-600">সাদা ডিম (A)</td>
                        <td class="text-right editable-cell font-semibold text-slate-900 data-qty" contenteditable="true" oninput="liveCalc()">900</td>
                        <td class="text-right editable-cell text-slate-500 data-rate" contenteditable="true" oninput="liveCalc()">7.06</td>
                        <td class="text-right bg-slate-50/30 text-slate-600 font-medium row-price">0.00</td>
                        <td class="text-right editable-cell bg-emerald-50/10 text-emerald-600 font-bold border-r border-emerald-50 data-deposit" contenteditable="true" oninput="liveCalc()">0</td>
                        <td class="text-right font-bold row-due">0.00</td>
                    </tr>

                    <tr class="calc-row bg-slate-50/50">
                        <td class="text-center text-slate-400 no-print text-xs select-none">5</td>
                        <td class="text-center font-medium text-slate-600 bg-slate-100/40" rowspan="2">14.04.26</td>
                        <td class="pl-4 py-2.5 text-left font-sans font-medium text-slate-600">সাদা ডিম (A)</td>
                        <td class="text-right editable-cell font-semibold text-slate-900 data-qty" contenteditable="true" oninput="liveCalc()">1500</td>
                        <td class="text-right editable-cell text-slate-500 data-rate" contenteditable="true" oninput="liveCalc()">7.16</td>
                        <td class="text-right bg-slate-50/30 text-slate-600 font-medium row-price">0.00</td>
                        <td class="text-right editable-cell bg-emerald-50/10 text-emerald-600 font-bold border-r border-emerald-50 data-deposit" contenteditable="true" oninput="liveCalc()">0</td>
                        <td class="text-right font-bold row-due">0.00</td>
                    </tr>
                    <tr class="calc-row bg-slate-50/50">
                        <td class="text-center text-slate-400 no-print text-xs select-none">6</td>
                        <td class="pl-4 py-2.5 text-left font-sans font-medium text-red-600">লাল ডিম (A)</td>
                        <td class="text-right editable-cell font-semibold text-slate-900 data-qty" contenteditable="true" oninput="liveCalc()">800</td>
                        <td class="text-right editable-cell text-slate-500 data-rate" contenteditable="true" oninput="liveCalc()">9.20</td>
                        <td class="text-right bg-slate-50/30 text-slate-600 font-medium row-price">0.00</td>
                        <td class="text-right editable-cell bg-emerald-50/10 text-emerald-600 font-bold border-r border-emerald-50 data-deposit" contenteditable="true" oninput="liveCalc()">15000</td>
                        <td class="text-right font-bold row-due">0.00</td>
                    </tr>
                    
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-400 text-xs">
                        <td class="text-center bg-slate-200 text-slate-400 no-print select-none">Σ</td>
                        <td class="p-3 font-sans text-left text-sm font-bold text-slate-900" colspan="2">সর্বমোট সমষ্টী (GRAND TOTAL)</td>
                        <td class="p-3 text-right text-blue-600 font-bold" id="totalQty">0</td>
                        <td class="p-3 text-center text-slate-300 font-mono">—</td>
                        <td class="p-3 text-right text-slate-900 font-bold" id="totalPrice">0</td>
                        <td class="p-3 text-right text-emerald-700 font-bold bg-emerald-50/40 border-r border-emerald-100" id="totalDeposit">0</td>
                        <td class="p-3 text-right font-bold bg-rose-50/40" id="totalDue">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-slate-50 border-t border-slate-200 px-4 py-3 flex justify-between items-center text-[10px] text-slate-400 font-semibold tracking-wider">
            <div>চারঘাট হাব, রাজশাহী | এ৪ প্রিন্ট ফ্রেন্ডলি ম্যাট্রিক্স সক্রিয়</div>
            <div>Powered by Happy Bangladesh Platforms</div>
        </div>
    </div>

    <script>
        function liveCalc() {
            // সমস্ত ক্লাস 'calc-row' সম্পন্ন রো ধরে লুপ চলবে
            let rows = document.querySelectorAll('.calc-row');
            let totalQty = 0, totalPrice = 0, totalDeposit = 0, totalDue = 0;

            rows.forEach((row) => {
                // সেল মার্জ হলেও আমরা ক্লাস নেম দিয়ে সরাসরি নিখুঁত ভ্যালু রিড করতে পারি
                let qty = parseFloat(row.querySelector('.data-qty').innerText.replace(/,/g, '')) || 0;
                let rate = parseFloat(row.querySelector('.data-rate').innerText.replace(/,/g, '')) || 0;
                let deposit = parseFloat(row.querySelector('.data-deposit').innerText.replace(/,/g, '')) || 0;
                
                // রো ভিত্তিক ক্যালকুলেশন
                let price = qty * rate;
                let due = deposit - price;

                row.querySelector('.row-price').innerText = price.toFixed(2);
                
                let dueCell = row.querySelector('.row-due');
                dueCell.innerText = due.toFixed(2);
                
                // বকেয়া নেগেটিভ এবং পজিটিভের জন্য নিখুঁত কালার কোডিং
                if (due < 0) { 
                    dueCell.className = "text-right font-bold row-due text-rose-600 bg-rose-50/10"; 
                } else if (due > 0) { 
                    dueCell.className = "text-right font-bold row-due text-emerald-600 bg-emerald-50/10"; 
                } else { 
                    dueCell.className = "text-right font-semibold row-due text-slate-300"; 
                }

                // গ্র্যান্ড টোটাল যোগফল
                totalQty += qty;
                totalPrice += price;
                totalDeposit += deposit;
                totalDue += due;
            });

            // ফুটারে গ্র্যান্ড টোটাল পুশ করা
            document.getElementById('totalQty').innerText = totalQty.toLocaleString();
            document.getElementById('totalPrice').innerText = totalPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('totalDeposit').innerText = totalDeposit.toLocaleString();
            
            let finalDueCell = document.getElementById('totalDue');
            finalDueCell.innerText = totalDue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            if (totalDue < 0) {
                finalDueCell.className = "p-3 text-right font-bold bg-rose-50/40 text-rose-600";
            } else {
                finalDueCell.className = "p-3 text-right font-bold bg-emerald-50/40 text-emerald-700";
            }
        }

        // পেজ লোড হওয়ার সাথে সাথে রান হবে
        window.onload = liveCalc;
    </script>
</body>
</html>