<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

/*
|--------------------------------------------------------------------------
| Sales Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS transaction_count,
        COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
");

$stmt->execute([$from, $to]);

$sales_summary = $stmt->fetch();

$transaction_count =
    (int) ($sales_summary['transaction_count'] ?? 0);

$total_revenue =
    (float) ($sales_summary['total_revenue'] ?? 0);

/*
|--------------------------------------------------------------------------
| Top Selling Products
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        products.name AS product_name,
        products.sku,
        SUM(sale_items.quantity) AS quantity_sold,
        SUM(sale_items.subtotal) AS revenue

    FROM sale_items

    INNER JOIN sales
        ON sale_items.sale_id = sales.id

    INNER JOIN products
        ON sale_items.product_id = products.id

    WHERE DATE(sales.sale_date) BETWEEN ? AND ?

    GROUP BY
        products.id,
        products.name,
        products.sku

    ORDER BY quantity_sold DESC
");

$stmt->execute([$from, $to]);

$top_products = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Current Stock Report
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        products.id,
        products.name,
        products.sku,
        products.category,
        products.stock_quantity,
        products.reorder_level,
        products.buying_price,
        products.selling_price,

        (
            products.stock_quantity *
            products.buying_price
        ) AS stock_buying_value,

        (
            products.stock_quantity *
            products.selling_price
        ) AS stock_selling_value,

        suppliers.name AS supplier_name

    FROM products

    LEFT JOIN suppliers
        ON products.supplier_id = suppliers.id

    ORDER BY products.stock_quantity ASC
");

$stock_report = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Stock Totals
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT

        COALESCE(
            SUM(
                stock_quantity * buying_price
            ),
            0
        ) AS total_buying_value,

        COALESCE(
            SUM(
                stock_quantity * selling_price
            ),
            0
        ) AS total_selling_value,

        COALESCE(
            SUM(
                CASE
                    WHEN stock_quantity <= reorder_level
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS low_stock_count

    FROM products
");

$stock_summary = $stmt->fetch();

$total_buying_value =
    (float) ($stock_summary['total_buying_value'] ?? 0);

$total_selling_value =
    (float) ($stock_summary['total_selling_value'] ?? 0);

$low_stock_count =
    (int) ($stock_summary['low_stock_count'] ?? 0);

$potential_margin =
    $total_selling_value - $total_buying_value;

/*
|--------------------------------------------------------------------------
| Create PDF
|--------------------------------------------------------------------------
*/

$pdf = new TCPDF(
    'P',
    'mm',
    'A4',
    true,
    'UTF-8',
    false
);

$pdf->SetCreator('SoftgeniousDev');
$pdf->SetAuthor('SoftgeniousDev');

$pdf->SetTitle(
    'SoftgeniousDev Inventory & Sales Report'
);

$pdf->SetSubject(
    'Inventory and Sales Report'
);

$pdf->SetMargins(
    12,
    15,
    12
);

$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

$pdf->SetAutoPageBreak(
    true,
    15
);

$pdf->AddPage();

$pdf->SetFont(
    'helvetica',
    '',
    10
);

