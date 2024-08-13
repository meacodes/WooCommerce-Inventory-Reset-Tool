<?php
// Include WordPress functions and WooCommerce
require_once(dirname(__FILE__) . '/wp-load.php');

// Security check to prevent unauthorized access
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

function set_all_products_inventory_to_zero() {
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );

    $products = get_posts($args);
    $count = 0;
    $details = [];
    $detailsText = "";

    foreach ($products as $product) {
        $product_id = $product->ID;
        $wc_product = wc_get_product($product_id);

        if ($wc_product->is_type('variable')) {
            // Process each variation of variable products
            $variations = $wc_product->get_children();
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                $variation_stock = $variation->get_stock_quantity();

                // Log stock before updating
                $detailsText .= "Product Name: {$wc_product->get_name()} - Variation ID: {$variation_id} - Stock before: {$variation_stock}, ";

                // Update the variation stock to 0
                update_post_meta($variation_id, '_stock', 0);
                update_post_meta($variation_id, '_stock_status', 'outofstock');
                $updated_stock = get_post_meta($variation_id, '_stock', true);

                // Log details after updating
                $detailsText .= "Stock set to 0\n";
                $details[] = "Product Name: {$wc_product->get_name()} - Variation ID: {$variation_id} - Stock before: {$variation_stock}, Stock set to 0";
                $count++;

                // Verify stock update
                error_log("Variation ID: {$variation_id} - Stock after update: {$updated_stock}");
            }

        } else {
            // Process simple products
            $current_stock = $wc_product->get_stock_quantity();

            // Log stock before updating
            $detailsText .= "Product Name: {$wc_product->get_name()} - Stock before: {$current_stock}, ";

            // Update the product stock to 0
            update_post_meta($product_id, '_stock', 0);
            update_post_meta($product_id, '_stock_status', 'outofstock');
            $updated_stock = get_post_meta($product_id, '_stock', true);

            // Log details after updating
            $detailsText .= "Stock set to 0\n";
            $details[] = "Product Name: {$wc_product->get_name()} - Stock before: {$current_stock}, Stock set to 0";
            $count++;

            // Verify stock update
            error_log("Product ID: {$product_id} - Stock after update: {$updated_stock}");
        }
    }

    // Save the details to a text file
    $file_name = 'product_inventory_update_' . date('YmdHis') . '.txt';
    file_put_contents($file_name, $detailsText);

    return [
        'count' => $count,
        'details' => '<ul><li>' . implode('</li><li>', $details) . '</li></ul>',
        'file' => $file_name
    ];
}

$result = null;
$showResult = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = set_all_products_inventory_to_zero();
    $showResult = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Product Inventory</title>
    <style>
        body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }
        #content {
            text-align: center;
            width: 50%;
        }
        #header {
            margin-bottom: 20px;
        }
        #header img {
            max-width: 200px;
        }
        #loading {
            display: none;
            font-size: 18px;
            margin-top: 20px;
        }
        .loader {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 20px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        #result {
            display: <?php echo $showResult ? 'block' : 'none'; ?>;
            margin-top: 20px;
            text-align: left;
        }
        #summary {
            font-size: 18px;
            margin-bottom: 20px;
        }
        #details {
            display: none;
            margin-top: 20px;
            border: 1px solid #ccc;
            padding: 10px;
            background-color: #fff;
        }
        #toggleDetails {
            cursor: pointer;
            color: blue;
            text-decoration: underline;
        }
        #downloadLink {
            text-align: center;
            width: 100%;
            display: block;
            margin-top: 55px;
            margin-bottom: -30px;
        }
        #footer {
            margin-top: 40px;
            font-size: 14px;
        }
        #footer a {
            color: #000;
            text-decoration: none;
        }
        #startProcess {
            background-color: #3498db; /* Blue color */
            color: #fff; /* White text */
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-transform: uppercase;
            margin-top: 30px;
        }
        #startProcess:hover {
            background-color: #2980b9; /* Darker blue on hover */
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.2);
        }
        #startProcess:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.3);
        }
        #startProcess:active {
            background-color: #1f6f9f; /* Even darker blue when clicked */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            transform: translateY(2px);
        }
    </style>
</head>
<body>

<div id="content">
    <div id="header">
        <a href="https://www.meacodes.com" target="_blank">
            <img src="mealogo.png" alt="Meacodes Logo">
        </a>
    </div>

    <h1>Updating Product Inventory</h1>
    <p style="
    font-size: 0.9rem;
    margin-top: -15px;
    margin-bottom: 20px;">All products' inventory will be set to 0 and marked as out of stock.</p>
    <p style="background: yellow;padding: 5px;border-radius: 5px; font-size: small; display: inline;"><strong>Important:</strong> For security reasons, please remember to delete this PHP file from your server after completing the process.</p>

    <?php if ($result === null): ?>
        <form method="post" id="inventoryForm">
            <button type="submit" id="startProcess">Start Process</button>
            <div id="loading" class="loader"></div>
        </form>
    <?php else: ?>
        <div id="result">
            <div id="summary">Successfully updated <?php echo $result['count']; ?> products.</div>
            <div id="toggleDetails">Show Details</div>
            <div id="details"><?php echo $result['details']; ?></div>
            <div id="downloadLink">
                <a href="<?php echo $result['file']; ?>" download>Download Update Details</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('inventoryForm');
        const loading = document.getElementById('loading');
        const startButton = document.getElementById('startProcess');

        if (form && loading && startButton) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                loading.style.display = 'block';
                startButton.style.display = 'none';
                form.submit();
            });
        }

        const toggleDetails = document.getElementById('toggleDetails');
        const details = document.getElementById('details');
        if (toggleDetails && details) {
            toggleDetails.addEventListener('click', function() {
                if (details.style.display === 'none' || details.style.display === '') {
                    details.style.display = 'block';
                    this.textContent = 'Hide Details';
                } else {
                    details.style.display = 'none';
                    this.textContent = 'Show Details';
                }
            });
        }
    });
</script>

</body>
</html>
