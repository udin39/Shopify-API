<?php

ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);
ini_set("max_execution_time", 0);
// Log errors to a file
ini_set("log_errors", 1);
ini_set("error_log", "/uploads/error.log");

$id_func = 106;
include "security.inc";
include "dbconn2.inc";
include "dbfun2.inc";
include "security2.inc";
include "shopify_api_call.php";

// Set your Shopify store credentials
$shop = " ";
$access_token = " ";
$limit = 50;
$page_of_orders = 1;
$next_id = 0;

$member_id = 0; 

do {
// Set the URL for the orders API endpoint
$url = "https://$shop/admin/api/2023-04/orders.json?status=any&limit=$limit&since_id=$next_id";

// Call the Shopify API to retrieve orders
$response = shopify_api_call($url, "GET", $access_token);
sleep(5); // Pause for 5 sec



$count = 0;
// Loop through each order and insert it into the pos_sales and pos_sales_dtl tables
foreach ($response["orders"] as $order) {
    $count++;

    echo "Processing order: " . $order["Name"] . "<br>";


    // Get order details
    $order_id = $order["id"];
    $customer_id = $order["customer"]["id"];
    $customer_id = (int)$order["customer"]["id"]; // 64-bit integer
    $customer_email = $order["customer"]["email"];
    $customer_name = $order["customer"]["default_address"]["name"];
    $invoice_number = $order["name"];
    $delivery_order_number = $order["order_number"];
    $amount = $order["total_price"];
    $status = $order["financial_status"];
    $created_at = $order["created_at"];

    


    // Check if the customer ID exists as a member_no in the pos_member table
$sql = "SELECT id FROM pos_member WHERE member_no = '$customer_id'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    // Customer exists in pos_member, retrieve the corresponding id
    $row = mysqli_fetch_assoc($result);
    $member_id = $row["id"];
} else {
    // Customer does not exist in pos_member, set member_id to 0 (or any other default value)
    $member_id = 0;
}

    // Check if the invoice already exists in the pos_sales table
    $sql = "SELECT * FROM pos_sales WHERE invoice_no='$invoice_number'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        // Invoice already exists, update the sales ID for the line items
        $row = mysqli_fetch_assoc($result);
        $sales_id = $row['id'];
    } else {
        // Invoice does not exist, insert order information into the pos_sales table
        $sql = "INSERT INTO pos_sales (invoice_no, id_do,  created_date, date_inv, id_member)
                VALUES ('$invoice_number', '$delivery_order_number', '$created_at', '$created_at',  '$member_id')";
        if (mysqli_query($conn, $sql)) {
            $sales_id = mysqli_insert_id($conn); // Get the ID of the last inserted row
            echo "Record inserted successfully";
        } else {
            echo "Error inserting record: " . mysqli_error($conn);
        }
    }

    $final_price = 0;

    // Loop through each line item in the order and insert it into the pos_sales_dtl table
    foreach ($order["line_items"] as $line_item) {
        $final_price += $line_item["price"] * $line_item["quantity"];
        $item_code = $line_item["sku"];
        $item_name = str_replace("'", "`", $line_item["name"]); // replace space with backtick
        $quantity = $line_item["quantity"];
        $price = $line_item["price"];
        $discount = $line_item["total_discount"];
        $final_price = $price - $discount; // Calculate the final price for the line item

        // Get the item ID from the pos_item table based on the item code
        $sql = "SELECT id FROM pos_item WHERE item_cd='$item_code'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $item_id = $row['id'];

        // Get the price type ID from the pos_itemprice table based on the item ID
        $sql = "SELECT id_pricetype FROM pos_itemprice WHERE id_item='$item_id'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $price_type_id = $row['id_pricetype'];

        // Insert line item information into the pos_sales_dtl table
        $sql = "INSERT INTO pos_sales_dtl (id_sales, descs, id_pricetype, qty, unitprice, disc_amt, amount, tax_cd, id_item)
                VALUES ('$sales_id', '$item_name', '$price_type_id', '$quantity', '$price', '$discount', '$final_price', 'SR', '$item_id')";


        if (mysqli_query($conn, $sql)) {
            echo "Record inserted successfully";
        } else {
            echo "Error inserting record: " . mysqli_error($conn);
        }
    }
}
$next_id = end($response["orders"])["id"];
$page_of_orders++;
echo "Retrieved page $page_of_orders of orders<br>";
}while (count($response["orders"]) == $limit);


// Close the database connection
mysqli_close($conn);

// Output success message
echo "Orders retrieved and inserted into database.";

?>