/*
|--------------------------------------------------------------------------
| PDF Header
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'helvetica',
    'B',
    20
);

$pdf->Cell(
    0,
    10,
    'SOFTGENIOUSDEV',
    0,
    1,
    'C'
);

$pdf->SetFont(
    'helvetica',
    '',
    11
);

$pdf->Cell(
    0,
    7,
    'Inventory & Sales Management System',
    0,
    1,
    'C'
);

$pdf->Ln(3);

$pdf->SetFont(
    'helvetica',
    'B',
    15
);

$pdf->Cell(
    0,
    8,
    'Sales & Inventory Report',
    0,
    1,
    'C'
);

$pdf->SetFont(
    'helvetica',
    '',
    10
);

$pdf->Cell(
    0,
    6,
    'Reporting Period: ' .
    date('d M Y', strtotime($from)) .
    ' - ' .
    date('d M Y', strtotime($to)),
    0,
    1,
    'C'
);

$pdf->Ln(7);

/*
|--------------------------------------------------------------------------
| Sales Summary
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'helvetica',
    'B',
    12
);

$pdf->Cell(
    0,
    7,
    'Sales Summary',
    0,
    1
);

$pdf->SetFont(
    'helvetica',
    '',
    10
);

$pdf->Cell(
    60,
    7,
    'Transactions',
    1
);

$pdf->Cell(
    50,
    7,
    number_format($transaction_count),
    1,
    1,
    'R'
);

$pdf->Cell(
    60,
    7,
    'Total Revenue',
    1
);

$pdf->Cell(
    50,
    7,
    'KSh ' .
    number_format(
        $total_revenue,
        2
    ),
    1,
    1,
    'R'
);

$pdf->Cell(
    60,
    7,
    'Low Stock Products',
    1
);

$pdf->Cell(
    50,
    7,
    number_format($low_stock_count),
    1,
    1,
    'R'
);

$pdf->Ln(8);

/*
|--------------------------------------------------------------------------
| Top Selling Products
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'helvetica',
    'B',
    12
);

$pdf->Cell(
    0,
    7,
    'Top Selling Products',
    0,
    1
);

$pdf->SetFont(
    'helvetica',
    'B',
    9
);

$pdf->Cell(
    65,
    7,
    'Product',
    1
);

$pdf->Cell(
    35,
    7,
    'SKU',
    1
);

$pdf->Cell(
    35,
    7,
    'Quantity Sold',
    1,
    0,
    'R'
);

$pdf->Cell(
    45,
    7,
    'Revenue',
    1,
    1,
    'R'
);

$pdf->SetFont(
    'helvetica',
    '',
    9
);

if (!empty($top_products)) {

    foreach ($top_products as $product) {

        $product_name = mb_strimwidth(
            $product['product_name'],
            0,
            32,
            '...'
        );

        $sku = mb_strimwidth(
            $product['sku'],
            0,
            18,
            '...'
        );

        $pdf->Cell(
            65,
            7,
            $product_name,
            1
        );

        $pdf->Cell(
            35,
            7,
            $sku,
            1
        );

        $pdf->Cell(
            35,
            7,
            number_format(
                (int) $product['quantity_sold']
            ),
            1,
            0,
            'R'
        );

        $pdf->Cell(
            45,
            7,
            'KSh ' .
            number_format(
                (float) $product['revenue'],
                2
            ),
            1,
            1,
            'R'
        );
    }

} else {

    $pdf->Cell(
        180,
        8,
        'No sales found for the selected date range.',
        1,
        1,
        'C'
    );
}

$pdf->Ln(8);

/*
|--------------------------------------------------------------------------
| Current Stock Report
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'helvetica',
    'B',
    12
);

$pdf->Cell(
    0,
    7,
    'Current Stock Report',
    0,
    1
);

$pdf->SetFont(
    'helvetica',
    'B',
    8
);

$pdf->Cell(
    42,
    7,
    'Product',
    1
);

$pdf->Cell(
    27,
    7,
    'SKU',
    1
);

$pdf->Cell(
    25,
    7,
    'Category',
    1
);

$pdf->Cell(
    25,
    7,
    'Supplier',
    1
);

$pdf->Cell(
    18,
    7,
    'Stock',
    1,
    0,
    'R'
);

$pdf->Cell(
    22,
    7,
    'Buying Value',
    1,
    0,
    'R'
);

$pdf->Cell(
    22,
    7,
    'Selling Value',
    1,
    1,
    'R'
);

$pdf->SetFont(
    'helvetica',
    '',
    7.5
);

foreach ($stock_report as $product) {

    $product_name = mb_strimwidth(
        $product['name'],
        0,
        22,
        '...'
    );

    $sku = mb_strimwidth(
        $product['sku'],
        0,
        14,
        '...'
    );

    $category = mb_strimwidth(
        $product['category'] ?? '-',
        0,
        13,
        '...'
    );

    $supplier = mb_strimwidth(
        $product['supplier_name'] ?? '-',
        0,
        13,
        '...'
    );

    $pdf->Cell(
        42,
        7,
        $product_name,
        1
    );

    $pdf->Cell(
        27,
        7,
        $sku,
        1
    );

    $pdf->Cell(
        25,
        7,
        $category,
        1
    );

    $pdf->Cell(
        25,
        7,
        $supplier,
        1
    );

    $pdf->Cell(
        18,
        7,
        number_format(
            (int) $product['stock_quantity']
        ),
        1,
        0,
        'R'
    );

    $pdf->Cell(
        22,
        7,
        number_format(
            (float) $product['stock_buying_value'],
            2
        ),
        1,
        0,
        'R'
    );

    $pdf->Cell(
        22,
        7,
        number_format(
            (float) $product['stock_selling_value'],
            2
        ),
        1,
        1,
        'R'
    );
}

$pdf->Ln(8);

/*
|--------------------------------------------------------------------------
| Inventory Valuation
|--------------------------------------------------------------------------
*/

$pdf->SetFont(
    'helvetica',
    'B',
    12
);

$pdf->Cell(
    0,
    7,
    'Inventory Valuation',
    0,
    1
);

$pdf->SetFont(
    'helvetica',
    '',
    10
);

$pdf->Cell(
    70,
    7,
    'Current Buying Value',
    1
);

$pdf->Cell(
    60,
    7,
    'KSh ' .
    number_format(
        $total_buying_value,
        2
    ),
    1,
    1,
    'R'
);

$pdf->Cell(
    70,
    7,
    'Current Selling Value',
    1
);

$pdf->Cell(
    60,
    7,
    'KSh ' .
    number_format(
        $total_selling_value,
        2
    ),
    1,
    1,
    'R'
);

$pdf->Cell(
    70,
    7,
    'Potential Gross Margin',
    1
);

$pdf->Cell(
    60,
    7,
    'KSh ' .
    number_format(
        $potential_margin,
        2
    ),
    1,
    1,
    'R'
);

/*
|--------------------------------------------------------------------------
| Footer Information
|--------------------------------------------------------------------------
*/

$pdf->Ln(10);

$pdf->SetFont(
    'helvetica',
    'I',
    8
);

$pdf->Cell(
    0,
    6,
    'Generated by SoftgeniousDev Inventory & Sales Management System',
    0,
    1,
    'C'
);

/*
|--------------------------------------------------------------------------
| Output PDF
|--------------------------------------------------------------------------
*/

$filename =
    'softgeniousdev_inventory_report_' .
    $from .
    '_to_' .
    $to .
    '.pdf';

$pdf->Output(
    $filename,
    'I'
);

exit;
