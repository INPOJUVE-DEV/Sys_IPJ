<?php

use App\Http\Controllers\Admin\BeneficiariosController as AdminBeneficiariosController;
use App\Http\Controllers\Admin\CatalogosController;
use App\Http\Controllers\Admin\ComponentCatalogController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BeneficiarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomicilioController;
use App\Http\Controllers\Encargado\BeneficiariosController as EncargadoBeneficiariosController;
use App\Http\Controllers\MisRegistrosController;
use App\Http\Controllers\ProfileController;
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
    if ($user->hasRole('encargado_360')) {
        return redirect()->route('s360.enc360.view');
    }
    if ($user->hasRole('capturista')) {
        return redirect('/capturista');
    }
    return redirect()->route('dashboard');
});Route::get('/', function () {
    if (! Auth::check()) {
        // Mostrar login directamente (200 OK) para mejorar DX/tests
        return view('auth.login');
    }
    $user = Auth::user();
    if ($user->hasRole('admin')) {
        return redirect('/admin');
    }
    if ($user->hasRole('encargado_360')) {
        return redirect()->route('s360.enc360.view');
    }
    if ($user->hasRole('capturista')) {
        return redirect('/capturista');
    }
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Compatibilidad: endpoint antiguo de KPIs de capturista (200 OK)
Route::get('/mi-progreso/kpis', [DashboardController::class, 'miProgresoKpis'])->middleware(['auth','role:capturista']);

// Alias de registro de captura usado en tests
Route::post('/captura/registrar', [BeneficiarioController::class, 'store'])->name('captura.registrar')->middleware(['auth','role:admin|capturista|encargado_360']);

// Secciones por rol
Route::middleware(['auth','role:admin'])->group(function () {
    Route::get('/admin', [DashboardController::class, 'admin'])->name('admin.home');
    Route::get('/admin/kpis', [DashboardController::class, 'adminKpis'])->name('admin.kpis');
});
Route::middleware(['auth','role:encargado_removed'])->group(function () {
    Route::get('/encargado', [DashboardController::class, 'encargado'])->name('encargado.home');
    Route::get('/encargado/kpis', [DashboardController::class, 'encargadoKpis'])->name('encargado.kpis');
    Route::get('/encargado/beneficiarios', [EncargadoBeneficiariosController::class, 'index'])->name('encargado.beneficiarios.index');
    // Export debe ir antes de la ruta con parámetro para evitar colisiones
    Route::get('/encargado/beneficiarios/export', [EncargadoBeneficiariosController::class, 'export'])->name('encargado.beneficiarios.export');
    Route::get('/encargado/beneficiarios/{beneficiario}', [EncargadoBeneficiariosController::class, 'show'])->name('encargado.beneficiarios.show');
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

// Beneficiarios y Domicilios (admin, capturista, encargado_360)
Route::middleware(['auth','role:admin|capturista|encargado_360'])->group(function () {
    Route::resource('beneficiarios', BeneficiarioController::class)->except(['show']);
    Route::resource('domicilios', DomicilioController::class)->except(['show']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin: gestión de usuarios
Route::middleware(['auth','role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'usuario']);

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

    Route::get('themes/current', [ThemeController::class, 'show'])->name('themes.current.show');
    Route::put('themes/current', [ThemeController::class, 'update'])->name('themes.current.update');
    Route::post('catalogos/import', [CatalogosController::class, 'import'])->name('catalogos.import');
    Route::get('beneficiarios', [AdminBeneficiariosController::class, 'index'])->name('beneficiarios.index');
    // Export antes de par�metro para no capturar "export" como {beneficiario}
    Route::get('beneficiarios/export', [AdminBeneficiariosController::class, 'export'])->name('beneficiarios.export');
    Route::get('beneficiarios/{beneficiario}', [AdminBeneficiariosController::class, 'show'])->name('beneficiarios.show');
});

require __DIR__.'/auth.php';

// Compatibilidad adicional: asegurar que /mi-progreso/kpis responda 200 OK
Route::get('/mi-progreso/kpis', [DashboardController::class, 'miProgresoKpis'])->middleware(['auth','role:capturista']);

// -------------------- SALUD 360 --------------------
use App\Http\Controllers\S360\S360AdminController;
use App\Http\Controllers\S360\S360BienestarController;
use App\Http\Controllers\S360\S360Enc360Controller;
use App\Http\Controllers\S360\S360PsicoController;

// Admin
Route::middleware(['auth','role:admin','access.log'])->prefix('s360/admin')->name('s360.admin.')->group(function () {
    Route::get('dash', [S360AdminController::class, 'dash'])->name('dash')->middleware('permission:s360.manage');
    Route::post('users', [S360AdminController::class, 'storeUsers'])->name('users.store')->middleware(['permission:s360.manage','throttle:10,1']);
});

// Encargado Bienestar
Route::middleware(['auth','role:encargado_bienestar','access.log'])->prefix('s360/bienestar')->name('s360.bienestar.')->group(function () {
    Route::get('/', [S360BienestarController::class, 'view'])->name('view');
    Route::get('dash', [S360BienestarController::class, 'dash'])->name('dash')->middleware('permission:s360.enc_bienestar.view_dash');
    Route::get('sesiones/ultimas', [S360BienestarController::class, 'latestSessions'])->name('sesiones.latest')->middleware('permission:s360.enc_bienestar.view_dash');
    Route::get('citas/proximas', [S360BienestarController::class, 'upcoming'])->name('citas.upcoming')->middleware('permission:s360.enc_bienestar.view_dash');
    Route::post('enc360', [S360BienestarController::class, 'storeEncargado360'])->name('enc360.store')->middleware(['permission:s360.enc_bienestar.manage','throttle:10,1']);
});

// Encargado 360
Route::middleware(['auth','role:encargado_360','access.log'])->prefix('s360/enc360')->name('s360.enc360.')->group(function () {
    Route::get('/', [S360Enc360Controller::class, 'view'])->name('view');
    Route::get('dash', [S360Enc360Controller::class, 'dash'])->name('dash')->middleware('permission:s360.enc360.view_dash');
    Route::get('sesiones/ultimas', [S360Enc360Controller::class, 'latestSessions'])->name('sesiones.latest')->middleware('permission:s360.enc360.view_dash');
    Route::get('citas/proximas', [S360Enc360Controller::class, 'upcoming'])->name('citas.upcoming')->middleware('permission:s360.enc360.view_dash');
    Route::get('asignaciones', [S360Enc360Controller::class, 'asignacionesView'])->name('asignaciones')->middleware('permission:s360.enc360.view_dash');
    Route::get('psicologos', [S360Enc360Controller::class, 'psicologosView'])->name('psicologos.view')->middleware('permission:s360.enc360.view_dash');
    Route::post('psicologos', [S360Enc360Controller::class, 'storePsicologo'])->name('psicologos.store')->middleware(['permission:s360.enc360.assign','throttle:10,1']);
    Route::post('assign', [S360Enc360Controller::class, 'assign'])->name('assign')->middleware('permission:s360.enc360.assign');
    Route::put('assign/{beneficiario}', [S360Enc360Controller::class, 'reassign'])->name('assign.update')->middleware('permission:s360.enc360.assign');
    Route::get('pacientes', [S360Enc360Controller::class, 'patients'])->name('patients')->middleware('permission:s360.enc360.view_dash');
    Route::get('psicologos/list', [S360Enc360Controller::class, 'psicologos'])->name('psicologos.list')->middleware('permission:s360.enc360.view_dash');
    Route::get('sesiones/{session}/manage', [S360Enc360Controller::class, 'manageSessionView'])->name('sesiones.manage');
    Route::put('sesiones/{session}', [S360Enc360Controller::class, 'updateSession'])->name('sesiones.update')->middleware('permission:s360.data.update_by_enc360');
});

// Psicólogo
Route::middleware(['auth','role:psicologo','access.log'])->prefix('s360/psico')->name('s360.psico.')->group(function () {
    Route::get('/', [S360PsicoController::class, 'pacientesView'])->name('view');
    Route::get('pacientes', [S360PsicoController::class, 'pacientes'])->name('pacientes')->middleware('permission:s360.psico.read_patients');
    Route::get('paciente/{id}', [S360PsicoController::class, 'showPaciente'])->name('paciente')->middleware('permission:s360.psico.read_patients');
    Route::get('paciente/{id}/show', [S360PsicoController::class, 'pacienteView'])->name('paciente.view');
    Route::get('agenda-semana', [S360PsicoController::class, 'agendaSemana'])->name('agenda.semana');
    Route::post('sesiones', [S360PsicoController::class, 'storeSesion'])->name('sesiones.store')->middleware('permission:s360.psico.create_session');
    Route::get('sesiones/{beneficiario}', [S360PsicoController::class, 'historial'])->name('sesiones.historial')->middleware('permission:s360.psico.view_history');
    Route::get('sesiones/{beneficiario}/show', [S360PsicoController::class, 'historialView'])->name('sesiones.historial.view');
});

















