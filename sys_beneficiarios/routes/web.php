<?php

use App\Http\Controllers\Admin\BeneficiariosController as AdminBeneficiariosController;
use App\Http\Controllers\Admin\CatalogosController;
use App\Http\Controllers\Admin\ComponentCatalogController;
use App\Http\Controllers\Admin\EventoTipoController as AdminEventoTipoController;
use App\Http\Controllers\Admin\InventarioMovimientoController;
use App\Http\Controllers\Admin\InventarioProteccionController as AdminInventarioProteccionController;
use App\Http\Controllers\Admin\InventarioTarjetaController as AdminInventarioTarjetaController;
use App\Http\Controllers\Admin\OficinaController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BeneficiarioController;
use App\Http\Controllers\Delegacion\DashboardController as DelegacionDashboardController;
use App\Http\Controllers\Delegacion\InventarioTarjetaController as DelegacionInventarioTarjetaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomicilioController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\InscripcionDashboardController;
use App\Http\Controllers\MisRegistrosController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SkatePlaza\BeneficiarioController as SkatePlazaBeneficiarioController;
use App\Http\Controllers\SkatePlaza\DashboardController as SkatePlazaDashboardController;
use App\Http\Controllers\SkatePlaza\PrestamoController as SkatePlazaPrestamoController;
use App\Http\Controllers\StackController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
Route::get('/', function () {
    if (! Auth::check()) {
        // Mostrar login directamente (200 OK) para mejorar DX/tests
        return view('auth.login');
    }
    $user = Auth::user();
    if ($user->hasRole('admin')) {
        return redirect('/admin');
    }
    if ($user->hasRole('delegado')) {
        return redirect('/delegacion');
    }
    if ($user->hasRole('capturista')) {
        return redirect('/capturista');
    }
    if ($user->hasRole('capturista_programas')) {
        return redirect('/inscripciones');
    }
    if ($user->hasRole('skate_plaza')) {
        return redirect('/skate-plaza');
    }
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Compatibilidad: endpoint antiguo de KPIs de capturista (200 OK)
Route::get('/mi-progreso/kpis', [DashboardController::class, 'miProgresoKpis'])->middleware(['auth','role:capturista']);

// Alias de registro de captura usado en tests
Route::post('/captura/registrar', [BeneficiarioController::class, 'store'])->name('captura.registrar')->middleware(['auth','role:admin|delegado|capturista']);

Route::get('/stack', [StackController::class, 'index'])
    ->middleware(['auth', 'role:admin|delegado'])
    ->name('stack.index');

// Secciones por rol
Route::middleware(['auth','role:admin'])->group(function () {
    Route::get('/admin', [DashboardController::class, 'admin'])->name('admin.home');
    Route::get('/admin/kpis', [DashboardController::class, 'adminKpis'])->name('admin.kpis');
});
Route::middleware(['auth','role:capturista'])->group(function () {
    Route::get('/capturista', [DashboardController::class, 'capturista'])->name('capturista.home');
    // KPIs capturista consistente bajo /capturista/kpis
    Route::get('/capturista/kpis', [DashboardController::class, 'miProgresoKpis'])->name('capturista.kpis');
    // Redirección de compatibilidad desde ruta anterior
    // REDIRECT DISABLED

    // Mis registros (solo capturista)
    Route::prefix('mis-registros')->name('mis-registros.')->group(function () {
        Route::get('/', [MisRegistrosController::class, 'index'])->name('index');
        Route::get('/{beneficiario}', [MisRegistrosController::class, 'show'])->name('show');
        Route::get('/{beneficiario}/edit', [MisRegistrosController::class, 'edit'])->name('edit');
        Route::put('/{beneficiario}', [MisRegistrosController::class, 'update'])->name('update');
    });
});

// Beneficiarios y Domicilios (admin, delegado, capturista)
Route::middleware(['auth','role:admin|delegado|capturista'])->group(function () {
    Route::resource('beneficiarios', BeneficiarioController::class)->except(['show']);
    Route::resource('domicilios', DomicilioController::class)->except(['show']);
});

Route::middleware(['auth','role:admin|delegado'])->group(function () {
    Route::resource('eventos', EventoController::class)->except(['show']);
});

Route::middleware(['auth','role:admin'])->group(function () {
    Route::resource('programas', ProgramaController::class)->except(['show']);
});

Route::middleware(['auth','role:admin|delegado|capturista|capturista_programas'])->group(function () {
    Route::get('inscripciones', [InscripcionController::class, 'create'])->name('inscripciones.index');
    Route::get('inscripciones/create', fn () => redirect()->route('inscripciones.index'))->name('inscripciones.create');
    Route::post('inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
});

Route::middleware(['auth','role:admin|delegado|capturista'])->group(function () {
    Route::get('inscripciones/lista', [InscripcionController::class, 'index'])->name('inscripciones.list');
    Route::get('inscripciones/{inscripcion}/edit', [InscripcionController::class, 'edit'])->name('inscripciones.edit');
    Route::put('inscripciones/{inscripcion}', [InscripcionController::class, 'update'])->name('inscripciones.update');
    Route::delete('inscripciones/{inscripcion}', [InscripcionController::class, 'destroy'])->name('inscripciones.destroy');
    Route::get('inscripciones-dashboard', [InscripcionDashboardController::class, 'index'])->name('inscripciones.dashboard');
    Route::get('inscripciones-dashboard/kpis', [InscripcionDashboardController::class, 'kpis'])->name('inscripciones.kpis');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:delegado'])->prefix('delegacion')->name('delegacion.')->group(function () {
    Route::get('/', [DelegacionDashboardController::class, 'index'])->name('home');
    Route::resource('usuarios', UserController::class)->except(['show'])->parameters(['usuarios' => 'usuario']);
    Route::prefix('inventario/tarjetas')->name('inventario.tarjetas.')->group(function () {
        Route::get('/', [DelegacionInventarioTarjetaController::class, 'index'])->name('index');
        Route::post('assign-range', [DelegacionInventarioTarjetaController::class, 'assignRange'])->name('assignRange');
        Route::post('{tarjeta}/status', [DelegacionInventarioTarjetaController::class, 'updateStatus'])->name('status');
    });
});

Route::middleware(['auth', 'role:skate_plaza'])->prefix('skate-plaza')->name('skate-plaza.')->group(function () {
    Route::get('/', [SkatePlazaDashboardController::class, 'index'])->name('home');
    Route::get('beneficiarios/buscar', [SkatePlazaBeneficiarioController::class, 'search'])->name('beneficiarios.search');
    Route::post('prestamos', [SkatePlazaPrestamoController::class, 'store'])->name('prestamos.store');
    Route::post('prestamos/{proteccion}/devolver', [SkatePlazaPrestamoController::class, 'devolver'])->name('prestamos.devolver');
});

// Admin: gestión de usuarios
Route::middleware(['auth','role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', UserController::class)->except(['show'])->parameters(['usuarios' => 'usuario']);
    Route::get('oficinas', [OficinaController::class, 'index'])->name('oficinas.index');
    Route::post('oficinas/municipios/{municipio}', [OficinaController::class, 'assignMunicipio'])->name('oficinas.municipios.assign');
    Route::prefix('inventario/tarjetas')->name('inventario.tarjetas.')->group(function () {
        Route::get('/', [AdminInventarioTarjetaController::class, 'index'])->name('index');
        Route::post('store-range', [AdminInventarioTarjetaController::class, 'storeRange'])->name('storeRange');
        Route::post('transfer-range', [AdminInventarioTarjetaController::class, 'transferRange'])->name('transferRange');
        Route::post('assign-range', [AdminInventarioTarjetaController::class, 'assignRange'])->name('assignRange');
        Route::post('{tarjeta}/status', [AdminInventarioTarjetaController::class, 'updateStatus'])->name('status');
    });
    Route::prefix('inventario/protecciones')->name('inventario.protecciones.')->group(function () {
        Route::get('/', [AdminInventarioProteccionController::class, 'index'])->name('index');
        Route::post('store-batch', [AdminInventarioProteccionController::class, 'storeBatch'])->name('storeBatch');
        Route::post('{proteccion}/transfer', [AdminInventarioProteccionController::class, 'transfer'])->name('transfer');
        Route::post('{proteccion}/status', [AdminInventarioProteccionController::class, 'updateStatus'])->name('status');
    });
    Route::get('inventario/movimientos', [InventarioMovimientoController::class, 'index'])->name('inventario.movimientos.index');

    Route::prefix('pages')->name('pages.')->group(function () {
        Route::get('/', [AdminPageController::class, 'index'])->name('index');
        Route::post('/', [AdminPageController::class, 'store'])->name('store');
        Route::get('{page:slug}/draft', [AdminPageController::class, 'showDraft'])->name('draft.show');
        Route::put('{page:slug}/draft', [AdminPageController::class, 'updateDraft'])->name('draft.update');
        Route::post('{page:slug}/publish', [AdminPageController::class, 'publish'])->name('publish');
        Route::get('{page:slug}/versions', [AdminPageController::class, 'versions'])->name('versions');
        Route::post('{page:slug}/rollback', [AdminPageController::class, 'rollback'])->name('rollback');
    });

    Route::get('catalogos', [CatalogosController::class, 'index'])->name('catalogos.index');
    Route::get('components', [ComponentCatalogController::class, 'index'])->name('components.index');
    Route::post('components', [ComponentCatalogController::class, 'upsert'])->name('components.upsert');
    Route::resource('evento-tipos', AdminEventoTipoController::class)
        ->except(['show'])
        ->parameters(['evento-tipos' => 'eventoTipo']);

    Route::get('themes/current', [ThemeController::class, 'show'])->name('themes.current.show');
    Route::put('themes/current', [ThemeController::class, 'update'])->name('themes.current.update');
    Route::post('catalogos/import', [CatalogosController::class, 'import'])->name('catalogos.import');
    Route::get('beneficiarios', [AdminBeneficiariosController::class, 'index'])->name('beneficiarios.index');
    // Export antes de parámetro para no capturar "export" como {beneficiario}
    Route::get('beneficiarios/export', [AdminBeneficiariosController::class, 'export'])->name('beneficiarios.export');
    Route::get('beneficiarios/{beneficiario}', [AdminBeneficiariosController::class, 'show'])->name('beneficiarios.show');
});

require __DIR__.'/auth.php';
