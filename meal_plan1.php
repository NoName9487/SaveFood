<?php

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Planning - SavePlate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2 id="brand">
                <i class="fas fa-leaf"></i>
                <span class="brand-text">SavePlate</span>
            </h2>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/bit216_assignment/mainpage_aftlogin.php" class="nav-link <?php echo $current_page == 'mainpage_aftlogin.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span class="label">Home</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/add_item.php" class="nav-link <?php echo $current_page == 'add_item.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span class="label">Add Item</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/view_inventory.php" class="nav-link <?php echo $current_page == 'view_inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-archive"></i>
                    <span class="label">View Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/mydonation.php" class="nav-link <?php echo $current_page == 'inventory_interface.php' ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i>
                    <span class="label">My Donations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/claimed_donation.php" class="nav-link <?php echo $current_page == 'available_donation.php' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span class="label">Claimed Donations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/meal_plan1.php" class="nav-link <?php echo $current_page == 'meal_plan1.php' ? 'active' : ''; ?>">
                    <i class="fas fa-utensils"></i>
                    <span class="label">Meal Plan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/food_analytics_dashboard.php" class="nav-link <?php echo $current_page == 'food_analytics_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-poll"></i>
                    <span class="label">Food Analysis</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/bit216_assignment/notification.php" class="nav-link <?php echo $current_page == 'notification.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span class="label">Notifications</span>
                    <span class="notif-dot" id="sidebar-notification-count" style="display: none;">0</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Inventory Sidebar (Right) -->
        <div id="inventorySidebar" class="inventory-sidebar">
            <div class="inv-header">
                <div class="inv-title"><i class="fas fa-warehouse"></i> Inventory</div>
                <button class="close-inv" type="button" onclick="toggleInventorySidebar()">&times;</button>
            </div>
            <input id="invSearch" class="inv-search" type="text" placeholder="Search items..." oninput="filterInventoryItems(this.value)">
            <div class="inv-list" id="invList">
                <?php if (empty($inventory_items)): ?>
                    <div style="color:#6B7280; font-size: 14px;">No items in inventory.</div>
                <?php else: ?>
                    <?php foreach ($inventory_items as $item): ?>
                        <?php 
                            $reserved = (float)($item['reserved_quantity'] ?? 0);
                            $available = max(0, (float)$item['quantity'] - $reserved);
                        ?>
                        <div class="inv-item" data-name="<?php echo htmlspecialchars(strtolower($item['item_name'])); ?>">
                            <div class="meta">
                                <div class="name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="sub">Available: <?php echo $available; ?><?php echo $reserved > 0 ? ' • Reserved: ' . $reserved : ''; ?><?php echo !empty($item['expiry_date']) ? ' • Exp: ' . htmlspecialchars($item['expiry_date']) : ''; ?></div>
                            </div>
                            <button class="add-btn" type="button" onclick="addFromSidebar(<?php echo (int)$item['id']; ?>, '<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>', <?php echo (float)$available; ?>)" <?php echo $available <= 0 ? 'disabled' : ''; ?>>Add</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Meal Plan</h1>
                    <p class="page-subtitle">Plan your weekly meals using your current inventory to reduce waste and save money</p>
                </div>
                <div class="header-right">
                    <div class="user-menu">
                        <button class="user-btn" id="userBtn">
                            <?php echo htmlspecialchars(explode(' ', $userData['username'] ?? 'User')[0]); ?> 
                            <i class="fas fa-chevron-down"></i>
                        </button>

                        <div class="user-dropdown" id="userDropdown">
                            <div class="user-profile">
                                <div class="name"><?php echo htmlspecialchars($userData['username'] ?? 'User'); ?></div>
                                <div class="email"><?php echo htmlspecialchars($userData['email'] ?? 'No email available'); ?></div>
                            </div>
                            <hr>
                            <a href="javascript:void(0)" class="menu-item" onclick="openProfileModal()">
                                <i class="fas fa-user"></i> Profile
                            </a>
                            <a href="javascript:void(0)" class="menu-item" onclick="openSettingsModal()"><i class="fas fa-cog"></i> Settings</a>
                            <form method="POST" action="">
                                <button type="submit" name="logout" class="menu-item logout-btn">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                </div>
            </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Weekly Calendar -->
        <div class="calendar-container">
            <div class="calendar-header">
                <h2 class="calendar-title">
                    <i class="fas fa-calendar-week"></i>
                    Weekly Meal Plan
                </h2>
                <div class="calendar-actions">
                     
                    <?php if (!empty($meal_plans)): ?>
                    <form method="POST" style="display: inline-block; margin-right: 15px;">
                        <button type="submit" name="reset_meal_plans" class="btn btn-danger" 
                                onclick="return confirm('Reset plans? This will delete all plans, release reserved ingredients, and remove reminders. This action cannot be undone.')">
                            <i class="fas fa-trash-alt"></i> Reset Plans
                        </button>
                    </form>
                    <?php endif; ?>
                    <div class="week-navigation">
                        <button class="nav-btn" onclick="changeWeek(-1)">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <span id="week-range"><?php echo $week_dates[0]->format('M j') . ' - ' . $week_dates[6]->format('M j, Y'); ?></span>
                        <button class="nav-btn" onclick="changeWeek(1)">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="week-grid">
                <?php 
                $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $meal_types = ['breakfast', 'lunch', 'dinner'];
                $today = date('Y-m-d');
                
                foreach ($week_dates as $index => $date): 
                    $is_today = $date->format('Y-m-d') === $today;
                    $is_past = $date->format('Y-m-d') < $today;
                    $day_name = $day_names[$index];
                    $day_key = strtolower($day_name);
                ?>
                <div class="day-card <?php echo $is_today ? 'today' : ''; ?>" data-day="<?php echo $day_key; ?>">
                    <div class="day-name"><?php echo $day_name; ?></div>
                    <div class="day-date"><?php echo $date->format('M j'); ?></div>
                    <div class="meal-slots">
                        <?php foreach ($meal_types as $meal_type): ?>
                            <?php 
                            $meal_data = $meal_plans[$day_key][$meal_type] ?? null;
                            $meal_name = $meal_data['meal_name'] ?? 'Add ' . ucfirst($meal_type);
                            $status_class = $meal_data ? 'confirmed locked' : 'empty';
                            ?>
                            <div class="meal-slot <?php echo $meal_type . ' ' . $status_class; ?>" 
                                 <?php if (!$meal_data && !$is_past): ?>
                                 onclick="openMealModal('<?php echo $day_key; ?>', '<?php echo $meal_type; ?>', '<?php echo htmlspecialchars($meal_name); ?>', '<?php echo htmlspecialchars($meal_data['ingredients'] ?? ''); ?>', '<?php echo htmlspecialchars($meal_data['notes'] ?? ''); ?>')"
                                 <?php else: ?>
                                 title="<?php echo $is_past ? 'You cannot add a meal for a past day.' : 'This slot is already planned. Reset plans to change it.'; ?>"
                                 style="cursor: not-allowed; opacity: 0.6;"
                                 <?php endif; ?>
                            >
                                <span class="meal-name"><?php echo htmlspecialchars($meal_name); ?></span>
                                <?php if ($meal_data): ?>
                                    <i class="fas fa-lock status-icon confirmed" aria-hidden="true"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Meal Planning Modal -->
    <div class="modal" id="mealModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Plan Your Meal</h3>
                <button class="close-modal" onclick="closeMealModal()">&times;</button>
            </div>
            <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="day" id="modal-day">
                <input type="hidden" name="meal_type" id="modal-meal-type">
                <input type="hidden" name="save_meal_plan" value="1">
                
                <div class="form-group">
                    <label class="form-label" for="meal-type-display">Meal Type</label>
                    <input type="text" class="form-input" id="meal-type-display" readonly style="background-color: #f8f9fa; color: #6c757d; font-weight: bold;">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="meal-name">Meal Name</label>
                    <input type="text" class="form-input" id="meal-name" name="meal_name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="ingredients">Ingredients (select from your inventory)</label>
                    <div id="ingredient-selection">
                        <div class="ingredient-dropdown-container">
                            <select class="form-input" id="ingredient-dropdown" onchange="addIngredientToList()">
                                <option value="">Select an ingredient...</option>
                                <?php if (empty($inventory_items)): ?>
                                    <option value="" disabled>No items in inventory. Add items first!</option>
                                <?php else: ?>
                                    <?php foreach ($inventory_items as $item): ?>
                                    <?php $reserved = (float)($item['reserved_quantity'] ?? 0); $available = max(0, (float)$item["quantity"] - $reserved); ?>
                                    <option value="<?php echo $item['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                            data-quantity="<?php echo $item['quantity']; ?>" 
                                            data-available="<?php echo $available; ?>"
                                            <?php echo $available <= 0 ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($item['item_name']); ?> 
                                        (Available: <?php echo $available; ?><?php echo $reserved > 0 ? ', Reserved: ' . $reserved : ''; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        
                        <div class="selected-ingredients-list" id="selected-ingredients-list" style="margin-top: 15px;">
                            <h4 style="margin-bottom: 10px; color: #333;">Selected Ingredients:</h4>
                            <div id="ingredients-container">
                                <!-- Selected ingredients will be added here -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Move and wrap the generic recipes table in a toggle-based div, hidden by default -->
                <div id="recipes-toggle-section" style="display:none; margin-bottom: 20px;">
                    <div class="table-container" style="margin-top: 10px;">
                        <h3 style="margin: 10px 0 16px 4px; color: #2C3E50;"><i class="fas fa-utensils"></i> Suggested Generic Recipes</h3>
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Meal Name</th>
                                    <th>Ingredients</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($generic_recipes as $rec): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($rec['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(implode(', ', $rec['ingredients'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-textarea" id="notes" name="notes" placeholder="Any additional notes or cooking instructions..."></textarea>
                </div>
                
                <div class="form-actions" style="display: flex; align-items: center; gap: 12px;">
                    <!-- Utensils icon to toggle generic recipes (now left of Cancel) -->
                    <button type="button" class="btn" style="padding: 6px 10px; background: none; color: #4a5568; font-size: 1.3em;" onclick="toggleRecipes()" title="Show suggested recipes">
                        <i class="fas fa-utensils"></i>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeMealModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm and Plan</button>
                </div>
            </form>
            </div>
        </div>
    </div>

        <!-- Saved Meal Plans -->
        <div class="saved-plans-container">
            <div class="saved-plans-header">
                <h3 class="saved-plans-title">
                    <i class="fas fa-calendar-check"></i>
                    Your Saved Meal Plans
                </h3>
                <div class="plans-controls">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="mealPlanSearch" placeholder="Search meal plans..." onkeyup="filterMealPlans()">
                    </div>
                    <div class="filter-dropdown">
                        <select id="mealTypeFilter" onchange="filterMealPlans()">
                            <option value="">All Meal Types</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="plans-tabs">
                <button class="tab-btn active" onclick="showTab('confirmed')">Meal Plans</button>
            </div>
            
            <!-- Confirmed Plans Tab -->
            <div id="confirmed-tab" class="tab-content active saved-plans-scroll">
                <?php
                $confirmed_plans = [];
                foreach ($meal_plans as $day_plans) {
                    foreach ($day_plans as $meal) {
                        if ($meal['status'] === 'confirmed') {
                            $confirmed_plans[] = $meal;
                        }
                    }
                }
                ?>
                <?php if (empty($confirmed_plans)): ?>
                    <div class="no-plans">
                        <i class="fas fa-check-circle"></i>
                        <p>No confirmed meal plans yet. Confirm your draft plans to see them here!</p>
                    </div>
                <?php else: ?>
                    <div class="plans-list" id="plansList">
                        <?php foreach ($confirmed_plans as $plan): ?>
                            <?php
                            // Fetch linked inventory ingredients for this plan
                            try {
                                $ingStmt = $pdo->prepare("SELECT fi.item_name, mpi.quantity_required FROM meal_plan_ingredients mpi JOIN food_inventory fi ON mpi.inventory_item_id = fi.id WHERE mpi.meal_plan_id = ? ORDER BY fi.item_name ASC");
                                $ingStmt->execute([$plan['id']]);
                                $linkedIngredients = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                $linkedIngredients = [];
                            }
                            ?>
                            <?php
                            // Build searchable text including ingredients
                            $searchableText = strtolower($plan['meal_name'] . ' ' . $plan['day_of_week'] . ' ' . $plan['meal_type']);
                            if (!empty($linkedIngredients)) {
                                foreach ($linkedIngredients as $li) {
                                    $searchableText .= ' ' . strtolower($li['item_name']);
                                }
                            } elseif ($plan['ingredients']) {
                                $searchableText .= ' ' . strtolower($plan['ingredients']);
                            }
                            if ($plan['notes']) {
                                $searchableText .= ' ' . strtolower($plan['notes']);
                            }
                            ?>
                            <div class="plan-card confirmed" data-meal-type="<?php echo strtolower($plan['meal_type']); ?>" data-search-text="<?php echo htmlspecialchars($searchableText); ?>">
                                <div class="plan-card-header">
                                    <div class="plan-title-section">
                                        <h4 class="plan-title"><?php echo htmlspecialchars($plan['meal_name']); ?></h4>
                                        <div class="plan-meta">
                                            <span class="plan-day"><?php echo ucfirst($plan['day_of_week']); ?></span>
                                            <span class="plan-type"><?php echo ucfirst($plan['meal_type']); ?></span>
                                        </div>
                                    </div>
                                    <div class="plan-status-badge">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Confirmed</span>
                                    </div>
                                </div>
                                
                                <div class="plan-card-body">
                                    <?php if (!empty($linkedIngredients)): ?>
                                        <div class="ingredients-section">
                                            <h5><i class="fas fa-list-ul"></i> Ingredients Used</h5>
                                            <div class="ingredients-list">
                                                <?php foreach ($linkedIngredients as $li): ?>
                                                    <span class="ingredient-tag">
                                                        <i class="fas fa-circle"></i>
                                                        <?php echo htmlspecialchars($li['item_name']) . ' × ' . number_format((float)$li['quantity_required'], 0, '.', ''); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php elseif ($plan['ingredients']): ?>
                                        <div class="ingredients-section">
                                            <h5><i class="fas fa-list-ul"></i> Ingredients</h5>
                                            <p class="ingredients-text"><?php echo htmlspecialchars($plan['ingredients']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($plan['notes']): ?>
                                        <div class="notes-section">
                                            <h5><i class="fas fa-sticky-note"></i> Notes</h5>
                                            <p class="notes-text"><?php echo htmlspecialchars($plan['notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current Inventory -->
        <div class="inventory-container">
            <div class="inventory-header">
                <h2 class="inventory-title"><i class="fas fa-list"></i> Your Available Ingredients </h2>
                <a href="add_item.php" class="btn btn-primary add-item-btn"><i class="fas fa-plus"></i> Add New Item</a>
            </div>
            
            <?php if (empty($inventory_items)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                    <p>No items in your inventory. <a href="/bit216_assignment/add_item.php">Add some items</a> to start meal planning!</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Reserved</th>
                                <th>Storage Location</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory_items as $item): ?>
                                <?php
                                    $expiry_date = new DateTime($item['expiry_date']);
                                    $today = new DateTime();
                                    $days_until_expiry = $today->diff($expiry_date)->days;
                                    $is_expired = $expiry_date < $today;
                                    $is_expiring_soon = $days_until_expiry <= 7 && !$is_expired;
                                    
                                    if ($is_expired) {
                                        $status_class = 'expired';
                                        $status_text = 'Expired';
                                    } elseif ($is_expiring_soon) {
                                        $status_class = 'expiring-soon';
                                        $status_text = 'Expiring Soon';
                                    } else {
                                        $status_class = 'good';
                                        $status_text = 'Good';
                                    }
                                ?>
                                <tr class="<?php echo $status_class; ?>">
                                    <td>
                                        <div class="item-info">
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <?php if (!empty($item['notes'])): ?>
                                                <small class="item-notes"><?php echo htmlspecialchars($item['notes']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></td>
                                    <?php 
                                        $reserved = (float)($item['reserved_quantity'] ?? 0);
                                        $total_quantity = (float)$item['quantity'];
                                        $available_quantity = max(0, $total_quantity - $reserved);
                                    ?>
                                    <td><?php echo htmlspecialchars($available_quantity); ?></td>
                                    <td><?php echo htmlspecialchars((string)($item['reserved_quantity'] ?? 0)); ?></td>
                                    <td><?php echo htmlspecialchars($item['storage_location'] ?? 'Not specified'); ?></td>
                                    <td>
                                        <span class="expiry-date <?php echo $status_class; ?>">
                                            <?php echo $expiry_date->format('M d, Y'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    </div>


    <!-- Simple Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-copyright">
                © 2014-2025 SavePlate. All rights reserved.
            </div>
            <div class="footer-social">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </footer>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> User Profile</h2>
            <span class="close-btn" onclick="closeProfileModal()">&times;</span>
            </div>
            <div class="modal-body">
            <form method="POST" id="profileForm">
                    <div class="profile-info-card">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="profile-details">
                            <h3><?php echo htmlspecialchars($userData['username']); ?></h3>
                            <p><?php echo htmlspecialchars($userData['email']); ?></p>
                        </div>
                    </div>
                    
            <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($userData['username']); ?>" readonly class="form-control">
            </div>
                    
            <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" readonly class="form-control">
            </div>
                    
            <div class="form-group">
                        <label><i class="fas fa-users"></i> Household Size</label>
                        <input type="number" name="household_size" value="<?php echo htmlspecialchars($household_size); ?>" min="1" readonly class="form-control">
            </div>
                    
            <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" readonly class="form-control"><?php echo htmlspecialchars($address); ?></textarea>
            </div>
                    
            <div class="actions">
                        <button type="button" id="editProfileBtn" class="btn btn-warning" style="background-color: #FFD700; color: #000; border: 2px solid #000;">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button type="submit" name="update_profile" id="saveProfileBtn" class="btn btn-primary" style="display:none;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" onclick="closeProfileModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Close
                        </button>
            </div>
            </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-lock"></i> Change Password</h2>
            <span class="close-btn" onclick="closeSettingsModal()">&times;</span>
            </div>
            <div class="modal-body">
            <form method="POST">
                    <div class="password-info-card">
                        <div class="password-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="password-info">
                            <h3>Security Settings</h3>
                            <p>Update your password to keep your account secure</p>
                        </div>
                    </div>
                    
            <div class="form-group">
                        <label><i class="fas fa-key"></i> Current Password</label>
                        <input type="password" name="current_password" required class="form-control" placeholder="Enter your current password">
            </div>
                    
            <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="new_password" required class="form-control" placeholder="Enter your new password">
            </div>
                    
            <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" name="confirm_password" required class="form-control" placeholder="Confirm your new password">
            </div>
                    
            <div class="actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                        <button type="button" onclick="closeSettingsModal()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
            </div>
            </form>
            </div>
        </div>
    </div>



    <script>
        document.addEventListener('DOMContentLoaded', function() {
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Desktop collapse/expand toggle via brand + persistence
        function toggleSidebarCollapse() {
            const sidebar = document.getElementById('sidebar');
            const collapsed = sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch(e) {}

            // Adjust footer margin when sidebar collapses/expands
            const footer = document.querySelector('.footer');
            if (collapsed) {
                footer.style.marginLeft = '72px';
            } else {
                footer.style.marginLeft = '280px';
            }
            
            try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch(e) {}
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !mobileToggle.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
            }
        });

        // Add some interactivity to KPI cards
        document.querySelectorAll('.kpi-card').forEach(card => {
            card.addEventListener('click', function() {
                // Add a subtle animation
                this.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    this.style.transform = 'translateY(-2px)';
                }, 150);
            });
        });

        // Restore collapsed state and wire brand click
            try {
                if (localStorage.getItem('sidebarCollapsed') === '1') {
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.add('collapsed');
                    document.body.classList.add('sidebar-collapsed');
                    
                    // Adjust footer margin when sidebar is collapsed
                    const footer = document.querySelector('.footer');
                    if (footer) {
                        footer.style.marginLeft = '72px';
                    }
                }
            } catch(e) {}
            const brand = document.getElementById('brand');
            if (brand) brand.addEventListener('click', toggleSidebarCollapse);


        // Load unread count for notif dot (shared with notification page)
            function updateSidebarNotificationCount(){
                try{
                    var formData = new FormData();
                    formData.append('action','get_unread_count');
                    formData.append('user_id', <?php echo (int)($userData['id'] ?? 0); ?>);
                    fetch('notification_handler.php',{method:'POST',body:formData})
                        .then(function(r){return r.json();})
                        .then(function(data){
                            var badge = document.getElementById('sidebar-notification-count');
                            if(!badge) return;
                            var count = (data && data.success) ? (data.unread_count||0) : 0;
                            if(count>0){
                                badge.textContent = count;
                                badge.style.display = 'flex';
                            } else {
                                badge.style.display = 'none';
                            }
                        })
                        .catch(function(){});
                }catch(e){}
            }
            updateSidebarNotificationCount();
            setInterval(updateSidebarNotificationCount,30000);

        // Toggle user dropdown
        document.getElementById("userBtn").addEventListener("click", function () {
            const dropdown = document.getElementById("userDropdown");
            dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
        });

        // Close dropdown if clicked outside
        window.addEventListener("click", function(e) {
            if (!document.getElementById("userBtn").contains(e.target) && 
                !document.getElementById("userDropdown").contains(e.target)) {
                document.getElementById("userDropdown").style.display = "none";
            }
        });
        });

        // Profile Modal Functions
        function openProfileModal() {
        document.getElementById("profileModal").style.display = "flex";
        }

        function closeProfileModal() {
        document.getElementById("profileModal").style.display = "none";
        }

        // Close when clicking outside modal
        window.onclick = function(event) {
        let modal = document.getElementById("profileModal");
        if (event.target === modal) {
            closeProfileModal();
        }
        }

        document.getElementById("editProfileBtn").addEventListener("click", function () {
        let inputs = document.querySelectorAll("#profileForm input, #profileForm textarea");

        inputs.forEach(el => {
            el.removeAttribute("readonly");
            el.style.background = "#fff"; // white when editable
        });

        document.getElementById("editProfileBtn").style.display = "none";
        document.getElementById("saveProfileBtn").style.display = "inline-block";
        });

        function openSettingsModal() {
        document.getElementById("settingsModal").style.display = "flex";
        const dd = document.getElementById('userDropdown');
        if (dd) dd.style.display = 'none';
        }
        
        function closeSettingsModal() {
        document.getElementById("settingsModal").style.display = "none";
        }

        // Meal Planning Modal Functions
        function openMealModal(day, mealType, mealName, ingredients, notes) {
            // First, clear all form fields to ensure clean state
            document.getElementById('modal-day').value = day;
            document.getElementById('modal-meal-type').value = mealType;
            document.getElementById('meal-name').value = '';
            document.getElementById('notes').value = '';
            
            // Display the meal type in the visible field
            const mealTypeDisplay = document.getElementById('meal-type-display');
            if (mealTypeDisplay) {
                const displayValue = mealType.charAt(0).toUpperCase() + mealType.slice(1);
                mealTypeDisplay.value = displayValue;
            }
            
            // If editing existing meal, populate the form
            if (mealName && mealName !== 'Add ' + mealType.charAt(0).toUpperCase() + mealType.slice(1)) {
                document.getElementById('meal-name').value = mealName;
                document.getElementById('notes').value = notes || '';
            }
            
            // Clear ingredient dropdown and selected ingredients
            const dropdown = document.getElementById('ingredient-dropdown');
            if (dropdown) {
                dropdown.selectedIndex = 0;
            }
            
            const ingredientsContainer = document.getElementById('ingredients-container');
            if (ingredientsContainer) {
                ingredientsContainer.innerHTML = '';
            }
            
            document.getElementById('mealModal').classList.add('show');
        }

        function closeMealModal() {
            const modal = document.getElementById('mealModal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                
                // Clear the form completely when closing
                document.getElementById('modal-day').value = '';
                document.getElementById('modal-meal-type').value = '';
                document.getElementById('meal-type-display').value = '';
                document.getElementById('meal-name').value = '';
                document.getElementById('notes').value = '';
                
                // Clear ingredient dropdown and selected ingredients
                const dropdown = document.getElementById('ingredient-dropdown');
                if (dropdown) {
                    dropdown.selectedIndex = 0;
                }
                
                const ingredientsContainer = document.getElementById('ingredients-container');
                if (ingredientsContainer) {
                    ingredientsContainer.innerHTML = '';
                }
            }
        }

        function addIngredientToList() {
            const dropdown = document.getElementById('ingredient-dropdown');
            const selectedOption = dropdown.options[dropdown.selectedIndex];
            
            if (selectedOption.value === '') return;
            
            const ingredientId = selectedOption.value;
            const ingredientName = selectedOption.getAttribute('data-name');
            const availableQuantity = selectedOption.getAttribute('data-available');
            
            // Check if ingredient is already added
            const existingIngredient = document.querySelector(`[data-ingredient-id="${ingredientId}"]`);
            if (existingIngredient) {
                alert('This ingredient is already added to your meal!');
                dropdown.selectedIndex = 0; // Reset dropdown
                return;
            }
            
            // Create ingredient item
            const ingredientItem = document.createElement('div');
            ingredientItem.className = 'selected-ingredient-item';
            ingredientItem.setAttribute('data-ingredient-id', ingredientId);
            ingredientItem.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px;">
                    <span style="flex: 1; font-weight: 500;">${ingredientName}</span>
                    <span style="color: #6c757d; font-size: 0.9rem;">Available: ${availableQuantity}</span>
                    <input type="number" name="ingredient_quantities[]" min="1" step="1" placeholder="1" 
                           style="width: 80px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;" 
                           max="${availableQuantity}" value="1">
                    <input type="hidden" name="selected_ingredients[]" value="${ingredientId}">
                    <button type="button" onclick="removeIngredient(this)" style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer;">×</button>
                </div>
            `;
            
            document.getElementById('ingredients-container').appendChild(ingredientItem);
            dropdown.selectedIndex = 0; // Reset dropdown
        }
        
        function removeIngredient(button) {
            button.closest('.selected-ingredient-item').remove();
        }

        function toggleInventorySidebar() {
            const el = document.getElementById('inventorySidebar');
            if (!el) return;
            el.classList.toggle('open');
            document.body.classList.toggle('inventory-open', el.classList.contains('open'));
        }

        function filterInventoryItems(query) {
            const q = (query || '').toLowerCase();
            const items = document.querySelectorAll('#invList .inv-item');
            items.forEach(it => {
                const name = it.getAttribute('data-name') || '';
                it.style.display = name.includes(q) ? 'flex' : 'none';
            });
        }

        function addFromSidebar(id, name, available) {
            if (!document.getElementById('mealModal').classList.contains('show')) {
                alert('Open a meal slot first, then add ingredients.');
                return;
            }
            const existing = document.querySelector(`[data-ingredient-id="${id}"]`);
            if (existing) {
                alert('This ingredient is already added to your meal!');
                return;
            }
            const container = document.getElementById('ingredients-container');
            if (!container) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'selected-ingredient-item';
            wrapper.setAttribute('data-ingredient-id', String(id));
            wrapper.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px;">
                    <span style="flex: 1; font-weight: 500;">${name}</span>
                    <span style="color: #6c757d; font-size: 0.9rem;">Available: ${available}</span>
                    <input type="number" name="ingredient_quantities[]" min="1" step="1" placeholder="1" 
                           style="width: 80px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;" 
                           max="${available}" value="1">
                    <input type="hidden" name="selected_ingredients[]" value="${id}">
                    <button type="button" onclick="removeIngredient(this)" style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer;">×</button>
                </div>`;
            container.appendChild(wrapper);
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const mealModal = document.getElementById('mealModal');
            if (event.target == mealModal) {
                closeMealModal();
            }
        }





        // Toggle ingredient quantity input when checkbox is checked
        function toggleIngredientQuantity(checkbox) {
            const ingredientItem = checkbox.closest('.ingredient-item');
            const quantityDiv = ingredientItem.querySelector('.ingredient-quantity');
            const quantityInput = quantityDiv.querySelector('input[type="number"]');
            const available = parseFloat(ingredientItem.getAttribute('data-available') || '0');
            
            if (checkbox.checked) {
                if (available <= 0) { checkbox.checked = false; return; }
                quantityDiv.style.display = 'flex';
                const defaultQty = Math.min(1, available);
                quantityInput.value = defaultQty;
                quantityInput.required = true;
                quantityInput.max = String(available);
                quantityInput.disabled = false;
            } else {
                quantityDiv.style.display = 'none';
                quantityInput.value = '';
                quantityInput.required = false;
                quantityInput.disabled = true;
            }
        }

        function closeMealModal() {
            // Remove quick selectors if they exist
            const quickSelectors = document.getElementById('quick-selectors');
            if (quickSelectors) {
                quickSelectors.remove();
            }
            
            document.getElementById('mealModal').classList.remove('show');
        }

        // Tab switching functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Search and filter functionality for meal plans
        function filterMealPlans() {
            const searchTerm = document.getElementById('mealPlanSearch').value.toLowerCase().trim();
            const mealTypeFilter = document.getElementById('mealTypeFilter').value.toLowerCase();
            const planCards = document.querySelectorAll('.plan-card');
            let visibleCount = 0;

            planCards.forEach(card => {
                const searchText = card.getAttribute('data-search-text') || '';
                const mealType = card.getAttribute('data-meal-type') || '';
                
                // More flexible search - check if any word in search term matches
                let matchesSearch = true;
                if (searchTerm) {
                    const searchWords = searchTerm.split(/\s+/);
                    matchesSearch = searchWords.every(word => searchText.includes(word));
                }
                
                const matchesMealType = !mealTypeFilter || mealType === mealTypeFilter;
                
                if (matchesSearch && matchesMealType) {
                    card.style.display = 'block';
                    visibleCount++;
                    // Add highlight effect for search terms
                    if (searchTerm) {
                        highlightSearchTerms(card, searchTerm);
                    }
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide no results message
            const plansList = document.getElementById('plansList');
            let noResultsMsg = document.getElementById('noResultsMessage');
            
            if (visibleCount === 0 && (searchTerm || mealTypeFilter)) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMessage';
                    noResultsMsg.className = 'no-results';
                    noResultsMsg.innerHTML = `
                        <div class="no-results-content">
                            <div class="no-results-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3 class="no-results-title">No meal plans found</h3>
                            <p class="no-results-subtitle">Try searching for:</p>
                            <ul class="no-results-suggestions">
                                <li>Meal names (e.g., "pasta", "salad")</li>
                                <li>Days (e.g., "monday", "thursday")</li>
                                <li>Meal types (e.g., "breakfast", "lunch")</li>
                                <li>Ingredients (e.g., "salmon", "chicken")</li>
                            </ul>
                        </div>
                    `;
                    plansList.appendChild(noResultsMsg);
                }
                noResultsMsg.style.display = 'block';
            } else if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }

        }

        // Highlight search terms in visible cards
        function highlightSearchTerms(card, searchTerm) {
            const searchWords = searchTerm.split(/\s+/);
            const title = card.querySelector('.plan-title');
            const ingredients = card.querySelectorAll('.ingredient-tag');
            
            // Remove existing highlights
            card.querySelectorAll('.search-highlight').forEach(el => {
                el.outerHTML = el.innerHTML;
            });
            
            // Highlight in title
            if (title) {
                searchWords.forEach(word => {
                    if (word.length > 1) {
                        const regex = new RegExp(`(${word})`, 'gi');
                        title.innerHTML = title.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                });
            }
            
            // Highlight in ingredients
            ingredients.forEach(ingredient => {
                searchWords.forEach(word => {
                    if (word.length > 1) {
                        const regex = new RegExp(`(${word})`, 'gi');
                        ingredient.innerHTML = ingredient.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                });
            });
        }



        // Week navigation
        function changeWeek(direction) {
            const url = new URL(window.location.href);
            const currentOffset = parseInt(url.searchParams.get('week_offset') || '0', 10);
            const nextOffset = currentOffset + direction;
            url.searchParams.set('week_offset', String(nextOffset));
            window.location.href = url.toString();
        }

        // Close modal when clicking outside
        document.getElementById('mealModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMealModal();
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);

        function toggleRecipes() {
            var recipesDiv = document.getElementById('recipes-toggle-section');
            if (recipesDiv.style.display === 'none' || recipesDiv.style.display === '') {
                recipesDiv.style.display = 'block';
            } else {
                recipesDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>
