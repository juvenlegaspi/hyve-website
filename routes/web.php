<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminCalendarEventController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPricingRuleController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminRoomController;
use App\Http\Controllers\Admin\AdminRoomScheduleController;
use App\Http\Controllers\Admin\AdminSectionController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MemberPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/policies/{document}', function (string $document) {
    $documents = [
        'house-rules' => [
            'path' => storage_path('app/hyve-house-rules.pdf'),
            'name' => 'HYVE House Rules.pdf',
        ],
        'booking-terms' => [
            'path' => storage_path('app/hyve-booking-terms-and-conditions.pdf'),
            'name' => 'HYVE Booking Terms and Conditions.pdf',
        ],
    ];

    abort_unless(isset($documents[$document]), 404);

    $file = $documents[$document];

    abort_unless(is_file($file['path']), 404);

    return response()->file($file['path'], [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="'.$file['name'].'"',
    ]);
})->name('policies.show');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login.store');

Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('/bookings/availability', [BookingController::class, 'availability'])->name('bookings.availability');
Route::get('/bookings/unavailable-dates', [BookingController::class, 'unavailableDates'])->name('bookings.unavailable-dates');
Route::get('/bookings/room-layout', [BookingController::class, 'roomLayout'])->name('bookings.room-layout');
Route::get('/bookings/quote', [BookingController::class, 'quote'])->name('bookings.quote');
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');

