<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OltController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\VlanController;
use App\Http\Controllers\IpPoolController;
use App\Http\Controllers\NttnLinkController;
use App\Http\Controllers\SupportContactController;
use App\Http\Controllers\SwitchController;
use App\Http\Controllers\SwitchEventController;
use App\Http\Controllers\NocAlertSettingController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\DutyShiftController;
use App\Http\Controllers\Attendance\EmployeeController;
use App\Http\Controllers\Attendance\LeaveController;
use App\Http\Controllers\Attendance\LeaveTypeController;
use App\Http\Controllers\Attendance\ZkDeviceController;
use App\Http\Controllers\Attendance\ZkPushController;
use App\Http\Controllers\Accounts\AccountsDashboardController;
use App\Http\Controllers\Accounts\BandwidthTypeController;
use App\Http\Controllers\Accounts\CustomerController;
use App\Http\Controllers\Accounts\InvoiceController;
use App\Http\Controllers\Accounts\ProductCategoryController;
use App\Http\Controllers\Accounts\ProductController;
use App\Http\Controllers\Accounts\TransactionCategoryController;
use App\Http\Controllers\Accounts\TransactionController;
use App\Http\Controllers\Latency\LatencyController;
use App\Http\Controllers\Latency\LatencyTargetController;

Route::redirect('/', '/dashboard');

// ZKTeco F18 ADMS push endpoints. Paths are fixed by the device firmware
// (configured on the device as "Server Address"), unauthenticated by
// protocol design, and excluded from CSRF verification in bootstrap/app.php.
Route::any('/iclock/cdata', [ZkPushController::class, 'cdata']);
Route::any('/iclock/getrequest', [ZkPushController::class, 'getrequest']);
Route::any('/iclock/devicecmd', [ZkPushController::class, 'devicecmd']);
Route::any('/iclock/fdata', [ZkPushController::class, 'fdata']);

// Users now lives under the Settings module (Settings > Users in the sidebar).
Route::middleware(['auth', 'can:access-settings-users'])->group(function () {
    Route::resource('users', UserController::class);
});

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard',
        [DashboardController::class,'index']
    )->name('dashboard');

    Route::get('/profile',
        [ProfileController::class,'edit']
    )->name('profile.edit');

    Route::patch('/profile',
        [ProfileController::class,'update']
    )->name('profile.update');

    Route::delete('/profile',
        [ProfileController::class,'destroy']
    )->name('profile.destroy');

});

Route::middleware(['auth', 'can:access-olt'])->group(function () {

    Route::get('/olt/dashboard',
        [OltController::class,'dashboard']
    )->name('olt.dashboard')->middleware('can:access-olt-dashboard');

    Route::resource('olt', OltController::class)
        ->middleware('can:access-olt-manage');

    Route::get('/olt/{olt}/ping',
        [OltController::class,'ping']
    )->name('olts.ping')->middleware('can:access-olt-manage');

    Route::get('/olt/{olt}/web',
        [OltController::class,'web']
    )->name('olt.web')->middleware('can:access-olt-manage');

    Route::resource('zones', ZoneController::class)
        ->except(['show'])
        ->middleware('can:access-olt-zones');

    Route::resource('vlans', VlanController::class)
        ->except(['show'])
        ->middleware('can:access-olt-vlans');

    Route::resource('ip-pools', IpPoolController::class)
        ->except(['show'])
        ->middleware('can:access-olt-ip');

    Route::resource('nttn-links', NttnLinkController::class)
        ->middleware('can:access-olt-nttn');

    Route::resource('support-contacts', SupportContactController::class)
        ->except(['show'])
        ->middleware('can:access-olt-support');

    Route::get('switch-events', [SwitchEventController::class, 'index'])
        ->name('switch-events.index')->middleware('can:access-olt-switches');

    Route::resource('switches', SwitchController::class)
        ->middleware('can:access-olt-switches');

    Route::post('switches/{switch}/poll', [SwitchController::class, 'poll'])
        ->name('switches.poll')->middleware('can:access-olt-switches');

    Route::patch('switches/{switch}/ports/{port}/notify', [SwitchController::class, 'togglePortNotify'])
        ->name('switches.ports.notify')->middleware('can:access-olt-switches');

});

Route::middleware(['auth', 'can:access-settings-telegram'])->prefix('settings')->name('settings.')->group(function () {

    Route::get('telegram', [NocAlertSettingController::class, 'edit'])->name('telegram');
    Route::put('telegram', [NocAlertSettingController::class, 'update'])->name('telegram.update');
    Route::post('telegram/test', [NocAlertSettingController::class, 'test'])->name('telegram.test');

});

// Old location of the Telegram page (it used to live under NOC).
Route::redirect('/noc-alerts', '/settings/telegram');

Route::middleware(['auth', 'can:access-settings-general'])->group(function () {

    Route::get('/settings', function () {
        return view('settings');
    })->name('settings');

});

