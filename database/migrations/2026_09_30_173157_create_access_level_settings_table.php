<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('access_level_settings', function (Blueprint $table) {
            $table->id();
            $table->string('operation_key')->unique();
            $table->string('category');
            $table->string('label');
            $table->string('description')->nullable();
            $table->boolean('enforced')->default(false);
            $table->integer('sort_order')->default(0);
            $table->integer('required_level')->default(0);
            $table->timestamps();
        });

        $now = now();

        // Mirrors Aronium's "Setting up access levels" categories (General / Sales /
        // Management / Stock). "enforced" marks operations this app actually gates;
        // the rest are stored so the Security screen stays a faithful match even
        // though those Aronium-only features (floor plans, split order, etc.) don't
        // exist here yet.
        $rows = [
            // GENERAL
            ['general', 'management', 'Management', 'Setting access level for management section', true, 1],
            ['general', 'settings', 'Settings', 'Setting access level for Settings section', false, 2],
            ['general', 'end_of_day', 'End of day', 'Setting access level for end of day action', false, 3],
            ['general', 'user_profile', 'User profile', 'Setting access level for user profile and cash out', false, 4],

            // SALES
            ['sales', 'view_all_open_orders', 'View all open orders', 'Setting access level required to view all open orders', false, 1],
            ['sales', 'void_order', 'Void order', 'Setting access level for void order action', true, 2],
            ['sales', 'void_item', 'Void item', 'Setting access level for void item action', true, 3],
            ['sales', 'lock_sale', 'Lock sale', 'Setting access level for lock sale action', false, 4],
            ['sales', 'unlock_sale', 'Unlock sale', 'Setting access level for unlock sale action', false, 5],
            ['sales', 'split_order', 'Split order', 'Setting access level for split order action', false, 6],
            ['sales', 'apply_discount', 'Apply discount', 'Setting access level for discount action', false, 7],
            ['sales', 'delete_document', 'Delete document', 'Setting access level for delete document action', false, 8],
            ['sales', 'refund', 'Refund', 'Setting access level for issuing refunds', true, 9],
            ['sales', 'view_sales_history', 'View sales history', 'Setting access level for View sales history action', true, 10],
            ['sales', 'starting_cash', 'Starting cash', 'Setting access level for Cash In / Out option', false, 11],
            ['sales', 'open_cash_drawer', 'Open cash drawer', 'Setting access level on Cash drawer button', false, 12],
            ['sales', 'zero_stock_quantity_sale', 'Zero stock quantity sale', 'Preventing unauthorized users from selling items with insufficient quantity on hand', true, 13],

            // MANAGEMENT
            ['management', 'dashboard', 'Dashboard', 'Allows user to see the dashboard', true, 1],
            ['management', 'documents', 'Documents', 'Allows user to open documents module', false, 2],
            ['management', 'products', 'Products', 'Allows user to open products module and manage products', true, 3],
            ['management', 'stock', 'Stock', 'Allows user to open stock module', true, 4],
            ['management', 'reporting', 'Reporting', 'Allows user to open reporting module and run reports', true, 5],
            ['management', 'customers_suppliers', 'Customers & suppliers', 'Allows user to open customers & suppliers module and manage them', true, 6],
            ['management', 'promotions', 'Promotions & actions', 'Allows user to open promotions module', false, 7],
            ['management', 'users_security', 'Users & security', 'Allows user to access users module and manage them', true, 8],
            ['management', 'payment_types', 'Payment types', 'Allows user to access payment types module', false, 9],
            ['management', 'countries', 'Countries', 'Allows user to access countries module', false, 10],
            ['management', 'employees', 'Employees', 'Allows user to open employees module and manage them', true, 11],
            ['management', 'backup', 'Database backup', 'Allows user to create and download database backups', true, 12],

            // STOCK
            ['stock', 'quick_inventory', 'Quick inventory', 'Allows user to perform quick inventory operation', false, 1],
            ['stock', 'view_cost_prices', 'View cost prices', 'Allows user to see cost prices in stock and product management', true, 2],
        ];

        foreach ($rows as [$category, $key, $label, $desc, $enforced, $order]) {
            DB::table('access_level_settings')->insert([
                'operation_key' => $key,
                'category' => $category,
                'label' => $label,
                'description' => $desc,
                'enforced' => $enforced,
                'sort_order' => $order,
                'required_level' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('access_level_settings');
    }
};
