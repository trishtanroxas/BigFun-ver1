<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "backend/db.php";

$admin_id = $_SESSION['admin_id'];
$admin = [];
$services = [];
$categories = []; // NEW: Array to hold unique categories

if ($conn) {
    // This query is no longer needed for the header, but might be used elsewhere. Kept for now.
    $stmt = $conn->prepare("SELECT * FROM admin_signup WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $admin = $result->fetch_assoc();
    $stmt->close();

    // Fetch all services
    $services_result = $conn->query("SELECT * FROM services ORDER BY id DESC");
    if ($services_result) {
        while ($row = $services_result->fetch_assoc()) {
            $services[] = $row;
        }
    }
    
    // NEW: Fetch all unique, non-empty service categories
    $category_result = $conn->query("SELECT DISTINCT service_category FROM services WHERE service_category IS NOT NULL AND service_category != '' ORDER BY service_category ASC");
    if ($category_result) {
        while ($row = $category_result->fetch_assoc()) {
            $categories[] = $row['service_category'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Administration</title>
    <link rel="icon" type="image/png" href="images/bfun.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #802080;
            --light-pink: #FFEFFC;
            --light-gray: #f8f9fa;
            --dark-gray: #6c757d;
            --border-color: #dee2e6;
        }
        
        body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); }

        /* --- NEW: Sidebar & Mobile Header Layout --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            background-color: #fff;
            border-right: 1px solid var(--border-color);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        .mobile-header {
            display: none;
            background-color: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .menu-icon {
            font-size: 28px;
            color: var(--primary-color);
            cursor: pointer;
        }
        .sidebar .logo { text-align: center; margin-bottom: 30px; }
        .sidebar .logo img { max-height: 50px; }
        .sidebar .nav-pills .nav-link {
            color: #555;
            font-size: 15px;
            font-weight: 600;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar .nav-pills .nav-link.active,
        .sidebar .nav-pills .nav-link:hover {
            background-color: var(--light-pink);
            color: var(--primary-color);
        }
        .sidebar .logout-link { margin-top: auto; }
        
        /* --- Styles for Main Content (Unchanged) --- */
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 600; }
        .action-bar { margin-bottom: 1.5rem; gap: 1rem; }
        .search-bar { position: relative; }
        .search-bar .material-symbols-outlined { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--dark-gray); }
        .search-bar .form-control { padding-left: 2.5rem; }
        .services-table-container { background-color: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); overflow: hidden; }
        .table { margin-bottom: 0; }
        .table thead { background-color: var(--light-gray); }
        .table th { font-weight: 600; color: var(--dark-gray); text-transform: uppercase; font-size: 0.8rem; border-bottom-width: 1px; }
        .table td, .table th { padding: 1rem; vertical-align: middle; }
        .table-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .table-actions .btn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
        
        /* Highlight class removed */

        /* Styles for enhanced modal */
        .image-preview-container {
            width: 100%;
            padding-top: 100%;
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            position: relative;
            background-color: var(--light-gray);
            overflow: hidden;
        }
        .image-preview {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;
        }
        .image-preview-placeholder {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: var(--dark-gray); text-align: center;
        }
        .image-preview-placeholder .material-symbols-outlined { font-size: 48px; }

        /* --- Responsive Styles --- */
        @media(max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 1050; }
            .sidebar.offcanvas.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-header { display: flex; justify-content: space-between; align-items: center; }
        }
    </style>
