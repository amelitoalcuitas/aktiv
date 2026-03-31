<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HubMemberController;
use App\Http\Controllers\Api\HubOwnerRequestController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\SuperAdminHubOwnerRequestController;
use App\Http\Controllers\Api\HubEventController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\GuestBookingController;
use App\Http\Controllers\Api\GuestBookingTrackingController;
use App\Http\Controllers\Api\HubController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\HubRatingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\OwnerBookingController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserBookingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Register the broadcasting auth endpoint at /api/broadcasting/auth
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::get('/google/redirect', [OAuthController::class, 'redirect'])->name('api.auth.google.redirect');
    Route::get('/google/callback', [OAuthController::class, 'callback'])->name('api.auth.google.callback');
    Route::get('/google/deletion-callback', [OAuthController::class, 'deletionCallback'])->name('api.auth.google.deletion-callback');

    Route::post('/password/forgot', [PasswordResetController::class, 'forgotPassword'])->name('api.auth.password.forgot');
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword'])->name('api.auth.password.reset');
    Route::post('/account/setup', [PasswordResetController::class, 'setupPassword'])->name('api.auth.account.setup');

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/email/resend-verification/status', [AuthController::class, 'resendVerificationStatus'])->name('api.auth.email.resend.status');
        Route::post('/email/resend-verification', [AuthController::class, 'resendVerification'])->name('api.auth.email.resend');
        Route::get('/google/deletion-redirect', [OAuthController::class, 'redirectForDeletion'])->name('api.auth.google.deletion-redirect');
    });
});

// Public hub routes
Route::get('/location/approx', [LocationController::class, 'approximate'])->name('api.location.approx');
Route::get('/hubs', [HubController::class, 'index'])->name('api.hubs.index');
Route::get('/hubs/cities', [HubController::class, 'cities'])->name('api.hubs.cities');
Route::get('/hubs/{hub}', [HubController::class, 'show'])->name('api.hubs.show');
Route::get('/hubs/{hub}/ratings', [HubRatingController::class, 'index'])->name('api.hubs.ratings.index');
Route::get('/hubs/{hub}/ratings/courts', [HubRatingController::class, 'courts'])->name('api.hubs.ratings.courts');
Route::get('/hubs/{hub}/members', [HubMemberController::class, 'index'])->name('api.hubs.members.index');
Route::get('/hubs/{hub}/members/list', [HubMemberController::class, 'list'])->name('api.hubs.members.list');
Route::get('/hubs/{hub}/courts', [CourtController::class, 'index'])->name('api.hubs.courts.index');
Route::get('/hubs/{hub}/bookings', [BookingController::class, 'hubIndex'])->name('api.hubs.bookings.index');
Route::get('/bookings/{code}/qr', [BookingController::class, 'qrCode'])->name('api.bookings.qr');
Route::get('/hubs/{hub}/courts/{court}/bookings', [BookingController::class, 'index'])->name('api.hubs.courts.bookings.index');
Route::post('/hubs/{hub}/vouchers/preview', [BookingController::class, 'previewVoucher'])->name('api.hubs.vouchers.preview');

// Guest booking routes (public — verified by OTP)
Route::post('/hubs/{hub}/courts/{court}/guest-verify', [GuestBookingController::class, 'sendVerificationCode'])->name('api.hubs.courts.guest-verify');
Route::post('/hubs/{hub}/courts/{court}/guest-bookings', [GuestBookingController::class, 'store'])->name('api.hubs.courts.guest-bookings.store');
Route::post('/hubs/{hub}/courts/{court}/guest-bookings/{booking}/receipt', [GuestBookingController::class, 'uploadReceipt'])->name('api.hubs.courts.guest-bookings.receipt');

// Guest booking tracking routes (public — protected by per-booking UUID token)
Route::get('/guest-bookings/{token}', [GuestBookingTrackingController::class, 'show'])->name('api.guest-bookings.show');
Route::post('/guest-bookings/{token}/receipt', [GuestBookingTrackingController::class, 'uploadReceipt'])->name('api.guest-bookings.receipt');
Route::post('/guest-bookings/{token}/cancel', [GuestBookingTrackingController::class, 'cancel'])->name('api.guest-bookings.cancel');

