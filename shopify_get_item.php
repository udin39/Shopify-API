<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 0);
// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', '/uploads/error.log'); 

$id_func=106;
include 'security.inc';
include 'dbconn2.inc';
include 'dbfun2.inc';
include 'security2.inc';
include 'shopify_api_call.php';


// Set your Shopify store credentials
$shop = " ";
$access_token = " ";
$limit = 250; // number of products to retrieve per batch
$next_id = 0;
$page_of_products = 1;

// Set the URL for the next batch of products
//$url = "https://$shop/admin/api/2023-04/products.json?limit=$limit";






do {
   // Set the URL for the next batch of products
   $url = "https://$shop/admin/api/2023-04/products.json?limit=$limit&since_id=$next_id";

   $response = shopify_api_call($url, 'GET', $access_token);

    // Insert each product into your pos_item table
    $count = 0;
    $result = mysqli_query($conn, "SELECT MAX(id) as max_id FROM pos_item");
    $row = mysqli_fetch_assoc($result);
    $max_id = $row['max_id'];
    foreach ($response['products'] as $product) {
        // Increment counter
        $count++;
        echo "Processing product " . $product['title'] . "<br>";
       
       
        
        $product_name = mysqli_real_escape_string($conn, $product['title']);
        $product_description = mysqli_real_escape_string($conn, $product_name);

        // Concatenate the product name and description
        $product_id = $product['id'];
        $product_price = $product['variants'][0]['price'];
        $product_qty = $product['variants'][0]['inventory_quantity'];
        $product_sku = $product['variants'][0]['sku'];
        $product_barcode = isset($product['variants'][0]['barcode']) ? $product['variants'][0]['barcode'] : '';
        $product_vendor = $product['vendor'];
        $product_type = $product['product_type'];
        $product_status = $product['status'];
        


        // Set Category IDs based on product vendor and type 
        if ($product_vendor == 'Bakhache Hampers') {
            $id_cat = 6;
        } elseif ($product_vendor == 'Bakhache Luxuries') {
            $id_cat = 8;
         } elseif ($product_vendor == 'WISHFUL Curated Gifting') {
                $id_cat = 66;
        } else {
            $id_cat = null;
        }
        
        // Set subcategory IDs based on product vendor and type  
        if (!empty($product_type)) {
            $id_cat2 = $product_vendor == 'Bakhache Luxuries' 
                ? ($product_type == 'Sleeve' ? 9 : ($product_type == 'Hamper' ? 10 : ($product_type == 'Sample' ? 11 : null))) 
                : ($product_vendor == 'Bakhache Hampers' 
                    ? ($product_type == 'Hamper' ? 7 : ($product_type == 'Services' ? 12 : null)) 
                    : ($product_vendor == 'WISHFUL Curated Gifting'
                        ? ($product_type == 'Hamper' ? 67 : null)
                        : null));
        } else {
            $id_cat2 = $product_vendor == 'Bakhache Luxuries' 
                ? 12 // Set to the category ID for items without product type in Bakhache Luxuries
                : ($product_vendor == 'Bakhache Hampers' 
                    ? 13 // Set to the category ID for items without product type in Bakhache Hampers
                    : ($product_vendor == 'WISHFUL Curated Gifting'
                        ? 68 // Set to the category ID for items without product type in WISHFUL Curated Gifting
                        : null));
        }
        $max_id++;

        // set pos column based on product status 
        $pos = $product_status == 'active' ? 'y' : 'n' ;

         
        $sql = "INSERT INTO pos_item (id, item_cd, product_id, id_cat, id_cat2, item_name, descs, barcode, stklevel, bom, flr_price, pos)
        VALUES ('$max_id',  '$id_cat', '$id_cat2', '$product_type', '$product_sku', '$product_id', '$product_name', '$product_description', '$product_barcode', '$product_qty', 'y', '$product_price', '$pos')
        ";
        

        // Check if the product already exists in pos_item table 
        $result = mysqli_query($conn, "SELECT id FROM pos_item WHERE product_id = '$product_id'");
        if (mysqli_num_rows($result) > 0) {
          // Update existing record
        $row = mysqli_fetch_assoc($result);
        $id = $row['id'];
        $sql = "UPDATE pos_item SET  product_id = '$product_id', item_name = '$product_name', descs = '$product_description', barcode = '$product_barcode', stklevel = '$product_qty', flr_price = '$product_price', id_cat = '$id_cat', id_cat2 = '$id_cat2', pos = '$pos' WHERE id = '$id'";
        } else {
        // Insert new record
        $max_id++;
        $sql = "INSERT INTO pos_item (id, item_cd, product_id, item_name, descs, barcode, stklevel, bom,  flr_price, pos) 
        VALUES ('$max_id', '$product_sku', '$product_id', '$product_name', '$product_description', '$product_barcode', '$product_qty', 'y', '$product_price', '$pos')";
        }

        mysqli_query($conn, $sql);



      


       // Get the pos_item ID for the current product SKU
$result = mysqli_query($conn, "SELECT id FROM pos_item WHERE item_cd = '$product_sku'");
$row = mysqli_fetch_assoc($result);
$id_item = $row['id'];

// Check if a unit price already exists for this item
$result = mysqli_query($conn, "SELECT id FROM pos_itemprice WHERE id_item = '$id_item' AND id_pricetype = '1'");
if (mysqli_num_rows($result) > 0) {
    // Update existing record
    $row = mysqli_fetch_assoc($result);
    $id_price = $row['id'];
    $sql_price = "UPDATE pos_itemprice SET unitprice = '$product_price' WHERE id = '$id_price'";
} else {
    // Insert new record
    $result = mysqli_query($conn, "SELECT MAX(id) AS max_id FROM pos_itemprice");
    $row = mysqli_fetch_assoc($result);
    $max_id = $row['max_id'] + 1;

    $sql_price = "INSERT INTO pos_itemprice (id, id_item, id_pricetype, unitprice) 
                  VALUES ('$max_id', '$id_item', '1', '$product_price')";
}

mysqli_query($conn, $sql_price);

        //$count++;
        $max_id++;
        $next_id = $product['id'];
    }
    
    echo "Processed $count products from page $page_of_products<br>";

   
    $page_of_products++;
    
  
} while (count($response['products']) == $limit);





    
// Close the database connection
mysqli_close($conn);
echo "Product import complete";
// Print out number of products processed
echo "Processed $count products.";
die();

?>
