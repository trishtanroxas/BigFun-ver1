<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

include "backend/db.php";

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM signup WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch ALL service details
$products = [];
$services_sql = "SELECT * FROM services ORDER BY service_name ASC";
$services_result = $conn->query($services_sql);
if ($services_result->num_rows > 0) {
    while ($row = $services_result->fetch_assoc()) {
        $products[$row['id']] = $row;
    }
}

// MODIFIED: Logic to handle comma-separated categories
$all_categories = [];
$categories_sql = "SELECT service_category FROM services WHERE service_category IS NOT NULL AND service_category != ''";
$categories_result = $conn->query($categories_sql);
if ($categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $cats = array_map('trim', explode(',', $row['service_category']));
        $all_categories = array_merge($all_categories, $cats);
    }
}
$unique_categories = array_unique(array_filter($all_categories));
sort($unique_categories);
$categories = array_merge(['All'], $unique_categories);


// ** NEW: Icon list for random-but-consistent assignment **
$icon_list = [
    'celebration',
    'sports_esports',
    'attractions',
    'castle',
    'fastfood',
    'dining',
    'music_note',
    'camera_alt',
    'auto_awesome',
    'cake',
    'festival',
    'sports_soccer',
    'pool',
    'flag',
    'nightlife',
    'park'
];


$filter = $_GET['category'] ?? 'All';

// MODIFIED: Filtering logic to check if a category is IN a comma-separated list
$filtered_products = ($filter === 'All')
    ? $products
    : array_filter($products, function ($p) use ($filter) {
        $product_categories = array_map('trim', explode(',', $p['service_category']));
        return in_array($filter, $product_categories);
    });

// MODIFIED: Cart count now comes from the database
$cart_count = 0;
$count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();
$cart_count = $count_result['total_items'] ?? 0;
$count_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Services Category - BigFun</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:wght@700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #802080;
            --primary-light: #FFEFFC;
            --primary-dark: #661966;
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: var(--primary-light);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 6%;
            background: var(--primary-light);
        }

        header img {
            height: 54px;
            display: block;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-right span {
            font-size: 14px;
            color: #333;
        }

        .header-right a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 600;
        }

        .btn-cart {
            padding: 10px 20px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            align-items: center;
            justify-content: center;
        }

        .menu-icon {
            display: none;
            cursor: pointer;
            font-size: 24px;
            color: var(--primary-color);
        }

        .sidebar {
            position: fixed;
            top: 0;
            right: 0;
            height: 100%;
            width: 260px;
            background: var(--primary-light);
            padding: 30px 20px;
            box-shadow: -2px 0 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            text-align: center;
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar h6 {
            font-size: 17px;
            font-weight: bold;
            text-transform: uppercase;
            color: var(--primary-color);
            margin: 25px 0 15px;
        }

        .sidebar a {
            display: block;
            margin: 12px 0;
            text-decoration: none;
            color: #555;
            padding: 8px 0;
            font-size: 15px;
        }

        .sidebar .close-btn {
            font-size: 22px;
            font-weight: bold;
            color: var(--primary-color);
            background: none;
            border: none;
            cursor: pointer;
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .sidebar .logout {
            display: block;
            margin: 30px auto 10px;
            padding: 10px;
            text-align: center;
            background: var(--primary-color);
            color: #fff;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            width: 80%;
        }

        .category-sidebar {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #ddd;
        }

        .category-sidebar .category-link {
            text-decoration: none;
            color: #333;
            font-size: 16px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: 500;
        }

        .category-sidebar .category-link:hover {
            color: var(--primary-color);
            font-weight: 600;
            background-color: var(--primary-light);
        }

        .category-sidebar .category-link.active {
            color: var(--primary-color) !important;
            font-weight: 700;
            background-color: var(--primary-light);
        }

        .category-sidebar .category-link .material-symbols-outlined {
            font-size: 22px;
            margin-right: 5px;
        }

        .product-card {
            border: 1px solid #ddd;
            border-radius: 12px;
            background-color: #fff;
            text-align: left;
            position: relative;
            color: #333;
            font-family: inter, sans-serif;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .product-card .card-img-top {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .product-card .card-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product-card .card-title {
            font-family: 'Inria Sans', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            color: #000;
        }

        .product-card .card-price {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: auto;
            /* Pushes price to bottom */
        }

        .product-card .card-footer {
            background-color: #f9f9f9;
            border-top: 1px solid #eee;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .product-card .btn-book-now {
            background: var(--primary-color);
            border: none;
            color: white;
            font-weight: 600;
            width: 100%;
        }

        .product-card .btn-book-now:hover {
            background: var(--primary-dark);
        }

        #serviceDetailModal .modal-price {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
        }

        #serviceDetailModal .modal-category-badge {
            background-color: var(--primary-color);
        }

        #serviceDetailModal .accordion-button:not(.collapsed) {
            background-color: #f8f0f8;
            color: var(--primary-color);
            box-shadow: none;
        }

        #serviceDetailModal .btn-book-modal {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
        }

        .toast-container {
            z-index: 1100;
        }

        @media(max-width:768px) {
            .header-right {
                display: none;
            }

            .menu-icon {
                display: block;
            }
        }
    </style>