// Public user profiles
Route::get('/users/resolve/{username}', [UserController::class, 'resolveUsername'])->name('api.users.resolve');
Route::get('/users/{user}', [UserController::class, 'show'])->name('api.users.show');

// Authenticated routes (any logged-in user)
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/hubs/{hub}/courts/{court}/bookings', [BookingController::class, 'store'])->name('api.hubs.courts.bookings.store');
    Route::post('/hubs/{hub}/courts/{court}/bookings/{booking}/receipt', [BookingController::class, 'uploadReceipt'])->name('api.hubs.courts.bookings.receipt');

    // User bookings
    Route::get('/user/bookings', [UserBookingController::class, 'index'])->name('api.user.bookings.index');
    Route::get('/user/bookings/page-of', [UserBookingController::class, 'pageOf'])->name('api.user.bookings.page-of');
    Route::get('/user/pending-review', [UserBookingController::class, 'pendingReview'])->name('api.user.pending-review');
    Route::post('/user/booking-review-skip', [UserBookingController::class, 'skipReview'])->name('api.user.booking-review-skip');
    Route::post('/user/bookings/{booking}/cancel', [UserBookingController::class, 'cancel'])->name('api.user.bookings.cancel');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('api.profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('api.profile.avatar');
    Route::post('/profile/banner', [ProfileController::class, 'uploadBanner'])->name('api.profile.banner');
    Route::get('/profile/change-password/status', [ProfileController::class, 'changePasswordStatus'])->name('api.profile.change-password.status');
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword'])->name('api.profile.change-password');
    Route::post('/profile/request-deletion', [ProfileController::class, 'requestDeletion'])->name('api.profile.request-deletion');
    Route::post('/profile/cancel-deletion', [ProfileController::class, 'cancelDeletion'])->name('api.profile.cancel-deletion');
    Route::get('/hub-owner-request', [HubOwnerRequestController::class, 'show'])->name('api.hub-owner-request.show');
    Route::post('/hub-owner-request', [HubOwnerRequestController::class, 'store'])->name('api.hub-owner-request.store');

    // Hearts
    Route::post('/users/{user}/heart', [UserController::class, 'toggleHeart'])->name('api.users.heart');

    // Hub membership
    Route::post('/hubs/{hub}/members', [HubMemberController::class, 'join'])->name('api.hubs.members.join');
    Route::delete('/hubs/{hub}/members', [HubMemberController::class, 'leave'])->name('api.hubs.members.leave');

    // Hub ratings
    Route::post('/hubs/{hub}/ratings', [HubRatingController::class, 'store'])->name('api.hubs.ratings.store');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('api.notifications.read-all');
    Route::patch('/notifications/{id}', [NotificationController::class, 'toggle'])->name('api.notifications.toggle');
});

