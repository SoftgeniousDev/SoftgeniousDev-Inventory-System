<?php

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../vendor/autoload.php';



/*
|--------------------------------------------------------------------------
| GET SALE ID
|--------------------------------------------------------------------------
*/

$sale_id = (int) ($_GET['id'] ?? 0);

if ($sale_id <= 0) {
    die('Invalid sale ID.');
}


/*
|--------------------------------------------------------------------------
| GET SALE INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        sales.id,
        sales.total_amount,
        sales.sale_date,

        COALESCE(
            customers.name,
            'Walk-in Customer'
        ) AS customer_name,

        customers.phone AS customer_phone,
        customers.email AS customer_email,
        customers.address AS customer_address,

        users.name AS recorded_by

    FROM sales

    LEFT JOIN customers
        ON sales.customer_id = customers.id

    INNER JOIN users
        ON sales.user_id = users.id

    WHERE sales.id = ?
");

$stmt->execute([$sale_id]);

$sale = $stmt->fetch();


if (!$sale) {
    die('Sale not found.');
}


/*
|--------------------------------------------------------------------------
| GET SALE ITEMS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        sale_items.quantity,
        sale_items.unit_price,
        sale_items.subtotal,

        products.name AS product_name,
        products.sku

    FROM sale_items

    INNER JOIN products
        ON sale_items.product_id = products.id

    WHERE sale_items.sale_id = ?

    ORDER BY sale_items.id ASC
");

$stmt->execute([$sale_id]);

$items = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CREATE PDF
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


/*
|--------------------------------------------------------------------------
| PDF SETTINGS
|--------------------------------------------------------------------------
*/

$pdf->SetCreator('SoftgeniousDev');

$pdf->SetAuthor('SoftgeniousDev');

$pdf->SetTitle(
    'Sale Receipt #' . $sale['id']
);

$pdf->SetSubject(
    'Inventory Sales Receipt'
);


/*
|--------------------------------------------------------------------------
| REMOVE DEFAULT HEADER / FOOTER
|--------------------------------------------------------------------------
*/

$pdf->setPrintHeader(false);

$pdf->setPrintFooter(false);


/*
|--------------------------------------------------------------------------
| MARGINS
|--------------------------------------------------------------------------
*/

$pdf->SetMargins(
    15,
    15,
    15
);


/*
|--------------------------------------------------------------------------
| AUTO PAGE BREAK
|--------------------------------------------------------------------------
*/

$pdf->SetAutoPageBreak(
    true,
    15
);


/*
|--------------------------------------------------------------------------
| ADD PAGE
|--------------------------------------------------------------------------
*/

$pdf->AddPage();


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

$html = '

<table width="100%" cellpadding="5">

<tr>

<td width="65%">

<h1 style="
    color:#172033;
    font-size:22px;
">
SoftgeniousDev
</h1>

<span style="
    color:#667085;
    font-size:10px;
">
Inventory & Sales Management System
</span>

</td>

<td width="35%" align="right">

<strong style="font-size:16px;">
SALE RECEIPT
</strong>

<br>

<span style="font-size:10px;">
Receipt #: ' . (int) $sale['id'] . '
</span>

</td>

</tr>

</table>

<hr>

';


/*
|--------------------------------------------------------------------------
| SALE INFORMATION
|--------------------------------------------------------------------------
*/

$html .= '

<table width="100%" cellpadding="5">

<tr>

<td width="50%">

<strong>Customer</strong><br>

' . htmlspecialchars(
    $sale['customer_name']
) . '

<br>

' . (
    !empty($sale['customer_phone'])
        ? htmlspecialchars($sale['customer_phone'])
        : ''
) . '

</td>


<td width="50%">

<strong>Sale Date</strong><br>

' . htmlspecialchars(
    $sale['sale_date']
) . '

<br><br>

<strong>Recorded By</strong><br>

' . htmlspecialchars(
    $sale['recorded_by']
) . '

</td>

</tr>

</table>

<br>

';


/*
|--------------------------------------------------------------------------
| ITEMS TABLE
|--------------------------------------------------------------------------
*/

$html .= '

<table
    width="100%"
    cellpadding="6"
    border="1"
>

<tr
    style="
        background-color:#f2f4f7;
        font-weight:bold;
    "
>

<th width="8%">
#
</th>

<th width="37%">
Product
</th>

<th width="15%">
SKU
</th>

<th width="12%">
Qty
</th>

<th width="14%">
Unit Price
</th>

<th width="14%">
Subtotal
</th>

</tr>

';


$count = 1;


foreach ($items as $item) {

    $html .= '

    <tr>

        <td align="center">

            ' . $count . '

        </td>

        <td>

            ' . htmlspecialchars(
                $item['product_name']
            ) . '

        </td>

        <td>

            ' . htmlspecialchars(
                $item['sku']
            ) . '

        </td>

        <td align="center">

            ' . (int)
                $item['quantity'] . '

        </td>

        <td align="right">

            KSh ' .
            number_format(
                (float) $item['unit_price'],
                2
            ) . '

        </td>

        <td align="right">

            KSh ' .
            number_format(
                (float) $item['subtotal'],
                2
            ) . '

        </td>

    </tr>

    ';

    $count++;
}


$html .= '

</table>

<br><br>

';


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$html .= '

<table
    width="100%"
    cellpadding="7"
>

<tr>

<td width="70%"></td>

<td
    width="30%"
    style="
        background-color:#f2f4f7;
        font-size:13px;
    "
>

<strong>
TOTAL
</strong>

<br>

<span style="font-size:16px;">

KSh ' .
number_format(
    (float) $sale['total_amount'],
    2
) . '

</span>

</td>

</tr>

</table>

<br><br>

';


/*
|--------------------------------------------------------------------------
| FOOTER MESSAGE
|--------------------------------------------------------------------------
*/

$html .= '

<hr>

<table width="100%">

<tr>

<td align="center">

<span style="
    color:#667085;
    font-size:9px;
">

Thank you for your business.

<br>

Generated by SoftgeniousDev Inventory & Sales Management System.

</span>

</td>

</tr>

</table>

';


/*
|--------------------------------------------------------------------------
| WRITE PDF
|--------------------------------------------------------------------------
*/

$pdf->writeHTML(
    $html,
    true,
    false,
    true,
    false,
    ''
);


/*
|--------------------------------------------------------------------------
| OUTPUT PDF
|--------------------------------------------------------------------------
*/

$pdf->Output(
    'sale-receipt-' . $sale['id'] . '.pdf',
    'I'
);

exit;