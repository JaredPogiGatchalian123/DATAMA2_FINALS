<?php
include 'config/db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

// 1. Fetch live stock from Capitalized v2 Tables
$sql = "SELECT i.*, s.Current_Stock, c.Category_Name 
        FROM Inventory i 
        JOIN Stock s ON i.Item_ID = s.Item_ID 
        LEFT JOIN Categories c ON i.Category_ID = c.Category_ID
        ORDER BY i.Item_ID DESC";
$items = $pdo->query($sql);

// 2. Fetch categories for the dropdown from v2 Table
$categories_query = $pdo->query("SELECT * FROM Categories");
$categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical Inventory | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .main-wrapper { margin-left: 90px; padding: 40px; background-color: #f8f9fa; min-height: 100vh; }
        .inventory-table { width: 100%; border-collapse: separate; border-spacing: 0; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .inventory-table th { background: #2bcbba; color: white; padding: 18px; text-align: left; font-size: 0.85rem; text-transform: uppercase; }
        .inventory-table td { padding: 18px; border-bottom: 1px solid #f1f1f1; font-size: 0.95rem; color: #2d3436; }

        .modal-overlay { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); 
            z-index: 2000; align-items: center; justify-content: center; 
        }
        .modal-card { background: white; padding: 35px; border-radius: 25px; width: 450px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #636e72; font-size: 0.9rem; }
        .form-group input, .form-group select { width: 100%; padding: 14px; border: 1.5px solid #eee; border-radius: 12px; outline: none; box-sizing: border-box; }

        .btn-action { background: none; border: none; cursor: pointer; font-size: 1.1rem; transition: 0.2s; }
        .btn-deduct { color: #6c5ce7; margin-right: 8px; }
        .btn-restock { color: #f39c12; margin-right: 8px; }
        .btn-edit { color: #2bcbba; }
        .btn-delete { color: #ff7675; margin-left: 8px; }

        .btn-new-supply { 
            background: #2bcbba; color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s;
        }
        .btn-new-supply:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(43, 203, 186, 0.3); }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="color: #2d3436;"><i class="fas fa-boxes" style="color: #2bcbba;"></i> Medical Inventory</h2>
            <p style="color: #7f8c8d; font-size: 0.9rem;">Manage clinic supplies and track stock levels</p>
        </div>
        <button class="btn-new-supply" onclick="openModal('addSupplyModal')"><i class="fas fa-plus"></i> NEW SUPPLY</button>
    </div>

    <table class="inventory-table">
        <thead>
            <tr>
                <th>Item ID</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock Level</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $items->fetch()): ?>
            <tr>
                <td>#<strong><?php echo $row['Item_ID']; ?></strong></td>
                <td><?php echo htmlspecialchars($row['Item_Name']); ?></td>
                <td><span style="color: #7f8c8d; font-size: 0.8rem;"><?php echo htmlspecialchars($row['Category_Name'] ?? 'Uncategorized'); ?></span></td>
                <td>₱<?php echo number_format($row['Price_Per_Unit'], 2); ?></td>
                <td><strong><?php echo $row['Current_Stock']; ?></strong> Units</td>
                <td>
                    <button class="btn-action btn-deduct" title="Reduce Stock" onclick="openDeductModal(<?php echo $row['Item_ID']; ?>, '<?php echo htmlspecialchars($row['Item_Name']); ?>', <?php echo $row['Current_Stock']; ?>)">
                        <i class="fas fa-minus-circle"></i>
                    </button>
                    <button class="btn-action btn-restock" title="Restock Item" onclick="openRestockModal(<?php echo $row['Item_ID']; ?>, '<?php echo htmlspecialchars($row['Item_Name']); ?>')">
                        <i class="fas fa-plus-square"></i>
                    </button>
                    <button class="btn-action btn-edit" title="Edit Item" onclick="editItem(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-action btn-delete" title="Delete Item" onclick="deleteItem(<?php echo $row['Item_ID']; ?>, '<?php echo htmlspecialchars($row['Item_Name']); ?>')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div id="addSupplyModal" class="modal-overlay">
    <div class="modal-card">
        <h3><i class="fas fa-plus-circle" style="color: #2bcbba;"></i> Add New Supply</h3>
        <form action="process_add_supply.php" method="POST">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" name="item_name" required placeholder="e.g. Parvo Vaccine">
            </div>
            
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" required>
                    <option value="">-- Select Category --</option>
                    <?php if (count($categories) > 0): ?>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['Category_ID']; ?>">
                                <?php echo htmlspecialchars($cat['Category_Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No categories found in DB</option>
                    <?php endif; ?>
                </select>
            </div>

            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label>Initial Stock</label>
                    <input type="number" name="initial_stock" min="0" value="0" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Price per Unit</label>
                    <input type="number" name="price" step="0.01" required>
                </div>
            </div>
            <button type="submit" style="width:100%; background:#2bcbba; color:white; border:none; padding:15px; border-radius:12px; font-weight:700; cursor:pointer;">SAVE ITEM</button>
            <button type="button" onclick="closeModal('addSupplyModal')" style="width:100%; border:none; padding:10px; margin-top:10px; cursor:pointer; background:none; color:#7f8c8d;">Cancel</button>
        </form>
    </div>
</div>

<div id="restockModal" class="modal-overlay">
    <div class="modal-card">
        <h3><i class="fas fa-plus-square" style="color: #f39c12;"></i> Restock Item</h3>
        <p id="restock_item_display" style="color: #7f8c8d; margin-bottom: 20px; font-weight:600;"></p>
        <form action="process_restock_stock.php" method="POST">
            <input type="hidden" name="item_id" id="restock_item_id">
            <div class="form-group">
                <label>Quantity to Add</label>
                <input type="number" name="add_qty" min="1" required>
            </div>
            <button type="submit" style="width:100%; background:#f39c12; color:white; border:none; padding:15px; border-radius:12px; font-weight:700; cursor:pointer;">CONFIRM RESTOCK</button>
            <button type="button" onclick="closeModal('restockModal')" style="width:100%; border:none; padding:10px; margin-top:10px; cursor:pointer; background:none; color:#7f8c8d;">Cancel</button>
        </form>
    </div>
</div>

<div id="deductModal" class="modal-overlay">
    <div class="modal-card">
        <h3><i class="fas fa-minus-circle" style="color: #6c5ce7;"></i> Reduce Stock</h3>
        <p id="deduct_item_display" style="color: #7f8c8d; margin-bottom: 20px; font-weight:600;"></p>
        <form action="process_deduct_stock.php" method="POST">
            <input type="hidden" name="item_id" id="deduct_item_id">
            <div class="form-group">
                <label>Quantity to Reduce</label>
                <input type="number" name="reduce_qty" id="reduce_input" min="1" required>
            </div>
            <button type="submit" style="width:100%; background:#6c5ce7; color:white; border:none; padding:15px; border-radius:12px; font-weight:700; cursor:pointer;">CONFIRM DEDUCTION</button>
            <button type="button" onclick="closeModal('deductModal')" style="width:100%; border:none; padding:10px; margin-top:10px; cursor:pointer; background:none; color:#7f8c8d;">Cancel</button>
        </form>
    </div>
</div>

<div id="editSupplyModal" class="modal-overlay">
    <div class="modal-card">
        <h3><i class="fas fa-edit" style="color: #2bcbba;"></i> Edit Item Details</h3>
        <form action="process_edit_supply.php" method="POST">
            <input type="hidden" name="item_id" id="edit_item_id">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" name="item_name" id="edit_item_name" required>
            </div>
            <div class="form-group">
                <label>Price per Unit</label>
                <input type="number" name="price" id="edit_price" step="0.01" required>
            </div>
            <button type="submit" style="width:100%; background:#2bcbba; color:white; border:none; padding:15px; border-radius:12px; font-weight:700; cursor:pointer;">UPDATE ITEM</button>
            <button type="button" onclick="closeModal('editSupplyModal')" style="width:100%; border:none; padding:10px; margin-top:10px; cursor:pointer; background:none; color:#7f8c8d;">Cancel</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function openDeductModal(id, name, currentStock) {
        document.getElementById('deduct_item_id').value = id;
        document.getElementById('deduct_item_display').innerText = "Item: " + name + " (In Stock: " + currentStock + ")";
        document.getElementById('reduce_input').max = currentStock; 
        openModal('deductModal');
    }

    function openRestockModal(id, name) {
        document.getElementById('restock_item_id').value = id;
        document.getElementById('restock_item_display').innerText = "Item: " + name;
        openModal('restockModal');
    }

    function editItem(item) {
        document.getElementById('edit_item_id').value = item.Item_ID;
        document.getElementById('edit_item_name').value = item.Item_Name;
        document.getElementById('edit_price').value = item.Price_Per_Unit;
        openModal('editSupplyModal');
    }

    function deleteItem(id, name) {
        Swal.fire({
            title: 'Delete ' + name + '?',
            text: "Removes from MySQL and logs to MongoDB.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#fa5252',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => { if (result.isConfirmed) { window.location.href = 'process_delete_supply.php?id=' + id; } });
    }

    // Success Notification Logic
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        Swal.fire({ title: 'Success!', text: 'Inventory action completed and logged.', icon: 'success', confirmButtonColor: '#2bcbba' });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
</script>
</body>
</html>