</head>

<body>
    <header>
        <img src="assets/images/bgfunlogo.png" alt="BigFun Logo">
        <div class="header-right">
            <span>Hello, <?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?></span>
            <a href="profile-manage.php">Profile Management</a>
            <button class="btn-cart" onclick="window.location.href='cart.php'">Cart (<span id="cart-count"><?php echo $cart_count; ?></span>)</button>
        </div>
        <div class="menu-icon" onclick="toggleSidebar()">☰</div>
    </header>

    <div class="sidebar" id="sidebar">
        <button class="close-btn" onclick="toggleSidebar()">×</button>
        <button class="btn-cart mb-4" onclick="window.location.href='cart.php'">Cart (<span id="mobile-cart-count"><?php echo $cart_count; ?></span>)</button>
        <h6>Management</h6>
        <a href="overview.php">Overview Panel</a>
        <a href="appointments.php">My Appointments</a>
        <a href="invoices.php">Invoices & Payments</a>
        <a href="profile.php">Profile & Settings</a>
        <h6>Services Category</h6>
        <?php foreach ($categories as $category): ?>
            <a href="book-dashboard.php?category=<?php echo urlencode($category); ?>"><?php echo htmlspecialchars($category); ?></a>
        <?php endforeach; ?>
        <a href="backend/logout.php" class="logout">Log Out</a>
    </div>

    <div class="container-fluid" style="padding:20px 6%;">
        <div class="row">
            <aside class="col-md-3 d-none d-md-block">
                <div class="category-sidebar d-flex flex-column p-4" style="background:#fff; border-radius:8px; min-height:100%; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                    <h6 class="mb-3 text-uppercase fw-bold" style="color:#802080; padding-left: 15px;">Services Category</h6>

                    <?php foreach ($categories as $index => $category): ?>
                        <?php
                        $icon_name = 'grid_view'; // Default icon for 'All'
                        if ($index > 0) {
                            $icon_index = ($index - 1) % count($icon_list);
                            $icon_name = $icon_list[$icon_index];
                        }
                        ?>
                        <a href="book-dashboard.php?category=<?php echo urlencode($category); ?>"
                            class="category-link <?php echo ($filter === $category) ? 'active' : ''; ?>">
                            <span class="material-symbols-outlined"><?php echo $icon_name; ?></span>
                            <span><?php echo htmlspecialchars($category); ?></span>
                        </a>
                    <?php endforeach; ?>

                    <div class="mt-auto pt-4"><a href="backend/logout.php" class="btn w-100 fw-bold text-white" style="background:#802080; border-radius:6px;">Log Out</a></div>
                </div>
            </aside>

            <main class="col-md-9">
                <h3 class="mb-4" style="font-family: 'Inria Sans', sans-serif; font-weight: 700;"><?php echo htmlspecialchars($filter); ?></h3>
                <div class="row g-4">
                    <?php if (empty($filtered_products)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">No services found in this category.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($filtered_products as $id => $p): ?>
                            <div class="col-md-4">
                                <div class="card product-card h-100"
                                    data-bs-toggle="modal" data-bs-target="#serviceDetailModal"
                                    data-id="<?php echo $id; ?>" data-name="<?php echo htmlspecialchars($p['service_name']); ?>" data-price="<?php echo $p['price']; ?>"
                                    data-image="uploads/<?php echo htmlspecialchars($p['service_image']); ?>" data-category="<?php echo htmlspecialchars($p['service_category']); ?>"
                                    data-description="<?php echo htmlspecialchars($p['service_description']); ?>" data-specification="<?php echo htmlspecialchars($p['service_specification']); ?>"
                                    data-additional="<?php echo htmlspecialchars($p['service_additional_info']); ?>">

                                    <img src="uploads/<?php echo htmlspecialchars($p['service_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($p['service_name']); ?>">

                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($p['service_name']); ?></h5>
                                        <p class="card-price">$<?php echo number_format($p['price'], 2); ?></p>
                                    </div>

                                    <div class="card-footer">
                                        <button class="btn btn-book-now">Book Now</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="serviceDetailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Service Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="addToCartForm">
                        <div class="row">
                            <div class="col-md-6 mb-3"><img id="modalImage" src="" class="img-fluid rounded" alt="Service Image"></div>
                            <div class="col-md-6">
                                <h2 id="modalServiceName" style="font-family: 'Inria Sans', sans-serif;"></h2>
                                <p><span id="modalCategory" class="badge modal-category-badge"></span></p>
                                <p id="modalPrice" class="modal-price"></p>
                                <div class="accordion accordion-flush" id="detailsAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDesc">Description</button></h2>
                                        <div id="collapseDesc" class="accordion-collapse collapse show" data-bs-parent="#detailsAccordion">
                                            <div class="accordion-body" id="modalDescription"></div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSpec">Specification</button></h2>
                                        <div id="collapseSpec" class="accordion-collapse collapse" data-bs-parent="#detailsAccordion">
                                            <div class="accordion-body" id="modalSpecification"></div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdd">Additional</button></h2>
                                        <div id="collapseAdd" class="accordion-collapse collapse" data-bs-parent="#detailsAccordion">
                                            <div class="accordion-body" id="modalAdditional"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label for="modal_quantity_input" class="form-label fw-bold">Quantity:</label>
                                    <div class="input-group" style="width: 150px;">
                                        <button class="btn btn-outline-secondary" type="button" id="modal-quantity-minus">-</button>
                                        <input type="text" class="form-control text-center" value="1" id="modal_quantity_input" name="quantity" readonly>
                                        <button class="btn btn-outline-secondary" type="button" id="modal-quantity-plus">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="service_id" id="modalProductId" value="">
                        <div class="modal-footer border-0 mt-3">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-book-modal">Add to Cart</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <span class="material-symbols-outlined me-2 text-success">task_alt</span>
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toast-body-message">Service added to cart successfully!</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const serviceDetailModalEl = document.getElementById('serviceDetailModal');
            const serviceDetailModal = new bootstrap.Modal(serviceDetailModalEl);
            const addToCartForm = document.getElementById('addToCartForm');
            const cartCountSpan = document.getElementById('cart-count');
            const mobileCartCountSpan = document.getElementById('mobile-cart-count');
            const cartToastEl = document.getElementById('cartToast');
            const cartToast = new bootstrap.Toast(cartToastEl);
            const toastBody = document.getElementById('toast-body-message');

            const quantityInput = serviceDetailModalEl.querySelector('input[name="quantity"]');
            const plusButton = serviceDetailModalEl.querySelector('#modal-quantity-plus');
            const minusButton = serviceDetailModalEl.querySelector('#modal-quantity-minus');

            serviceDetailModalEl.addEventListener('show.bs.modal', function(event) {
                const card = event.relatedTarget;
                const service = {
                    id: card.dataset.id,
                    name: card.dataset.name,
                    price: card.dataset.price,
                    image: card.querySelector('.card-img-top').src,
                    category: card.dataset.category,
                    description: card.dataset.description,
                    specification: card.dataset.specification,
                    additional: card.dataset.additional
                };

                serviceDetailModalEl.querySelector('#modalImage').src = service.image;
                serviceDetailModalEl.querySelector('#modalServiceName').textContent = service.name;

                const firstCategory = service.category.split(',')[0].trim();
                serviceDetailModalEl.querySelector('#modalCategory').textContent = firstCategory;

                serviceDetailModalEl.querySelector('#modalPrice').textContent = '$' + parseFloat(service.price).toFixed(2);
                serviceDetailModalEl.querySelector('#modalDescription').innerHTML = service.description || 'No description available.';
                serviceDetailModalEl.querySelector('#modalSpecification').innerHTML = service.specification || 'No specifications available.';
                serviceDetailModalEl.querySelector('#modalAdditional').innerHTML = service.additional || 'No additional information available.';
                serviceDetailModalEl.querySelector('#modalProductId').value = service.id;

                // ** NEW: Logic to set quantity limits in modal **
                quantityInput.value = 1; // Always reset to 1
                if (service.name === 'Premium Mechanical Bull QLD') {
                    quantityInput.dataset.max = "2";
                    plusButton.disabled = false;
                    minusButton.disabled = false;
                } else {
                    quantityInput.dataset.max = "1";
                    plusButton.disabled = true; // Disable buttons for single items
                    minusButton.disabled = true;
                }
            });

            // --- Handle Add to Cart with JavaScript Fetch ---
            addToCartForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(addToCartForm);

                fetch('backend/add_to_cart.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            cartCountSpan.textContent = data.cart_count;
                            if (mobileCartCountSpan) mobileCartCountSpan.textContent = data.cart_count;
                            toastBody.textContent = 'Service added to cart successfully!';
                            cartToast.show();
                            serviceDetailModal.hide();
                        } else {
                            // Show the backend error message in the toast
                            toastBody.textContent = data.message;
                            cartToast.show();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred.');
                    });
            });

            // --- Handle Modal Quantity Buttons ---
            serviceDetailModalEl.addEventListener('click', function(e) {
                let currentValue = parseInt(quantityInput.value);
                const max = parseInt(quantityInput.dataset.max || 1); // Read max from data attribute

                if (e.target.id === 'modal-quantity-plus') {
                    if (currentValue < max) { // Only increment if below max
                        quantityInput.value = currentValue + 1;
                    }
                }
                if (e.target.id === 'modal-quantity-minus' && currentValue > 1) {
                    quantityInput.value = currentValue - 1;
                }
            });
        });
    </script>
</body>

</html>