</head>
<body>

    <datalist id="category-list">
        <?php foreach ($categories as $category): ?>
            <option value="<?php echo htmlspecialchars($category); ?>">
        <?php endforeach; ?>
    </datalist>

    <div class="sidebar offcanvas-lg offcanvas-start" id="sidebar">
        <div class="logo">
            <img src="images/bgfunlogo.png" alt="BigFun Logo">
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="admin-dashboard.php" class="nav-link"><span class="material-symbols-outlined">dashboard</span>Dashboard Overview</a></li>
            <li class="nav-item"><a href="admin-appointments.php" class="nav-link"><span class="material-symbols-outlined">event_note</span>Booked Appointments</a></li>
            <li class="nav-item"><a href="admin-booking-approval.php" class="nav-link"><span class="material-symbols-outlined">checklist</span>Booking Approval</a></li>
            <li class="nav-item"><a href="admin-services.php" class="nav-link active"><span class="material-symbols-outlined">design_services</span>Services Administration</a></li>
            <li class="nav-item"><a href="admin-customers.php" class="nav-link"><span class="material-symbols-outlined">group</span>Customers Management</a></li>
            <li class="nav-item"><a href="admin-manage.php" class="nav-link"><span class="material-symbols-outlined">person</span>Profile</a></li>
        </ul>

        <div class="logout-link">
            <a href="backend/logout-admin.php" class="btn btn-light w-100">Log Out</a>
        </div>
    </div>

    <header class="mobile-header">
        <img src="images/bgfunlogo.png" alt="BigFun Logo" style="height:40px;">
        <span class="material-symbols-outlined menu-icon" data-bs-toggle="offcanvas" data-bs-target="#sidebar">menu</span>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>Services Administration</h1>
            <p class="text-muted">Create, edit, and manage all your services here.</p>
        </div>

        <?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="action-bar d-flex justify-content-between align-items-center">
            <div class="search-bar flex-grow-1">
                <span class="material-symbols-outlined">search</span>
                <input type="search" class="form-control" id="serviceSearchInput" placeholder="Search by name, category, etc...">
            </div>
            <button class="btn d-flex align-items-center" style="background-color: var(--primary-color); color: white;" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                <span class="material-symbols-outlined me-1">add</span> Add New Service
            </button>
        </div>

        <div class="services-table-container">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Image</th><th>Service Name</th><th>Category</th><th>Price</th><th class="text-end">Actions</th></tr></thead>
                    <tbody id="servicesTableBody">
                        <?php if (!empty($services)): ?>
                            <?php foreach($services as $service): ?>
                            <tr id="service-row-<?php echo $service['id']; ?>">
                                <td><img src="uploads/<?php echo htmlspecialchars($service['service_image']); ?>" alt="" class="table-img"></td>
                                <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($service['service_category']); ?></td>
                                <td>AU$ <?php echo number_format($service['price'], 2); ?></td>
                                <td class="text-end table-actions">
                                    <button class="btn btn-sm btn-outline-secondary edit-btn" title="Edit Service"
                                        data-bs-toggle="modal" data-bs-target="#editServiceModal"
                                        data-id="<?php echo $service['id']; ?>" data-name="<?php echo htmlspecialchars($service['service_name']); ?>" data-category="<?php echo htmlspecialchars($service['service_category']); ?>" data-price="<?php echo $service['price']; ?>" data-description="<?php echo htmlspecialchars($service['service_description']); ?>" data-specification="<?php echo htmlspecialchars($service['service_specification']); ?>" data-additional="<?php echo htmlspecialchars($service['service_additional_info']); ?>" data-image="<?php echo htmlspecialchars($service['service_image']); ?>">
                                        <span class="material-symbols-outlined">edit</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" title="Delete Service"
                                        data-bs-toggle="modal" data-bs-target="#deleteServiceModal"
                                        data-id="<?php echo $service['id']; ?>" data-name="<?php echo htmlspecialchars($service['service_name']); ?>" data-image="<?php echo htmlspecialchars($service['service_image']); ?>">
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="no-services-row"><td colspan="5" class="text-center p-5">No services found. Click 'Add New Service' to begin.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="backend/add_service.php" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="mb-3">
                                    <label class="form-label">Service Image</label>
                                    <div class="image-preview-container mb-2">
                                        <img src="" alt="Image Preview" class="image-preview" id="add_image_preview">
                                        <div class="image-preview-placeholder" id="add_image_placeholder">
                                            <span class="material-symbols-outlined">image</span>
                                            <div>Select an image</div>
                                        </div>
                                    </div>
                                    <input type="file" class="form-control" name="service_image" onchange="previewImage(event, 'add_image_preview', 'add_image_placeholder')" required>
                                    <small class="form-text text-muted">Recommended size: 800x800px.</small>
                                </div>
                                <div class="mb-3"><label class="form-label">Service Name</label><input type="text" class="form-control" name="service_name" required></div>
                                <div class="row">
                                    <div class="col-sm-7 mb-3"><label class="form-label">Service Category</label><input type="text" class="form-control" name="service_category" placeholder="e.g., Mechanical Rides" required list="category-list"></div> <div class="col-sm-5 mb-3"><label class="form-label">Price (AU$)</label><input type="number" class="form-control" name="price" step="0.01" min="0" required></div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="service_description" rows="5"></textarea></div>
                                <div class="mb-3"><label class="form-label">Specification</label><textarea class="form-control" name="service_specification" rows="5"></textarea></div>
                                <div class="mb-3"><label class="form-label">Additional Information</label><textarea class="form-control" name="service_additional_info" rows="3"></textarea></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn" style="background-color: var(--primary-color); color: white;">Save Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editServiceModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Service</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form action="backend/update_service.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="service_id" id="edit_service_id">
                        <input type="hidden" name="current_image_filename" id="edit_current_image_filename">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="mb-3">
                                    <label class="form-label">Service Image</label>
                                    <div class="image-preview-container mb-2">
                                        <img src="" alt="Image Preview" class="image-preview" id="edit_image_preview">
                                        <div class="image-preview-placeholder" id="edit_image_placeholder" style="display: none;">
                                             <span class="material-symbols-outlined">image</span>
                                             <div>Select a new image</div>
                                        </div>
                                    </div>
                                    <input type="file" class="form-control" name="edit_service_image" onchange="previewImage(event, 'edit_image_preview', 'edit_image_placeholder')">
                                    <small class="form-text text-muted">Upload a new image to replace the current one.</small>
                                </div>
                                <div class="mb-3"><label class="form-label">Name of Service</label><input type="text" class="form-control" id="edit_service_name" name="service_name" required></div>
                                <div class="row">
                                    <div class="col-sm-7 mb-3"><label class="form-label">Service Category</label><input type="text" class="form-control" id="edit_service_category" name="service_category" required list="category-list"></div> <div class="col-sm-5 mb-3"><label class="form-label">Price (AU$)</label><input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required></div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" id="edit_service_description" name="service_description" rows="5"></textarea></div>
                                <div class="mb-3"><label class="form-label">Specification</label><textarea class="form-control" id="edit_service_specification" name="service_specification" rows="5"></textarea></div>
                                <div class="mb-3"><label class="form-label">Additional Information</label><textarea class="form-control" id="edit_service_additional_info" name="service_additional_info" rows="3"></textarea></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn" style="background-color: var(--primary-color); color: white;">Update Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteServiceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Are you sure you want to delete the service: <strong id="delete_service_name"></strong>?</p><p class="text-danger">This action cannot be undone.</p></div><div class="modal-footer"><form action="backend/delete_service.php" method="POST"><input type="hidden" name="delete_service_id" id="delete_service_id"><input type="hidden" name="delete_image_filename" id="delete_image_filename"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete Service</button></form></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Live Image Preview Function ---
        function previewImage(event, previewId, placeholderId) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById(previewId);
                const placeholder = document.getElementById(placeholderId);
                output.src = reader.result;
                output.style.display = 'block';
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            };
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('serviceSearchInput');
            const servicesTableBody = document.getElementById('servicesTableBody');
            const noServicesRow = document.getElementById('no-services-row');
            
            // Quick Find variables removed

            // --- Populate Quick Find List Function Removed ---

            // --- Search Functionality ---
            searchInput.addEventListener('keyup', function() {
                const searchTerm = searchInput.value.toLowerCase();
                let visibleRows = 0;
                servicesTableBody.querySelectorAll('tr').forEach(row => {
                    if (row.id === 'no-services-row') return;
                    const rowText = row.textContent.toLowerCase();
                    if (rowText.includes(searchTerm)) {
                        row.style.display = '';
                        visibleRows++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                if (noServicesRow) {
                    noServicesRow.style.display = visibleRows === 0 ? '' : 'none';
                }
                // populateQuickFind() call removed
            });

            // --- Quick Find Click Functionality Removed ---

            // --- Modal Event Listeners ---
            document.body.addEventListener('click', function(e) {
                const button = e.target.closest('.edit-btn, .delete-btn');
                if (!button) return;

                if (button.classList.contains('edit-btn')) {
                    document.getElementById('edit_service_id').value = button.dataset.id;
                    document.getElementById('edit_service_name').value = button.dataset.name;
                    document.getElementById('edit_service_category').value = button.dataset.category;
                    document.getElementById('edit_price').value = button.dataset.price;
                    document.getElementById('edit_service_description').value = button.dataset.description;
                    document.getElementById('edit_service_specification').value = button.dataset.specification;
                    document.getElementById('edit_service_additional_info').value = button.dataset.additional;
                    document.getElementById('edit_current_image_filename').value = button.dataset.image;

                    const editPreview = document.getElementById('edit_image_preview');
                    const editPlaceholder = document.getElementById('edit_image_placeholder');
                    if (button.dataset.image) {
                        editPreview.src = 'uploads/' + button.dataset.image;
                        editPreview.style.display = 'block';
                        editPlaceholder.style.display = 'none';
                    } else {
                        editPreview.style.display = 'none';
                        editPlaceholder.style.display = 'block';
                    }
                }
                if (button.classList.contains('delete-btn')) {
                    document.getElementById('delete_service_id').value = button.dataset.id;
                    document.getElementById('delete_image_filename').value = button.dataset.image;
                    document.getElementById('delete_service_name').textContent = button.dataset.name;
                }
            });
            
            // Initial population of the list removed
        });
    </script>
</body>
</html>