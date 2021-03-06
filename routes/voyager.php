<?php

use TCG\Voyager\Events\Routing;
use TCG\Voyager\Events\RoutingAdmin;
use TCG\Voyager\Events\RoutingAdminAfter;
use TCG\Voyager\Events\RoutingAfter;
use TCG\Voyager\Models\DataType;

//tesssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssst
/*
|--------------------------------------------------------------------------
| Voyager Routes
|--------------------------------------------------------------------------
|
| This file is where you may override any of the routes that are included
| with Voyager.
|
*/


Route::group(['as' => 'voyager.'], function () {
    event(new Routing());

    $namespacePrefix = '\\'.config('voyager.controllers.namespace').'\\';

    Route::get('login', ['uses' => $namespacePrefix.'VoyagerAuthController@login',     'as' => 'login']);
    Route::post('login', ['uses' => $namespacePrefix.'VoyagerAuthController@postLogin', 'as' => 'postlogin']);

    Route::group(['middleware' => 'admin.user'], function () use ($namespacePrefix) {
        event(new RoutingAdmin());

        // Main Admin and Logout Route
        Route::get('/', ['uses' => $namespacePrefix.'VoyagerController@index',   'as' => 'dashboard']);
        Route::post('logout', ['uses' => $namespacePrefix.'VoyagerController@logout',  'as' => 'logout']);
        Route::post('upload', ['uses' => $namespacePrefix.'VoyagerController@upload',  'as' => 'upload']);

        Route::get('profile', ['uses' => $namespacePrefix.'VoyagerController@profile', 'as' => 'profile']);

        try {
            foreach (DataType::all() as $dataType) {
                $breadController = $dataType->controller
                                 ? $dataType->controller
                                 : $namespacePrefix.'VoyagerBaseController';

                Route::post($dataType->slug.'/addGroup', $breadController.'@addGroup')->name($dataType->slug.'.addGroup');
                Route::get($dataType->slug.'/restore', $breadController.'@restore')->name($dataType->slug.'.restore');
                Route::post($dataType->slug.'/restore', $breadController.'@restore_row')->name($dataType->slug.'.restore');
                Route::get($dataType->slug.'/redirect/{id}/{table}/{type}', $breadController.'@redirect')->name($dataType->slug.'.redirect');
                Route::get($dataType->slug.'/order', $breadController.'@order')->name($dataType->slug.'.order');
                Route::post($dataType->slug.'/order', $breadController.'@update_order')->name($dataType->slug.'.order');
                Route::resource($dataType->slug, $breadController);
            }
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("Custom routes hasn't been configured because: ".$e->getMessage(), 1);
        } catch (\Exception $e) {
            // do nothing, might just be because table not yet migrated.
        }

        //Activity Logs
        Route::resource('logs', $namespacePrefix.'VoyagerLogController');
        Route::post('logs/restoreLog', $namespacePrefix.'VoyagerLogController@restoreLog')->name('logs.restoreLog');
        // Role Routes
        Route::resource('roles', $namespacePrefix.'VoyagerRoleController');

        // Menu Routes
        Route::group([
            'as'     => 'menus.',
            'prefix' => 'menus/{menu}',
        ], function () use ($namespacePrefix) {
            Route::get('builder', ['uses' => $namespacePrefix.'VoyagerMenuController@builder',    'as' => 'builder']);
            Route::post('order', ['uses' => $namespacePrefix.'VoyagerMenuController@order_item', 'as' => 'order']);

            Route::group([
                'as'     => 'item.',
                'prefix' => 'item',
            ], function () use ($namespacePrefix) {
                Route::delete('{id}', ['uses' => $namespacePrefix.'VoyagerMenuController@delete_menu', 'as' => 'destroy']);
                Route::post('/', ['uses' => $namespacePrefix.'VoyagerMenuController@add_item',    'as' => 'add']);
                Route::put('/', ['uses' => $namespacePrefix.'VoyagerMenuController@update_item', 'as' => 'update']);
            });
        });

        // Settings
        Route::group([
            'as'     => 'settings.',
            'prefix' => 'settings',
        ], function () use ($namespacePrefix) {
            Route::get('/', ['uses' => $namespacePrefix.'VoyagerSettingsController@index',        'as' => 'index']);
            Route::post('/', ['uses' => $namespacePrefix.'VoyagerSettingsController@store',        'as' => 'store']);
            Route::put('/', ['uses' => $namespacePrefix.'VoyagerSettingsController@update',       'as' => 'update']);
            Route::delete('{id}', ['uses' => $namespacePrefix.'VoyagerSettingsController@delete',       'as' => 'delete']);
            Route::get('{id}/move_up', ['uses' => $namespacePrefix.'VoyagerSettingsController@move_up',      'as' => 'move_up']);
            Route::get('{id}/move_down', ['uses' => $namespacePrefix.'VoyagerSettingsController@move_down',    'as' => 'move_down']);
            Route::get('{id}/delete_value', ['uses' => $namespacePrefix.'VoyagerSettingsController@delete_value', 'as' => 'delete_value']);
        });

        // Admin Media
        Route::group([
            'as'     => 'media.',
            'prefix' => 'media',
        ], function () use ($namespacePrefix) {
            Route::get('/', ['uses' => $namespacePrefix.'VoyagerMediaController@index',              'as' => 'index']);
            Route::post('files', ['uses' => $namespacePrefix.'VoyagerMediaController@files',              'as' => 'files']);
            Route::post('new_folder', ['uses' => $namespacePrefix.'VoyagerMediaController@new_folder',         'as' => 'new_folder']);
            Route::post('delete_file_folder', ['uses' => $namespacePrefix.'VoyagerMediaController@delete_file_folder', 'as' => 'delete_file_folder']);
            Route::post('directories', ['uses' => $namespacePrefix.'VoyagerMediaController@get_all_dirs',       'as' => 'get_all_dirs']);
            Route::post('move_file', ['uses' => $namespacePrefix.'VoyagerMediaController@move_file',          'as' => 'move_file']);
            Route::post('rename_file', ['uses' => $namespacePrefix.'VoyagerMediaController@rename_file',        'as' => 'rename_file']);
            Route::post('upload', ['uses' => $namespacePrefix.'VoyagerMediaController@upload',             'as' => 'upload']);
            Route::post('remove', ['uses' => $namespacePrefix.'VoyagerMediaController@remove',             'as' => 'remove']);
            Route::post('crop', ['uses' => $namespacePrefix.'VoyagerMediaController@crop',             'as' => 'crop']);
        });

        // BREAD Routes
        Route::group([
            'as'     => 'bread.',
            'prefix' => 'bread',
        ], function () use ($namespacePrefix) {
            Route::get('/', ['uses' => $namespacePrefix.'VoyagerBreadController@index',              'as' => 'index']);
            Route::get('{table}/create', ['uses' => $namespacePrefix.'VoyagerBreadController@create',     'as' => 'create']);
            Route::post('/', ['uses' => $namespacePrefix.'VoyagerBreadController@store',   'as' => 'store']);
            Route::get('{table}/edit', ['uses' => $namespacePrefix.'VoyagerBreadController@edit', 'as' => 'edit']);
            Route::put('{id}', ['uses' => $namespacePrefix.'VoyagerBreadController@update',  'as' => 'update']);
            Route::delete('{id}', ['uses' => $namespacePrefix.'VoyagerBreadController@destroy',  'as' => 'delete']);
            Route::post('relationship', ['uses' => $namespacePrefix.'VoyagerBreadController@addRelationship',  'as' => 'relationship']);
            Route::get('delete_relationship/{id}', ['uses' => $namespacePrefix.'VoyagerBreadController@deleteRelationship',  'as' => 'delete_relationship']);
            Route::post('filter', ['uses' => $namespacePrefix.'VoyagerBreadController@addFilter',  'as' => 'filter']);
            Route::delete('delete_filter/{id}', ['uses' => $namespacePrefix.'VoyagerBreadController@deleteFilter',  'as' => 'delete_filter']);
            Route::post('order_filter', ['uses' => $namespacePrefix.'VoyagerBreadController@update_order',  'as' => 'order_filter']);

            Route::put('dataTable/{id}', ['uses' => $namespacePrefix.'VoyagerBreadController@updateDataTable',  'as' => 'updateDataTable']);
            Route::post('saveSmartTable', ['uses' => $namespacePrefix.'VoyagerBreadController@saveSmartTable',  'as' => 'saveSmartTable']);
            Route::post('getSmartRelationsCompare', ['uses' => $namespacePrefix.'VoyagerBreadController@getSmartRelationsCompare',  'as' => 'getSmartRelationsCompare']);
            Route::post('saveSmartGroupTable', ['uses' => $namespacePrefix.'VoyagerBreadController@saveSmartGroupTable',  'as' => 'saveSmartGroupTable']);
            Route::post('addDataTableRelationshipColumn/{id}', ['uses' => $namespacePrefix.'VoyagerBreadController@addDataTableRelationshipColumn',  'as' => 'addDataTableRelationshipColumn']);

//
            Route::post('save_data', ['uses' => $namespacePrefix.'VoyagerBreadController@saveData',  'as' => 'save_data']);
            Route::post('column_data', ['uses' => $namespacePrefix.'VoyagerBreadController@columnData',  'as' => 'column_data']);
            Route::post('saveSmartColWidth', ['uses' => $namespacePrefix.'VoyagerBreadController@saveSmartColWidth',  'as' => 'saveSmartColWidth']);
            Route::post('saveSmartData', ['uses' => $namespacePrefix.'VoyagerBreadController@saveSmartData',  'as' => 'saveSmartData']);
        });

        // Database Routes
        Route::resource('database', $namespacePrefix.'VoyagerDatabaseController');

        // Compass Routes
        Route::group([
            'as'     => 'compass.',
            'prefix' => 'compass',
        ], function () use ($namespacePrefix) {
            Route::get('/', ['uses' => $namespacePrefix.'VoyagerCompassController@index',  'as' => 'index']);
            Route::post('/', ['uses' => $namespacePrefix.'VoyagerCompassController@index',  'as' => 'post']);
        });
        event(new RoutingAdminAfter());
    });
    event(new RoutingAfter());
});
