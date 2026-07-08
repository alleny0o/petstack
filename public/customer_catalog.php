<?php session_start(); ?>

<?php
// Generate Mock Data (Now including Isotopes and Compounds)
$all_products = [];
$sample_isotopes = ['C-14', 'H-3', 'P-32', 'I-125', 'S-35'];
$sample_compounds = ['Glucose', 'Thymidine', 'ATP', 'Water', 'Methionine'];

for ($i = 1; $i <= 60; $i++) {
    // Picking random isotopes and compounds for the dummy data
    $iso = $sample_isotopes[array_rand($sample_isotopes)];
    $comp = $sample_compounds[array_rand($sample_compounds)];
    
    $all_products[] = [
        'id' => $i,
        'name' => "Radiolabeled $comp ($iso)",
        'sku' => 'SKU-' . str_pad($i, 4, '0', STR_PAD_LEFT),
        'isotope' => $iso,
        'compound' => $comp,
        'description' => "Standard specifications for $comp labeled with $iso. Manufactured in-house."
    ];
}

// Handle Search Requests
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_isotope = isset($_GET['isotope']) ? trim($_GET['isotope']) : '';
$search_compound = isset($_GET['compound']) ? trim($_GET['compound']) : '';

$filtered_products = $all_products;

// Only filter if at least one search field was used
if ($search_query !== '' || $search_isotope !== '' || $search_compound !== '') {
    $filtered_products = array_filter($all_products, function($product) use ($search_query, $search_isotope, $search_compound) {
        $match_general = true;
        $match_isotope = true;
        $match_compound = true;

        if ($search_query !== '') {
            $match_general = (stripos($product['name'], $search_query) !== false || stripos($product['sku'], $search_query) !== false);
        }
        if ($search_isotope !== '') {
            $match_isotope = (stripos($product['isotope'], $search_isotope) !== false);
        }
        if ($search_compound !== '') {
            $match_compound = (stripos($product['compound'], $search_compound) !== false);
        }

        // The product must match ALL the fields the user typed into
        return $match_general && $match_isotope && $match_compound;
    });
}

// Handle Pagination Logic
$items_per_page = 24;
$total_items = count($filtered_products);
$total_pages = ceil($total_items / $items_per_page);

// Get current page from URL, default to 1
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($current_page, max(1, $total_pages))); 

// Calculate offset and slice the array
$offset = ($current_page - 1) * $items_per_page;
$display_products = array_slice($filtered_products, $offset, $items_per_page);

// Helper function to build pagination URLs without losing search terms
$url_params = [];
if ($search_query) $url_params['search'] = $search_query;
if ($search_isotope) $url_params['isotope'] = $search_isotope;
if ($search_compound) $url_params['compound'] = $search_compound;
$query_string = http_build_query($url_params); // Magically turns array into &search=...&isotope=...
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php $pageTitle = 'Catalog'; $roleCss = 'customer';
    include '../src/partials/head.php'; ?>
</head>

<body>

    <div class="app-shell">
        <?php include '../src/partials/layout_customer.php'; ?>

        <main class="app-main">


            <div class="catalog-header">
                <h1>Catalog</h1>
            </div>

            <div class="order-banner">
                <strong>Notice:</strong> This is a reference index only. To place an order for any of these items, please email 
                <a href="mailto:admin@yourcompany.com?subject=Order Request">admin@yourcompany.com</a> with the SKU numbers.
            </div>

            <form class="search-container" method="GET" action="">
                <input type="text" name="search" placeholder="Search Name or SKU..." value="<?php echo htmlspecialchars($search_query); ?>">
                <input type="text" name="isotope" placeholder="Filter Isotope (e.g., C-14)" value="<?php echo htmlspecialchars($search_isotope); ?>">
                <input type="text" name="compound" placeholder="Filter Compound (e.g., Glucose)" value="<?php echo htmlspecialchars($search_compound); ?>">
                <button type="submit">Search</button>
                
                <?php if($search_query || $search_isotope || $search_compound): ?>
                    <a href="customer_catalog.php" class="clear-btn">Clear</a>
                <?php endif; ?>
            </form>

            <p style="text-align: left;">Showing <?php echo count($display_products); ?> of <?php echo $total_items; ?> products.</p>

            <div class="catalog-grid">
                <?php if(count($display_products) > 0): ?>
                    <?php foreach($display_products as $product): ?>
                        <div class="product-card">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="sku"><?php echo htmlspecialchars($product['sku']); ?></div>
                            <div style="margin-bottom: 10px; font-size: 0.9em;">
                                <strong>Isotope:</strong> <?php echo htmlspecialchars($product['isotope']); ?><br>
                                <strong>Compound:</strong> <?php echo htmlspecialchars($product['compound']); ?>
                            </div>
                            <p><?php echo htmlspecialchars($product['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="grid-column: 1 / -1; text-align: center;">No products found matching your search criteria.</p>
                <?php endif; ?>
            </div>

            <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php 
                            // Attach the specific page number to our search query string
                            $page_url = "?page=" . $i . ($query_string ? '&' . $query_string : ''); 
                        ?>
                        <a href="<?php echo $page_url; ?>" class="<?php echo $i === $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </main>

    </div>

</body>

<script src="assets/js/script.js" defer></script>

</html>
