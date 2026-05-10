<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

         // Create super admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );

        // =============================================
        // UNITS
        // =============================================
        $units = [
            ['name' => 'Kilogram',  'symbol' => 'kg'],
            ['name' => 'Gram',      'symbol' => 'gr'],
            ['name' => 'Liter',     'symbol' => 'L'],
            ['name' => 'Meter',     'symbol' => 'm'],
            ['name' => 'Pcs',       'symbol' => 'pcs'],
            ['name' => 'Box',       'symbol' => 'box'],
            ['name' => 'Lusin',     'symbol' => 'lsn'],
        ];
 
        foreach ($units as $unit) {
            Unit::firstOrCreate(['symbol' => $unit['symbol']], $unit);
        }
 
        // =============================================
        // CATEGORIES
        // =============================================
        $categories = [
            'Elektronik', 'Pakaian', 'Makanan & Minuman',
            'Peralatan Rumah', 'Bahan Bangunan', 'Otomotif',
        ];
 
        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat)],
                ['name' => $cat, 'is_active' => true]
            );
        }
 
        // =============================================
        // PAYMENT METHODS
        // =============================================
        $methods = [
            ['name' => 'Tunai',       'code' => 'cash',        'is_installment' => false, 'sort_order' => 1],
            ['name' => 'Transfer',    'code' => 'transfer',    'is_installment' => false, 'sort_order' => 2],
            ['name' => 'QRIS',        'code' => 'qris',        'is_installment' => false, 'sort_order' => 3],
            ['name' => 'Akulaku',     'code' => 'akulaku',     'is_installment' => true,  'sort_order' => 4, 'provider' => 'Akulaku'],
            ['name' => 'Home Credit', 'code' => 'home_credit', 'is_installment' => true,  'sort_order' => 5, 'provider' => 'Home Credit'],
            ['name' => 'Kredivo',     'code' => 'kredivo',     'is_installment' => true,  'sort_order' => 6, 'provider' => 'Kredivo'],
        ];
 
        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['code' => $method['code']],
                array_merge($method, ['is_active' => true])
            );
        }
 
        // =============================================
        // SAMPLE DO CUSTOMERS
        // =============================================
        $doCustomers = [
            [
                'name'             => 'PT. Maju Jaya',
                'phone'            => '08123456789',
                'address'          => 'Jl. Industri No. 1, Jakarta',
                'type'             => 'do',
                'credit_limit'     => 50000000,
                'default_discount' => [['type' => 'percent', 'value' => 5]],
            ],
            [
                'name'             => 'CV. Berkah Sejahtera',
                'phone'            => '08987654321',
                'address'          => 'Jl. Perdagangan No. 45, Surabaya',
                'type'             => 'do',
                'credit_limit'     => 25000000,
                'default_discount' => [
                    ['type' => 'percent', 'value' => 3],
                    ['type' => 'nominal', 'value' => 50000],
                ],
            ],
        ];
 
        foreach ($doCustomers as $cust) {
            Customer::firstOrCreate(
                ['name' => $cust['name'], 'type' => 'do'],
                array_merge($cust, ['is_active' => true])
            );
        }
 
        // =============================================
        // ROLES & PERMISSIONS (Spatie)
        // =============================================
        $roles = ['super_admin', 'admin', 'kasir', 'gudang', 'viewer'];
 
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
 
        $permissions = [
            // Transactions
            'view_transaction', 'create_transaction', 'edit_transaction', 'delete_transaction',
            // Payments
            'view_payment', 'create_payment',
            // Delivery
            'view_delivery', 'create_delivery', 'process_shipment',
            // Stock
            'view_stock', 'adjust_stock',
            // Products
            'view_product', 'create_product', 'edit_product', 'delete_product',
            // Customers
            'view_customer', 'create_customer', 'edit_customer',
            // Reports
            'view_report',
        ];
 
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
 
        // Assign semua permissions ke super_admin
        Role::findByName('super_admin')->syncPermissions(Permission::all());
 
        // Admin: semua kecuali delete
        Role::findByName('admin')->syncPermissions(
            Permission::whereNotIn('name', ['delete_transaction', 'delete_product'])->get()
        );
 
        // Kasir: transaksi dan pembayaran
        Role::findByName('kasir')->syncPermissions([
            'view_transaction', 'create_transaction',
            'view_payment', 'create_payment',
            'view_product', 'view_customer', 'create_customer',
        ]);
 
        // Gudang: delivery dan stok
        Role::findByName('gudang')->syncPermissions([
            'view_delivery', 'create_delivery', 'process_shipment',
            'view_stock', 'adjust_stock',
            'view_product',
        ]);
 
        $admin->assignRole('super_admin');
        echo "✅ Seeder berhasil dijalankan.\n";

        $warehouses = [
            [
                'name'       => 'Gudang Utama',
                'code'       => 'GDG-UTAMA',
                'address'    => 'Jl. Industri No. 1, Jakarta',
                'pic'        => 'Budi Santoso',
                'phone'      => '08111234567',
                'is_default' => true,
                'is_active'  => true,
                'sort_order' => 1,
            ],
            [
                'name'       => 'Toko / Showroom',
                'code'       => 'TOKO-PUSAT',
                'address'    => 'Jl. Raya Pusat No. 10, Jakarta Pusat',
                'pic'        => 'Siti Rahayu',
                'phone'      => '08119876543',
                'is_default' => false,
                'is_active'  => true,
                'sort_order' => 2,
            ],
        ];
 
        foreach ($warehouses as $data) {
            Warehouse::firstOrCreate(['code' => $data['code']], $data);
        }
 
        echo "✅ Warehouse seeder selesai: " . count($warehouses) . " gudang dibuat.\n";
    }
}
