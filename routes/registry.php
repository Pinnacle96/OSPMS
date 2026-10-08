<?php

use App\Http\Controllers\Web\AssignmentController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\MediaController;
use Illuminate\Support\Facades\Route;

foreach (['operators', 'drivers', 'vehicles', 'revenue-heads', 'fee-configurations'] as $catalog) {
    $name = $catalog === 'fee-configurations' ? 'fees' : str_replace('-', '_', $catalog);
    foreach ([['get', '', 'index'], ['get', '/create', 'create'], ['post', '', 'store'], ['get', '/{record}/edit', 'edit'], ['patch', '/{record}', 'update'], ['get', '/{record}', 'show']] as [$verb,$suffix,$action]) {
        Route::$verb('/'.$catalog.$suffix, [CatalogController::class, $action])->defaults('catalog', $catalog)->name($name.'.'.$action);
    }
}
Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
Route::get('/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
Route::get('/assignments/{assignment}/end', [AssignmentController::class, 'end'])->name('assignments.end');
Route::patch('/assignments/{assignment}/end', [AssignmentController::class, 'close'])->name('assignments.close');
Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
Route::post('/{catalog}/{record}/documents', [MediaController::class, 'store'])->whereIn('catalog', ['operators', 'drivers', 'vehicles'])->name('registry.documents.store');
Route::get('/{catalog}/{record}/documents/{media}', [MediaController::class, 'show'])->whereIn('catalog', ['operators', 'drivers', 'vehicles'])->name('registry.documents.show');
