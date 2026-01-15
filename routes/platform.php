<?php

declare(strict_types=1);

use App\Orchid\Screens\Examples\ExampleActionsScreen;
use App\Orchid\Screens\Examples\ExampleCardsScreen;
use App\Orchid\Screens\Examples\ExampleChartsScreen;
use App\Orchid\Screens\Examples\ExampleFieldsAdvancedScreen;
use App\Orchid\Screens\Examples\ExampleFieldsScreen;
use App\Orchid\Screens\Examples\ExampleGridScreen;
use App\Orchid\Screens\Examples\ExampleLayoutsScreen;
use App\Orchid\Screens\Examples\ExampleScreen;
use App\Orchid\Screens\Examples\ExampleTextEditorsScreen;
use App\Orchid\Screens\PlatformScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;
use App\Orchid\Screens\User\UserProfileScreen;
use App\Orchid\Screens\Operator\DashboardScreen as OperatorDashboardScreen;
use App\Orchid\Screens\Operator\OrdersScreen as OperatorOrdersScreen;
use App\Orchid\Screens\Operator\DeliveriesScreen as OperatorDeliveriesScreen;
use App\Orchid\Screens\Settings\GenApiSettingsScreen;
use App\Orchid\Screens\Settings\PaymentSettingsScreen;
use App\Orchid\Screens\Settings\TelegramSettingsScreen;
use App\Orchid\Screens\Settings\MainSettingsScreen;
use App\Orchid\Screens\CollageEditScreen;
use App\Orchid\Screens\CollagesScreen;
use App\Orchid\Screens\MainScreen;
use App\Orchid\Screens\SupportTicketsScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the need "dashboard" middleware group. Now create something great!
|
*/

// Main
Route::screen('/main', MainScreen::class)
    ->name('platform.main');

// Platform > Profile
Route::screen('profile', UserProfileScreen::class)
    ->name('platform.profile')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Profile'), route('platform.profile')));

// Platform > System > Users > User
Route::screen('users/{user}/edit', UserEditScreen::class)
    ->name('platform.systems.users.edit')
    ->breadcrumbs(fn (Trail $trail, $user) => $trail
        ->parent('platform.systems.users')
        ->push($user->name, route('platform.systems.users.edit', $user)));

// Platform > System > Users > Create
Route::screen('users/create', UserEditScreen::class)
    ->name('platform.systems.users.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.users')
        ->push(__('Create'), route('platform.systems.users.create')));

// Platform > System > Users
Route::screen('users', UserListScreen::class)
    ->name('platform.systems.users')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Users'), route('platform.systems.users')));

// Platform > System > Roles > Role
Route::screen('roles/{role}/edit', RoleEditScreen::class)
    ->name('platform.systems.roles.edit')
    ->breadcrumbs(fn (Trail $trail, $role) => $trail
        ->parent('platform.systems.roles')
        ->push($role->name, route('platform.systems.roles.edit', $role)));

// Platform > System > Roles > Create
Route::screen('roles/create', RoleEditScreen::class)
    ->name('platform.systems.roles.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.roles')
        ->push(__('Create'), route('platform.systems.roles.create')));

// Platform > System > Roles
Route::screen('roles', RoleListScreen::class)
    ->name('platform.systems.roles')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Roles'), route('platform.systems.roles')));

// Example...
Route::screen('example', ExampleScreen::class)
    ->name('platform.example')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Example Screen'));

Route::screen('/examples/form/fields', ExampleFieldsScreen::class)->name('platform.example.fields');
Route::screen('/examples/form/advanced', ExampleFieldsAdvancedScreen::class)->name('platform.example.advanced');
Route::screen('/examples/form/editors', ExampleTextEditorsScreen::class)->name('platform.example.editors');
Route::screen('/examples/form/actions', ExampleActionsScreen::class)->name('platform.example.actions');

Route::screen('/examples/layouts', ExampleLayoutsScreen::class)->name('platform.example.layouts');
Route::screen('/examples/grid', ExampleGridScreen::class)->name('platform.example.grid');
Route::screen('/examples/charts', ExampleChartsScreen::class)->name('platform.example.charts');
Route::screen('/examples/cards', ExampleCardsScreen::class)->name('platform.example.cards');

// Operator Routes
Route::screen('operator/dashboard', OperatorDashboardScreen::class)
    ->name('platform.operator.dashboard')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Operator Dashboard', route('platform.operator.dashboard')));

Route::screen('operator/orders', OperatorOrdersScreen::class)
    ->name('platform.operator.orders')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.operator.dashboard')
        ->push('Orders', route('platform.operator.orders')));

Route::screen('operator/deliveries', OperatorDeliveriesScreen::class)
    ->name('platform.operator.deliveries')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.operator.dashboard')
        ->push('Deliveries', route('platform.operator.deliveries')));

// Settings Routes
Route::screen('settings', MainSettingsScreen::class)
    ->name('platform.settings')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Settings', route('platform.settings')));

Route::screen('settings/genapi', GenApiSettingsScreen::class)
    ->name('platform.settings.genapi')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.settings')
        ->push('Image Generation', route('platform.settings.genapi')));

Route::screen('settings/payment', PaymentSettingsScreen::class)
    ->name('platform.settings.payment')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.settings')
        ->push('Payment Settings', route('platform.settings.payment')));

Route::screen('settings/telegram', TelegramSettingsScreen::class)
    ->name('platform.settings.telegram')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.settings')
        ->push('Telegram Settings', route('platform.settings.telegram')));

// Support Tickets Routes
Route::screen('support-tickets', SupportTicketsScreen::class)
    ->name('platform.support-tickets')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Support Tickets', route('platform.support-tickets')));

// Collage Routes
Route::screen('collages', CollagesScreen::class)
    ->name('platform.collages')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Collages', route('platform.collages')));

Route::screen('collages/create', CollageEditScreen::class)
    ->name('platform.collage.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.collages')
        ->push('Create Collage', route('platform.collage.create')));

Route::screen('collages/{collage}/edit', CollageEditScreen::class)
    ->name('platform.collage.edit')
    ->breadcrumbs(fn (Trail $trail, $collage) => $trail
        ->parent('platform.collages')
        ->push($collage->title ?? 'Edit Collage', route('platform.collage.edit', $collage)));

// Route::screen('idea', Idea::class, 'platform.screens.idea');
