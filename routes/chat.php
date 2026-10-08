<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationExportController;
use App\Http\Controllers\CustomProviderController;
use App\Http\Controllers\LiveCaptureController;
use App\Http\Controllers\MediaGenerationController;
use App\Http\Controllers\PromptEnhanceController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureUserIsAuthenticated;
use Illuminate\Support\Facades\Route;

Route::get('chat/', [ChatController::class, 'index'])
    ->middleware(EnsureUserIsAuthenticated::class)
    ->name('chat.index');

Route::get('chat/models', [ChatController::class, 'models'])->name('chat.models');

Route::get('chat/local-models', [ChatController::class, 'localModels'])->name('chat.local-models');

Route::get('chat/capabilities', [ChatController::class, 'capabilities'])->name('chat.capabilities');

/*
|--------------------------------------------------------------------------
| Custom Providers
|--------------------------------------------------------------------------
|
| The endpoints declared in WHALE_CUSTOM_PROVIDERS. Discovery asks an endpoint
| what it serves, import keeps chosen models, and sync reconciles the two.
|
*/

Route::get('chat/custom-providers', [CustomProviderController::class, 'index'])
    ->name('chat.custom-providers');

Route::get('chat/custom-providers/{provider}/models', [CustomProviderController::class, 'discover'])
    ->name('chat.custom-providers.models');

Route::post('chat/custom-providers/{provider}/import', [CustomProviderController::class, 'import'])
    ->name('chat.custom-providers.import');

Route::post('chat/custom-providers/{provider}/sync', [CustomProviderController::class, 'sync'])
    ->name('chat.custom-providers.sync');

Route::post('chat/enhance', PromptEnhanceController::class)
    ->middleware('throttle:15,1')
    ->name('chat.enhance');

Route::get('chat/studio', StudioController::class)->name('chat.studio');

Route::view('chat/workspace', 'chat.workspace')->name('chat.workspace');

Route::post('chat/', [ChatController::class, 'send'])
    ->middleware('throttle:20,1')
    ->name('chat.send');

Route::post('chat/image', [MediaGenerationController::class, 'generateImage'])
    ->middleware('throttle:10,1')
    ->name('chat.image');

Route::post('chat/image/edit', [MediaGenerationController::class, 'editImage'])
    ->middleware('throttle:10,1')
    ->name('chat.image.edit');

Route::post('chat/audio', [MediaGenerationController::class, 'generateAudio'])
    ->middleware('throttle:20,1')
    ->name('chat.audio');

/*
|--------------------------------------------------------------------------
| Live Speech & Camera
|--------------------------------------------------------------------------
|
| The composer records speech and shows the camera. Live speech is sent in
| short clips so words appear while the user is still talking, which is why
| the transcription limit is generous: it is a rate limit across many chunks,
| not one upload.
|
*/

Route::get('chat/capture', [LiveCaptureController::class, 'capabilities'])
    ->name('chat.capture');

Route::post('chat/transcribe', [LiveCaptureController::class, 'transcribe'])
    ->middleware('throttle:60,1')
    ->name('chat.transcribe');

Route::post('chat/camera', [LiveCaptureController::class, 'describeScene'])
    ->middleware('throttle:20,1')
    ->name('chat.camera');

Route::post('chat/ocr', [MediaGenerationController::class, 'ocr'])
    ->middleware('throttle:10,1')
    ->name('chat.ocr');

Route::post('chat/vector', [MediaGenerationController::class, 'generateVector'])
    ->middleware('throttle:10,1')
    ->name('chat.vector');

Route::post('chat/video', [MediaGenerationController::class, 'generateVideo'])
    ->middleware('throttle:5,1')
    ->name('chat.video');

/*
|--------------------------------------------------------------------------
| Workspace / IDE
|--------------------------------------------------------------------------
*/

Route::prefix('chat/workspace')->name('chat.workspace.')->group(function () {
    Route::get('files', [WorkspaceController::class, 'index'])->name('index');
    Route::get('search', [WorkspaceController::class, 'search'])->name('search');
    Route::post('terminal', [WorkspaceController::class, 'terminal'])->name('terminal');
    Route::post('mkdir', [WorkspaceController::class, 'mkdir'])->name('mkdir');
    Route::get('mentions', [WorkspaceController::class, 'mentions'])->name('mentions');
    Route::get('file', [WorkspaceController::class, 'show'])->name('show');
    Route::put('file', [WorkspaceController::class, 'store'])->name('store');
    Route::post('upload', [WorkspaceController::class, 'upload'])->name('upload');
    Route::post('move', [WorkspaceController::class, 'move'])->name('move');
    Route::delete('file', [WorkspaceController::class, 'destroy'])->name('destroy');
    Route::get('preview', [WorkspaceController::class, 'preview'])->name('preview');
    Route::get('export', [WorkspaceController::class, 'export'])->name('export');
});

/*
|--------------------------------------------------------------------------
| Projects & conversation history (sidebar management)
|--------------------------------------------------------------------------
*/

Route::prefix('chat/history')->name('chat.history.')->group(function () {
    Route::get('/', [ConversationController::class, 'index'])->name('index');
    Route::post('/', [ConversationController::class, 'store'])->name('store');
    Route::patch('{conversation}', [ConversationController::class, 'update'])->name('update');
    Route::delete('{conversation}', [ConversationController::class, 'destroy'])->name('destroy');
    Route::get('{conversation}/messages', [ConversationController::class, 'messages'])->name('messages');
    Route::get('{conversation}/export', ConversationExportController::class)->name('export');
    Route::post('{conversation}/messages', [ConversationController::class, 'appendMessage'])->name('messages.append');
    Route::post('{conversation}/share', [ConversationController::class, 'share'])->name('share');
    Route::delete('{conversation}/share', [ConversationController::class, 'unshare'])->name('share.destroy');

    Route::post('projects', [ConversationController::class, 'storeProject'])->name('projects.store');
    Route::patch('projects/{project}', [ConversationController::class, 'updateProject'])->name('projects.update');
    Route::delete('projects/{project}', [ConversationController::class, 'destroyProject'])->name('projects.destroy');
});

/*
|--------------------------------------------------------------------------
| Public, read-only view of a shared conversation
|--------------------------------------------------------------------------
*/

Route::get('chat/shared/{token}', [ConversationController::class, 'shared'])->name('chat.shared');