Route::middleware('auth')->group(function () {
    Route::get('/my-bookings', [MemberPortalController::class, 'bookings'])->name('member.index');
    Route::get('/my-account', [MemberPortalController::class, 'account'])->name('member.account');
    Route::get('/my-account/profile', [MemberPortalController::class, 'profile'])->name('member.profile.edit');
    Route::get('/my-account/password', [MemberPortalController::class, 'password'])->name('member.password.edit');
    Route::get('/my-bookings/{bookingDetail}/reschedule', [MemberPortalController::class, 'reschedule'])->name('member.bookings.reschedule');
    Route::patch('/my-bookings/{bookingDetail}/reschedule', [MemberPortalController::class, 'submitReschedule'])->name('member.bookings.reschedule.update');
    Route::get('/my-bookings/{bookingHeader}/balance-payment', [MemberPortalController::class, 'balancePayment'])->name('member.bookings.balance-payment');
    Route::post('/my-bookings/{bookingHeader}/balance-payment', [MemberPortalController::class, 'submitBalancePayment'])->name('member.bookings.balance-payment.store');
    Route::post('/my-bookings/{bookingHeader}/cancel', [MemberPortalController::class, 'cancelBooking'])->name('member.bookings.cancel');
    Route::patch('/my-bookings/profile', [MemberPortalController::class, 'updateProfile'])->name('member.profile.update');
    Route::patch('/my-bookings/password', [MemberPortalController::class, 'updatePassword'])->name('member.password.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    Route::middleware('permission:bookings.view')->group(function () {
        Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{bookingHeader}/summary', [AdminBookingController::class, 'summary'])->name('bookings.summary');
        Route::get('/bookings/{bookingHeader}/proof', [AdminBookingController::class, 'proof'])->name('bookings.proof');
        Route::get('/bookings/feed', [AdminBookingController::class, 'bookingsFeed'])->name('bookings.feed');
        Route::get('/bookings/notifications/feed', [AdminBookingController::class, 'notificationsFeed'])->name('bookings.notifications.feed');
        Route::post('/bookings/notifications/read', [AdminBookingController::class, 'markNotificationsRead'])->name('bookings.notifications.read');
    });

    Route::middleware('permission:bookings.manage')->group(function () {
        Route::get('/bookings/create', [BookingController::class, 'adminCreate'])->name('bookings.create');
        Route::post('/bookings/create', [BookingController::class, 'adminStore'])->name('bookings.store');
        Route::post('/bookings/{bookingHeader}/approve', [AdminBookingController::class, 'approve'])->name('bookings.approve');
        Route::post('/bookings/{bookingHeader}/reject', [AdminBookingController::class, 'reject'])->name('bookings.reject');
        Route::post('/booking-details/{bookingDetail}/approve', [AdminBookingController::class, 'approveDetail'])->name('booking-details.approve');
        Route::post('/booking-details/{bookingDetail}/reject', [AdminBookingController::class, 'rejectDetail'])->name('booking-details.reject');
        Route::post('/booking-details/{bookingDetail}/start', [AdminBookingController::class, 'startDetail'])->name('booking-details.start');
        Route::post('/booking-details/{bookingDetail}/end', [AdminBookingController::class, 'endDetail'])->name('booking-details.end');
        Route::post('/booking-details/{bookingDetail}/extend', [AdminBookingController::class, 'extendDetail'])->name('booking-details.extend');
        Route::get('/booking-details/{bookingDetail}/reschedule', [AdminBookingController::class, 'reschedule'])->name('booking-details.reschedule');
        Route::post('/booking-details/{bookingDetail}/reschedule/slots', [AdminBookingController::class, 'rescheduleSlots'])->name('booking-details.reschedule.slots');
        Route::post('/booking-details/{bookingDetail}/reschedule/preview', [AdminBookingController::class, 'reschedulePreview'])->name('booking-details.reschedule.preview');
        Route::patch('/booking-details/{bookingDetail}/reschedule', [AdminBookingController::class, 'updateReschedule'])->name('booking-details.reschedule.update');
    });

    Route::get('/rooms', [AdminSectionController::class, 'show'])->defaults('section', 'rooms')->middleware('permission:rooms.view')->name('sections.rooms');
    Route::patch('/rooms/{room}', [AdminRoomController::class, 'update'])->middleware('permission:rooms.manage')->name('rooms.update');

    Route::get('/room-schedule', [AdminRoomScheduleController::class, 'index'])->middleware('permission:room_schedule.view')->name('sections.room-schedule');
    Route::post('/room-schedule', [AdminRoomScheduleController::class, 'store'])->middleware('permission:room_schedule.manage')->name('room-schedule.store');
    Route::post('/room-schedule/reset-day', [AdminRoomScheduleController::class, 'resetDay'])->middleware('permission:room_schedule.manage')->name('room-schedule.reset-day');
    Route::post('/room-schedule/reset-all', [AdminRoomScheduleController::class, 'resetAll'])->middleware('permission:room_schedule.manage')->name('room-schedule.reset-all');

    Route::get('/calendar-events', [AdminCalendarEventController::class, 'index'])->middleware('permission:calendar_events.view')->name('sections.calendar-events');
    Route::post('/calendar-events', [AdminCalendarEventController::class, 'store'])->middleware('permission:calendar_events.manage')->name('calendar-events.store');
    Route::patch('/calendar-events/{calendarEvent}', [AdminCalendarEventController::class, 'update'])->middleware('permission:calendar_events.manage')->name('calendar-events.update');
    Route::delete('/calendar-events/{calendarEvent}', [AdminCalendarEventController::class, 'destroy'])->middleware('permission:calendar_events.manage')->name('calendar-events.destroy');

    Route::get('/pricing-rules', [AdminPricingRuleController::class, 'index'])->middleware('permission:pricing_rules.view')->name('sections.pricing-rules');
    Route::patch('/pricing-rules/{rate}', [AdminPricingRuleController::class, 'update'])->middleware('permission:pricing_rules.manage')->name('pricing-rules.update');

    Route::get('/payments', [AdminPaymentController::class, 'index'])->middleware('permission:payments.view')->name('sections.payments');
    Route::get('/payments/{bookingPayment}/proof', [AdminPaymentController::class, 'proof'])->middleware('permission:payments.view')->name('payments.proof');
    Route::get('/payments/{bookingPayment}/receipt', [AdminPaymentController::class, 'receipt'])->middleware('permission:payments.view')->name('payments.receipt');
    Route::post('/payments/bookings/{bookingHeader}/discount', [AdminPaymentController::class, 'applyDiscount'])->middleware('permission:payments.manage')->name('payments.discount');
    Route::post('/payments/bookings/{bookingHeader}/record', [AdminPaymentController::class, 'record'])->middleware('permission:payments.manage')->name('payments.record');
    Route::post('/payments/{bookingPayment}/approve', [AdminPaymentController::class, 'approve'])->middleware('permission:payments.manage')->name('payments.approve');
    Route::post('/payments/{bookingPayment}/reject', [AdminPaymentController::class, 'reject'])->middleware('permission:payments.manage')->name('payments.reject');

    Route::get('/credits', [AdminSectionController::class, 'show'])->defaults('section', 'credits')->middleware('permission:credits.view')->name('sections.credits');
    Route::get('/venue-stock', [AdminSectionController::class, 'show'])->defaults('section', 'venue-stock')->middleware('permission:venue_stock.view')->name('sections.venue-stock');
    Route::get('/shop-products', [AdminSectionController::class, 'show'])->defaults('section', 'shop-products')->middleware('permission:shop_products.view')->name('sections.shop-products');
    Route::get('/reports', [AdminSectionController::class, 'show'])->defaults('section', 'reports')->middleware('permission:reports.view')->name('sections.reports');
    Route::get('/settings', [AdminSettingController::class, 'index'])->middleware('permission:settings.view')->name('sections.settings');
    Route::patch('/settings', [AdminSettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
    Route::post('/settings/test-mikrotik', [AdminSettingController::class, 'testMikrotik'])->middleware('permission:settings.manage')->name('settings.test-mikrotik');

    Route::get('/users', [AdminUserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/{user}/summary', [AdminUserController::class, 'summary'])->middleware('permission:users.view')->name('users.summary');
    Route::post('/users/admins', [AdminUserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');

    Route::get('/admin-roles', [AdminRoleController::class, 'index'])->middleware('permission:admin_roles.view')->name('sections.admin-roles');
    Route::post('/admin-roles/admins', [AdminRoleController::class, 'store'])->middleware('permission:admin_roles.manage')->name('admin-roles.store');
    Route::patch('/admin-roles/{user}', [AdminRoleController::class, 'update'])->middleware('permission:admin_roles.manage')->name('admin-roles.update');
});