// Admin-only routes (admin + super_admin, email verified)
Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/dashboard/hubs', [HubController::class, 'myHubs'])->name('api.dashboard.hubs');
    Route::post('/hubs', [HubController::class, 'store'])->name('api.hubs.store');
    Route::match(['put', 'post'], '/hubs/{hub}', [HubController::class, 'update'])->name('api.hubs.update');
    Route::delete('/hubs/{hub}', [HubController::class, 'destroy'])->name('api.hubs.destroy');
    Route::patch('/hubs/{hub}/show-on-profile', [HubController::class, 'updateShowOnProfile'])->name('api.hubs.show-on-profile');
    Route::post('/hubs/{hub}/courts', [CourtController::class, 'store'])->name('api.hubs.courts.store');
    Route::match(['put', 'post'], '/hubs/{hub}/courts/{court}', [CourtController::class, 'update'])->name('api.hubs.courts.update');
    Route::delete('/hubs/{hub}/courts/{court}', [CourtController::class, 'destroy'])->name('api.hubs.courts.destroy');

    // Owner booking management
    Route::get('/dashboard/hubs/{hub}/bookings', [OwnerBookingController::class, 'index'])->name('api.dashboard.hubs.bookings.index');
    Route::get('/dashboard/hubs/{hub}/bookings/{booking}', [OwnerBookingController::class, 'show'])->name('api.dashboard.hubs.bookings.show');
    Route::put('/dashboard/hubs/{hub}/bookings/{booking}', [OwnerBookingController::class, 'update'])->name('api.dashboard.hubs.bookings.update');
    Route::post('/dashboard/hubs/{hub}/bookings/{booking}/confirm', [OwnerBookingController::class, 'confirm'])->name('api.dashboard.hubs.bookings.confirm');
    Route::post('/dashboard/hubs/{hub}/bookings/{booking}/reject', [OwnerBookingController::class, 'reject'])->name('api.dashboard.hubs.bookings.reject');
    Route::post('/dashboard/hubs/{hub}/bookings/{booking}/cancel', [OwnerBookingController::class, 'cancel'])->name('api.dashboard.hubs.bookings.cancel');
    Route::post('/dashboard/hubs/{hub}/courts/{court}/walk-in', [OwnerBookingController::class, 'walkIn'])->name('api.dashboard.hubs.walk-in');
    Route::get('/dashboard/users/search', [OwnerBookingController::class, 'searchUsers'])->name('api.dashboard.users.search');
    Route::get('/dashboard/hubs/{hub}/bookings/verify/{code}', [OwnerBookingController::class, 'verifyByCode'])->name('api.dashboard.hubs.bookings.verify');

    // Hub events (owner)
    Route::get('/dashboard/hubs/{hub}/events', [HubEventController::class, 'index'])->name('api.dashboard.hubs.events.index');
    Route::post('/dashboard/hubs/{hub}/events', [HubEventController::class, 'store'])->name('api.dashboard.hubs.events.store');
    Route::put('/dashboard/hubs/{hub}/events/{event}', [HubEventController::class, 'update'])->name('api.dashboard.hubs.events.update');
    Route::delete('/dashboard/hubs/{hub}/events/{event}', [HubEventController::class, 'destroy'])->name('api.dashboard.hubs.events.destroy');
    Route::patch('/dashboard/hubs/{hub}/events/{event}/toggle', [HubEventController::class, 'toggle'])->name('api.dashboard.hubs.events.toggle');
});

// Super admin panel routes
Route::middleware(['auth:sanctum', 'super_admin'])->prefix('panel')->group(function (): void {
    Route::get('/stats', [SuperAdminController::class, 'stats'])->name('api.panel.stats');
    Route::get('/users', [SuperAdminController::class, 'users'])->name('api.panel.users');
    Route::post('/users', [SuperAdminController::class, 'store'])->name('api.panel.users.store');
    Route::put('/users/{user}', [SuperAdminController::class, 'update'])->name('api.panel.users.update');
    Route::patch('/users/{user}/verify-email', [SuperAdminController::class, 'verifyEmail'])->name('api.panel.users.verify-email');
    Route::patch('/users/{user}/role', [SuperAdminController::class, 'updateRole'])->name('api.panel.users.update-role');
    Route::delete('/users/{user}', [SuperAdminController::class, 'destroy'])->name('api.panel.users.destroy');
    Route::get('/hub-owner-requests', [SuperAdminHubOwnerRequestController::class, 'index'])->name('api.panel.hub-owner-requests');
    Route::post('/hub-owner-requests/{hubOwnerRequest}/approve', [SuperAdminHubOwnerRequestController::class, 'approve'])->name('api.panel.hub-owner-requests.approve');
    Route::post('/hub-owner-requests/{hubOwnerRequest}/reject', [SuperAdminHubOwnerRequestController::class, 'reject'])->name('api.panel.hub-owner-requests.reject');
});

Route::get('/status', static fn (): array => ['ok' => true])->name('api.status');