Route::middleware(['auth', 'can:access-attendance'])->prefix('attendance')->name('attendance.')->group(function () {

    Route::get('dashboard', [AttendanceController::class, 'dashboard'])
        ->name('dashboard')->middleware('can:access-attendance-dashboard');

    Route::get('report', [AttendanceController::class, 'report'])
        ->name('report')->middleware('can:access-attendance-report');
    Route::get('report/export', [AttendanceController::class, 'exportReport'])
        ->name('report.export')->middleware('can:access-attendance-report');

    Route::get('absence', [AttendanceController::class, 'absence'])
        ->name('absence')->middleware('can:access-attendance-absence');
    Route::get('absence/export', [AttendanceController::class, 'exportAbsence'])
        ->name('absence.export')->middleware('can:access-attendance-absence');

    // Lives on the Devices page, so it's gated with that submodule.
    Route::put('settings', [AttendanceController::class, 'updateSettings'])
        ->name('settings.update')->middleware('can:access-attendance-devices');

    Route::resource('devices', ZkDeviceController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:access-attendance-devices');

    Route::post('devices/{device}/fetch-data', [ZkDeviceController::class, 'fetchData'])
        ->name('devices.fetch-data')->middleware('can:access-attendance-devices');

    Route::resource('shifts', DutyShiftController::class)
        ->except(['show'])
        ->middleware('can:access-attendance-shifts');

    Route::resource('employees', EmployeeController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:access-attendance-employees');

    Route::resource('leaves', LeaveController::class)
        ->parameters(['leaves' => 'leave'])
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:access-attendance-leaves');

    Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])
        ->name('leaves.approve')->middleware('can:access-attendance-leaves');
    Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])
        ->name('leaves.reject')->middleware('can:access-attendance-leaves');

    Route::post('leave-types', [LeaveTypeController::class, 'store'])
        ->name('leave-types.store')->middleware('can:access-attendance-leaves');
    Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])
        ->name('leave-types.destroy')->middleware('can:access-attendance-leaves');

});

Route::middleware(['auth', 'can:access-accounts'])->prefix('accounts')->name('accounts.')->group(function () {

    Route::get('dashboard', [AccountsDashboardController::class, 'index'])
        ->name('dashboard')->middleware('can:access-accounts-dashboard');

    Route::resource('transactions', TransactionController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:access-accounts-transactions');

    Route::get('transactions/{transaction}/print', [TransactionController::class, 'print'])
        ->name('transactions.print')->middleware('can:access-accounts-transactions');

    Route::post('transaction-categories', [TransactionCategoryController::class, 'store'])
        ->name('transaction-categories.store')->middleware('can:access-accounts-transactions');
    Route::delete('transaction-categories/{category}', [TransactionCategoryController::class, 'destroy'])
        ->name('transaction-categories.destroy')->middleware('can:access-accounts-transactions');

    Route::resource('products', ProductController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:access-accounts-products');

    Route::post('product-categories', [ProductCategoryController::class, 'store'])
        ->name('product-categories.store')->middleware('can:access-accounts-products');
    Route::delete('product-categories/{category}', [ProductCategoryController::class, 'destroy'])
        ->name('product-categories.destroy')->middleware('can:access-accounts-products');

    Route::resource('customers', CustomerController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->middleware('can:access-accounts-customers');

    Route::get('customers/{customer}/ledger', [CustomerController::class, 'ledger'])
        ->name('customers.ledger')->middleware('can:access-accounts-customers');

    Route::get('customers/{customer}/ledger/print', [CustomerController::class, 'printLedger'])
        ->name('customers.ledger.print')->middleware('can:access-accounts-customers');

    Route::post('customers/{customer}/invoices/generate', [InvoiceController::class, 'generateForCustomer'])
        ->name('customers.invoices.generate')->middleware('can:access-accounts-invoices');

    Route::post('customers/{customer}/payments', [CustomerController::class, 'recordPayment'])
        ->name('customers.payments.store')->middleware('can:access-accounts-invoices');

    Route::post('bandwidth-types', [BandwidthTypeController::class, 'store'])
        ->name('bandwidth-types.store')->middleware('can:access-accounts-customers');
    Route::delete('bandwidth-types/{bandwidthType}', [BandwidthTypeController::class, 'destroy'])
        ->name('bandwidth-types.destroy')->middleware('can:access-accounts-customers');

    Route::get('invoices', [InvoiceController::class, 'index'])
        ->name('invoices.index')->middleware('can:access-accounts-invoices');
    Route::post('invoices/generate', [InvoiceController::class, 'generate'])
        ->name('invoices.generate')->middleware('can:access-accounts-invoices');
    Route::get('invoices/print-batch', [InvoiceController::class, 'printBatch'])
        ->name('invoices.print-batch')->middleware('can:access-accounts-invoices');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])
        ->name('invoices.print')->middleware('can:access-accounts-invoices');
    Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])
        ->name('invoices.mark-paid')->middleware('can:access-accounts-invoices');
    Route::post('invoices/{invoice}/adjustments', [InvoiceController::class, 'storeAdjustment'])
        ->name('invoices.adjustments.store')->middleware('can:access-accounts-invoices');
    Route::delete('invoices/{invoice}/adjustments/{item}', [InvoiceController::class, 'destroyAdjustment'])
        ->name('invoices.adjustments.destroy')->middleware('can:access-accounts-invoices');

    Route::put('invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'updatePayment'])
        ->name('invoices.payments.update')->middleware('can:access-accounts-payments-edit');
    Route::delete('invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'destroyPayment'])
        ->name('invoices.payments.destroy')->middleware('can:access-accounts-payments-edit');

});

Route::middleware(['auth', 'can:access-latency'])->prefix('latency')->name('latency.')->group(function () {

    Route::resource('targets', LatencyTargetController::class)
        ->parameters(['targets' => 'target'])
        ->except(['show'])
        ->middleware('can:access-latency-targets');

    Route::get('/', [LatencyController::class, 'index'])
        ->name('index')->middleware('can:access-latency-graphs');
    Route::get('{target}', [LatencyController::class, 'show'])
        ->name('show')->whereNumber('target')->middleware('can:access-latency-graphs');
    Route::get('{target}/data', [LatencyController::class, 'data'])
        ->name('data')->whereNumber('target')->middleware('can:access-latency-graphs');

});

require __DIR__.'/auth.php';
