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
$next_id = 0;
$limit = 50;
$page_of_customers = 1;

do{
// Set the URL for the next batch of customers
$url = "https://$shop/admin/api/2023-04/customers.json?limit=$limit&since_id=$next_id";

$response = shopify_api_call($url, "GET", $access_token);
sleep(5); //Pause for 8 seconds

// Insert each customer into your customer table
$count = 0;
$result = mysqli_query($conn, "SELECT MAX(id) as max_id FROM pos_member");
$row = mysqli_fetch_assoc($result);
$max_id = $row["max_id"];
foreach ($response["customers"] as $customer) {
    // Increment counter
    $count++;
    echo "Processing customer " . $customer["email"] . " - " . $customer["first_name"] . " " . $customer["last_name"] . "<br>";


    $customer_id = $customer["id"];
    $customer_first_name = mysqli_real_escape_string($conn, $customer["first_name"]);
    $customer_last_name = mysqli_real_escape_string($conn, $customer["last_name"]);
    $customer_email = mysqli_real_escape_string($conn, $customer["email"]);
    $customer_phone = $customer["default_address"]["phone"];
    $customer_address = isset($customer["default_address"]["address1"])
        ? $customer["default_address"]["address1"]
        : "";
    //$customer_city = mysqli_real_escape_string($conn, $customer["default_address"]["city"]);
    //$customer_province = mysqli_real_escape_string($conn, $customer["default_address"]["province"]);
    $customer_postcode = isset($customer["default_address"]["zip"])
        ? $customer["default_address"]["zip"]
        : "";
    //$customer_country = mysqli_real_escape_string($conn, $customer["default_address"]["country"]);

    $max_id++;


    // Retrieve the customer's last order
    $orders_url = "https://$shop/admin/api/2023-04/orders.json?customer_id=$customer_id&status=any&limit=1&fields=id,created_at";
    $orders_response = shopify_api_call($orders_url, "GET", $access_token);
    $last_order_id = null;
    $last_order_date = null;
    if (!empty($orders_response["orders"])) {
        $last_order = $orders_response["orders"][0];
        $last_order_id = $last_order["id"];
        $last_order_date = substr($last_order["created_at"], 0, 10);
    }

    if (!empty($last_order_date)) {
        $last_order_date = mysqli_real_escape_string($conn, $last_order_date);
    } else {
        $last_order_date = '0001-01-01';
    }
    
    $sql = "INSERT INTO pos_member (id, comp_cd, member_no, name, last_name, address, phone, email, id_memlevel, status_cd, last_sale, gst_id, id_memtype)
            VALUES ('$max_id', '$comp_cd', '$customer_id', '$customer_first_name', '$customer_last_name', '$customer_address','$customer_phone','$customer_email', '0', 'Active', '$last_order_date', '', '1')";
    

    // Check if the customer already exists in the pos_member table
    $result = mysqli_query(
        $conn,
        "SELECT id FROM pos_member WHERE member_no = '$customer_id'"
    );

if (mysqli_num_rows($result) > 0) {
    // Update existing record
    $row = mysqli_fetch_assoc($result);
    $id = $row["id"];
    mysqli_query($conn, "UPDATE pos_member SET first_name = '$customer_first_name', last_name = '$customer_last_name', phone = '$customer_phone' WHERE id = $id");
} else {
    // Insert new record
    mysqli_query($conn, $sql);
}
}

$next_id = end($response["customers"])["id"];
$page_of_customers++;
echo "Retrieved page $page_of_customers of customers<br>";
}
while (count($response["customers"]) == $limit);


// Close the database connection
mysqli_close($conn);
//echo "Processed $customer products.";
echo "Customer data synchronization is complete.";
die();



?>